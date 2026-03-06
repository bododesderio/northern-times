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
                formData.append('_csrf', token); // must match CsrfMiddleware field name
            }

            // --- Configure request ---
            xhr.open('POST', '/admin/media/upload-inline', true);
            xhr.responseType = 'json';

            // Pass CSRF as header too (middleware checks both)
            if (token) {
                xhr.setRequestHeader('X-CSRF-Token', token);
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
 * Inserts a figure+img element into the CKEditor at cursor position.
 *
 * @param {object}  editor   - CKEditor instance
 * @param {string}  url      - Image URL
 * @param {string}  alt      - Alt text
 * @param {string}  size     - legacy: 'full' | 'half' | 'original'
 * @param {string}  align    - 'center' | 'left' | 'right' | 'none'
 * @param {string}  caption  - Optional caption (rendered italic + small)
 * @param {string}  width    - Optional explicit width e.g. '75%' (overrides size)
 */
function ntInsertImageIntoEditor(editor, url, alt, size, align, caption, width) {
    if (!editor || !url) return;

    // Determine width style: explicit width wins, then legacy size tokens
    let widthPct = '';
    if (width && width !== '100%') {
        widthPct = width; // e.g. '75%'
    } else if (size === 'half') {
        widthPct = '50%';
    }
    // 'full' / 100% / 'original' → no constraint

    // Build figure inline styles
    const figStyles = [];
    if (widthPct) figStyles.push(`width:${widthPct}`);
    if (align === 'left')       figStyles.push('float:left;margin:0 18px 12px 0');
    else if (align === 'right') figStyles.push('float:right;margin:0 0 12px 18px');
    else                        figStyles.push('margin:16px auto;display:block');

    // CSS classes (for frontend stylesheet targeting)
    const classes = ['article-image'];
    if (widthPct && parseInt(widthPct) <= 60) classes.push('article-image-half');
    else classes.push('article-image-full');
    if (align === 'left')  classes.push('article-image-left');
    else if (align === 'right') classes.push('article-image-right');

    const altAttr = (alt || '').replace(/"/g, '&quot;');
    const captionHtml = caption
        ? `<figcaption style="font-style:italic;font-size:0.85em;color:#666;text-align:center;margin-top:5px;line-height:1.45">${caption.replace(/</g, '&lt;')}</figcaption>`
        : '';

    const html =
        `<figure class="${classes.join(' ')}" style="${figStyles.join(';')}">` +
        `<img src="${url}" alt="${altAttr}" loading="lazy" style="width:100%;height:auto;display:block">` +
        captionHtml +
        `</figure>`;

    const viewFragment  = editor.data.processor.toView(html);
    const modelFragment = editor.data.toModel(viewFragment);
    editor.model.insertContent(modelFragment);
}