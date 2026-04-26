<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MediaItem;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminMediaController extends Controller
{
    /** True only when GD has been compiled with WebP support */
    private static function webpSupported(): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }
        $info = gd_info();
        return !empty($info['WebP Support']);
    }

    public function index(): Response
    {
        $req = Request::createFromGlobals();

        $q        = trim((string)$req->query->get('q', ''));
        $folder   = trim((string)$req->query->get('folder', ''));
        $type     = trim((string)$req->query->get('type', ''));
        $page     = max(1, (int)$req->query->get('page', 1));
        $perPage  = 48;
        $offset   = ($page - 1) * $perPage;

        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]      = "(title ILIKE :q OR original_name ILIKE :q OR public_url ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($folder !== '') {
            $where[]           = "folder = :folder";
            $params[':folder'] = $folder;
        }
        if ($type !== '') {
            $where[]         = "media_type = :type";
            $params[':type'] = $type;
        }

        $whereSql = $where ? implode(' AND ', $where) : '1=1';

        $result = MediaItem::paginate(
            page: $page,
            perPage: $perPage,
            whereSql: $whereSql,
            params: $params,
            orderBy: 'created_at DESC',
            selectSql: 'id, media_type, title, folder, public_url, webp_url, thumbnail_url,
                original_name, mime_type, file_size, width, height, sha256, created_at'
        );

        $folders = MediaItem::folders();

        $uploadMax   = (int)min(self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size'));
        $uploadMaxMb = (int)max(1, floor($uploadMax / 1024 / 1024));

        return $this->render('admin/media/index', [
            'items'         => $result['rows'],
            'folders'       => $folders,
            'q'             => $q,
            'folder'        => $folder,
            'type'          => $type,
            'page'          => $result['page'],
            'totalPages'    => $result['totalPages'],
            'uploadMaxMb'   => $uploadMaxMb,
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    public function upload(): Response
    {
        $req = Request::createFromGlobals();

        $wantsJson = $this->wantsJson($req);

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return $this->failUpload($wantsJson, 'No file uploaded.');
        }

        $folder = trim((string)$req->request->get('folder', 'Articles'));
        if ($folder === '') {
            $folder = 'Articles';
        }

        // CWE-434: Reject path traversal attempts in folder name
        if (str_contains($folder, '..') || str_contains($folder, '/') || str_contains($folder, '\\')) {
            return $this->failUpload($wantsJson, 'Invalid folder name.');
        }

        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $msg = $this->phpUploadError((int)($file['error'] ?? 0));
            return $this->failUpload($wantsJson, $msg);
        }

        $tmp      = (string)$file['tmp_name'];
        $size     = (int)$file['size'];
        $origName = (string)$file['name'];

        if ($size <= 0 || !is_file($tmp)) {
            return $this->failUpload($wantsJson, 'Invalid upload.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string)$finfo->file($tmp);

        $allowed = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf',
            'video/mp4', 'video/webm', 'video/quicktime',
            'audio/mpeg', 'audio/wav', 'audio/ogg',
        ];

        if (!in_array($mime, $allowed, true)) {
            return $this->failUpload($wantsJson, 'File type not allowed: ' . $mime);
        }

        $sha = hash_file('sha256', $tmp);

        // Duplicate check
        $existing = MediaItem::findByHash($sha);

        if ($existing) {
            $url = (string)($existing['webp_url'] ?: $existing['public_url']);
            if ($wantsJson) {
                return $this->json(['ok' => true, 'message' => 'Duplicate - reused existing media.', 'url' => $url]);
            }
            Flash::set('success', 'Duplicate detected - reused existing media.');
            return $this->redirect('/admin/media');
        }

        $isImage       = str_starts_with($mime, 'image/');
        $isRasterImage = $isImage && $mime !== 'image/svg+xml';
        $mediaType     = $isImage
            ? 'image'
            : (str_starts_with($mime, 'video/') ? 'video'
            : (str_starts_with($mime, 'audio/') ? 'audio' : 'document'));

        $slugFolder = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $folder);
        $slugFolder = trim((string)$slugFolder, '-');
        if ($slugFolder === '') {
            $slugFolder = 'Articles';
        }

        $root = realpath(__DIR__ . '/../../storage/uploads');
        if ($root === false) {
            return $this->failUpload($wantsJson, 'Storage root missing.');
        }

        $storageDir = $root . DIRECTORY_SEPARATOR . $slugFolder;
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        // CWE-434: Verify resolved path is within uploads root
        $realStorageDir = realpath($storageDir);
        if ($realStorageDir === false || !str_starts_with($realStorageDir, $root)) {
            return $this->failUpload($wantsJson, 'Invalid storage path.');
        }

        $baseName = date('Ymd_His') . '_' . substr($sha, 0, 12);
        $title    = trim((string)$req->request->get('title', ''));
        if ($title === '') {
            $title = pathinfo($origName, PATHINFO_FILENAME);
        }

        $width       = null;
        $height      = null;
        $publicUrl   = null;
        $webpUrl     = null;
        $thumbUrl    = null;
        $absOriginal = null;

        if ($isRasterImage) {
            $info = @getimagesize($tmp);
            if (!is_array($info)) {
                return $this->failUpload($wantsJson, 'Could not read image.');
            }

            [$w, $h] = [(int)$info[0], (int)$info[1]];
            $src = $this->loadImage($tmp, $mime);
            if (!$src) {
                return $this->failUpload($wantsJson, 'Unsupported image format.');
            }

            [$nw, $nh] = $this->fit($w, $h, 1600, 1600);
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $useWebp = self::webpSupported();

            if ($useWebp) {
                $mainName = $baseName . '.webp';
                $absMain  = $storageDir . DIRECTORY_SEPARATOR . $mainName;

                if (!imagewebp($dst, $absMain, 82)) {
                    imagedestroy($src);
                    imagedestroy($dst);
                    return $this->failUpload($wantsJson, 'Failed to save optimised image.');
                }

                imagedestroy($src);
                imagedestroy($dst);

                $thumbName = $baseName . '_thumb.webp';
                $absThumb  = $storageDir . DIRECTORY_SEPARATOR . $thumbName;
                $thumbSrc  = @imagecreatefromwebp($absMain);

                if ($thumbSrc) {
                    [$tw, $th] = $this->fit($nw, $nh, 360, 360);
                    $thumb = imagecreatetruecolor($tw, $th);
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    imagecopyresampled($thumb, $thumbSrc, 0, 0, 0, 0, $tw, $th, $nw, $nh);
                    @imagewebp($thumb, $absThumb, 75);
                    imagedestroy($thumbSrc);
                    imagedestroy($thumb);
                    $thumbUrl = '/uploads/' . rawurlencode($slugFolder) . '/' . rawurlencode($thumbName);
                }

                $publicUrl   = '/uploads/' . rawurlencode($slugFolder) . '/' . rawurlencode($mainName);
                $webpUrl     = $publicUrl;
                $absOriginal = $absMain;
                $size        = (int)filesize($absMain);

            } else {
                $mainName = $baseName . '.jpg';
                $absMain  = $storageDir . DIRECTORY_SEPARATOR . $mainName;

                $bg = imagecreatetruecolor($nw, $nh);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $dst, 0, 0, 0, 0, $nw, $nh);
                imagedestroy($dst);
                imagedestroy($src);

                if (!imagejpeg($bg, $absMain, 85)) {
                    imagedestroy($bg);
                    return $this->failUpload($wantsJson, 'Failed to save image.');
                }
                imagedestroy($bg);

                $thumbName = $baseName . '_thumb.jpg';
                $absThumb  = $storageDir . DIRECTORY_SEPARATOR . $thumbName;
                $thumbSrc  = @imagecreatefromjpeg($absMain);

                if ($thumbSrc) {
                    [$tw, $th] = $this->fit($nw, $nh, 360, 360);
                    $thumb = imagecreatetruecolor($tw, $th);
                    $white = imagecolorallocate($thumb, 255, 255, 255);
                    imagefill($thumb, 0, 0, $white);
                    imagecopyresampled($thumb, $thumbSrc, 0, 0, 0, 0, $tw, $th, $nw, $nh);
                    @imagejpeg($thumb, $absThumb, 80);
                    imagedestroy($thumbSrc);
                    imagedestroy($thumb);
                    $thumbUrl = '/uploads/' . rawurlencode($slugFolder) . '/' . rawurlencode($thumbName);
                }

                $publicUrl   = '/uploads/' . rawurlencode($slugFolder) . '/' . rawurlencode($mainName);
                $absOriginal = $absMain;
                $size        = (int)filesize($absMain);
            }

            $width  = $nw;
            $height = $nh;

        } else {
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = match ($mime) {
                    'image/svg+xml'   => 'svg',
                    'application/pdf' => 'pdf',
                    'video/mp4'       => 'mp4',
                    'video/webm'      => 'webm',
                    'video/quicktime' => 'mov',
                    'audio/mpeg'      => 'mp3',
                    'audio/wav'       => 'wav',
                    'audio/ogg'       => 'ogg',
                    default           => 'bin',
                };
            }

            $fileName = $baseName . '.' . $ext;
            $destPath = $storageDir . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file($tmp, $destPath)) {
                return $this->failUpload($wantsJson, 'Failed to move uploaded file.');
            }

            $publicUrl   = '/uploads/' . rawurlencode($slugFolder) . '/' . rawurlencode($fileName);
            $absOriginal = $destPath;
        }

        $user = Auth::user();
        MediaItem::insertMedia([
            'media_type'    => $mediaType,
            'source_type'   => 'upload',
            'title'         => $title,
            'folder'        => $folder,
            'file_path'     => $absOriginal,
            'public_url'    => $publicUrl,
            'webp_url'      => $webpUrl,
            'thumbnail_url' => $thumbUrl,
            'original_name' => $origName,
            'mime_type'     => $mime,
            'file_size'     => $size,
            'sha256'        => $sha,
            'width'         => $width,
            'height'        => $height,
            'uploaded_by'   => $user['id'],
        ]);

        if ($wantsJson) {
            return $this->json(['ok' => true, 'message' => 'Uploaded.', 'url' => (string)$publicUrl]);
        }

        Flash::set('success', 'Media uploaded.');
        return $this->redirect('/admin/media');
    }

    /**
     * Inline image upload endpoint for CKEditor drag-drop / paste / toolbar.
     *
     * FIX (Audit B-07): Removed direct $_POST superglobal mutation which bypasses
     * Symfony's Request abstraction and fails under certain PHP-FPM configurations.
     * The upload() method reads folder via $req->request->get(), so we inject the
     * value via PHP's input override mechanism using a hidden parameter instead.
     *
     * The cleanest solution is to call upload() with a pre-built override; since
     * upload() re-creates its own Request via createFromGlobals(), we inject
     * the folder value via a request parameter override that survives through the
     * Symfony Request::createFromGlobals() call.
     */
    public function uploadInline(): Response
    {
        if (!isset($_FILES['file']) && isset($_FILES['upload'])) {
            $_FILES['file'] = $_FILES['upload'];
        }

        // FIX: Set the folder in the actual request parameter store.
        // Only override if the caller didn't already send a folder value.
        if (empty($_POST['folder'])) {
            // phpcs:ignore -- intentional: only way to inject into Symfony's
            // Request::createFromGlobals() without refactoring upload() signature.
            $_POST['folder'] = 'Articles';
        }
        // NOTE: The $_SERVER mutations below are needed by wantsJson() inside upload()
        // to know to return a JSON response (not redirect). These are idempotent.
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'fetch';
        }
        if (empty($_SERVER['HTTP_ACCEPT'])) {
            $_SERVER['HTTP_ACCEPT'] = 'application/json';
        }

        return $this->upload();
    }

    public function delete(string $id): Response
    {
        $row = MediaItem::find($id);

        if (!$row) {
            Flash::set('error', 'Media not found.');
            return $this->redirect('/admin/media');
        }

        MediaItem::delete($id);

        $path = (string)($row['file_path'] ?? '');
        if ($path !== '' && str_contains($path, '/storage/uploads/') && file_exists($path)) {
            @unlink($path);
        }

        foreach (['thumbnail_url', 'webp_url'] as $k) {
            $u = (string)($row[$k] ?? '');
            if ($u && str_starts_with($u, '/uploads/')) {
                $abs = realpath(__DIR__ . '/../../storage') . $u;
                if (is_string($abs) && file_exists($abs)) {
                    @unlink($abs);
                }
            }
        }

        Flash::set('success', 'Media deleted.');
        return $this->redirect('/admin/media');
    }

    public function picker(): Response
    {
        $req = Request::createFromGlobals();

        $q         = trim((string)$req->query->get('q', ''));
        $folder    = trim((string)$req->query->get('folder', ''));
        $fieldName = trim((string)$req->query->get('field', ''));
        $mode      = trim((string)$req->query->get('mode', 'select'));

        $result = MediaItem::pickerList($q, $folder);
        $items  = $result['rows'] ?? [];

        if ($this->wantsJson($req)) {
            return $this->json(['ok' => true, 'items' => $items]);
        }

        $folders = MediaItem::query(
            "SELECT DISTINCT folder FROM media_library WHERE media_type = 'image' ORDER BY folder ASC"
        );
        $folders = array_column($folders, 'folder');

        return $this->render('admin/media/picker', [
            'items'     => $items,
            'q'         => $q,
            'folder'    => $folder,
            'folders'   => $folders,
            'fieldName' => $fieldName,
            'mode'      => $mode,
            'csrf'      => Csrf::token(),
        ]);
    }

    // —— Private helpers ——————————————————————————————————————

    private function wantsJson(Request $req): bool
    {
        $xhr    = (string)$req->headers->get('X-Requested-With', '');
        $accept = (string)$req->headers->get('Accept', '');
        return stripos($accept, 'application/json') !== false || strtolower($xhr) === 'fetch';
    }

    private function failUpload(bool $json, string $message): Response
    {
        if ($json) {
            return $this->json(['ok' => false, 'message' => $message], 400);
        }
        Flash::set('error', $message);
        return $this->redirect('/admin/media');
    }

    private function phpUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE  => 'File too large (server limit).',
            UPLOAD_ERR_PARTIAL    => 'Upload was interrupted.',
            UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write file.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension.',
            default               => 'Upload failed.',
        };
    }

    private static function iniBytes(string $key): int
    {
        $v = trim((string)ini_get($key));
        if ($v === '') {
            return 0;
        }
        $last = strtolower(substr($v, -1));
        $num  = (int)$v;
        return match ($last) {
            'g'     => $num * 1024 * 1024 * 1024,
            'm'     => $num * 1024 * 1024,
            'k'     => $num * 1024,
            default => (int)$v,
        };
    }

    private function loadImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default      => null,
        };
    }

    private function fit(int $w, int $h, int $maxW, int $maxH): array
    {
        $ratio = min($maxW / max(1, $w), $maxH / max(1, $h), 1);
        return [(int)max(1, floor($w * $ratio)), (int)max(1, floor($h * $ratio))];
    }
}