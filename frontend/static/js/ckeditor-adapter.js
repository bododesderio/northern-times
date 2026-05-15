/**
 * Northern Times — CKEditor 5 Upload Adapter & Media Picker Bridge
 *
 * Provides two ways to insert images into article/policy content:
 *   1. Drag-and-drop / paste / toolbar upload  →  NTUploadAdapter
 *   2. Browse existing media library           →  NTMediaPickerBridge
 *
 * Usage (register with CKEditor):
 *   ClassicEditor.create(element, {
 *       extraPlugins: [NTUploadAdapterPlugin],
 *       ...
 *   })
 *
 * Depends on: <meta name="csrf-token" content="..."> in <head>
 */

/* ================================================================
   1. UPLOAD ADAPTER — handles drag-drop, paste, toolbar upload
   ================================================================ */
class NTUploadAdapter {
    constructor(loader) {
        this.loader = loader;
        this.xhr = null;
    }

    upload() {
        return this.loader.file.then(file => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            this.xhr = xhr;

            // --- Build form data ---
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folder', 'Articles');

            // CSRF token from meta tag or hidden input
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfInput = document.querySelector('input[name="_csrf"]');
            const token = csrfMeta?.content || csrfInput?.value || '';

            if (token) {
                formData.append('csrfmiddlewaretoken', token); // must match Django CSRF field name
            }

            // --- Configure request ---
            xhr.open('POST', '/admin/media/upload/', true);
            xhr.responseType = 'json';

            // Pass CSRF as header too (middleware checks both)
            if (token) {
                xhr.setRequestHeader('X-CSRFToken', token);
            }

            // Signal this is an AJAX request
            xhr.setRequestHeader('X-Requested-With', 'fetch');
            xhr.setRequestHeader('Accept', 'application/json');

            // --- Upload progress ---
            if (xhr.upload) {
                xhr.upload.addEventListener('progress', evt => {
                    if (evt.lengthComputable) {
                        this.loader.uploadTotal = evt.total;
                        this.loader.uploaded = evt.loaded;
                    }
                });
            }

            // --- Response handlers ---
            xhr.addEventListener('load', () => {
                const response = xhr.response;

                if (!response || xhr.status >= 400) {
                    const msg = response?.message || `Upload failed (HTTP ${xhr.status})`;
                    return reject(msg);
                }

                if (!response.url) {
                    return reject('Server did not return an image URL.');
                }

                resolve({ default: response.url });
            });

            xhr.addEventListener('error', () => reject('Upload failed — network error.'));
            xhr.addEventListener('abort', () => reject('Upload aborted.'));

            // --- Send ---
            xhr.send(formData);
        }));
    }

    abort() {
        if (this.xhr) {
            this.xhr.abort();
        }
    }
}

/**
 * CKEditor plugin function — registers the adapter with FileRepository.
 * Pass this to `extraPlugins: [NTUploadAdapterPlugin]`
 */
function NTUploadAdapterPlugin(editor) {
    editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
        return new NTUploadAdapter(loader);
    };
}

/* ================================================================
   2. MEDIA PICKER BRIDGE — insert existing library images into editor
   ================================================================ */

/**
 * Opens the media picker in "insert into editor" mode.
 * Called from the "Insert Media" button in article_form.
 *
 * @param {object} editorInstance  - CKEditor 5 editor instance
 * @param {object} options         - { mode: 'insert' }
 */
function ntInsertMediaFromPicker(editorInstance, options) {
    if (!editorInstance) {
        console.warn('NTMediaPicker: No editor instance.');
        return;
    }

    // Store reference so the postMessage handler can call ntInsertImageIntoEditor
    window._ntEditorInstance = editorInstance;
    window._ntInsertMode = options?.mode || 'insert';

    // Open the media picker modal (article_form.php controls the modal/iframe)
    const openBtn = document.getElementById('insertMediaBtn');
    if (openBtn) {
        openBtn.click();
    } else {
        console.warn('NTMediaPicker: #insertMediaBtn not found.');
    }
}

/**
 * Inserts an image into the CKEditor using its native model API.
 *
 * CKEditor 5 has a strict schema — raw HTML with custom classes/styles gets
 * stripped. Instead we create an `imageBlock` model element which the Image
 * plugin recognises, and optionally attach a caption via the ImageCaption plugin.
 *
 * @param {object}  editor   - CKEditor 5 instance
 * @param {string}  url      - Image URL
 * @param {string}  alt      - Alt text
 * @param {string}  size     - 'full' | 'half' | 'original' (mapped to imageStyle)
 * @param {string}  align    - 'center' | 'left' | 'right' | 'none'
 * @param {string}  caption  - Optional caption text
 * @param {string}  width    - Optional explicit width (unused — CKEditor handles sizing)
 */
function ntInsertImageIntoEditor(editor, url, alt, size, align, caption, width) {
    if (!editor || !url) return;

    editor.model.change(writer => {
        // Create the imageBlock element (recognised by CKEditor's Image plugin)
        const imageElement = writer.createElement('imageBlock', {
            src: url,
            alt: alt || ''
        });

        // Attach caption if ImageCaption plugin is available
        if (caption) {
            try {
                const captionElement = writer.createElement('caption');
                writer.appendText(caption, captionElement);
                writer.append(captionElement, imageElement);
            } catch (e) {
                // ImageCaption plugin not available — skip silently
            }
        }

        editor.model.insertContent(imageElement);
    });

    // Apply image style (side = float left/right, full = block center)
    try {
        if (align === 'left' || align === 'right' || size === 'half') {
            editor.execute('imageStyle', { value: 'side' });
        }
    } catch (e) {
        // imageStyle command not available — skip
    }
}