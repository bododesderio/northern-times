<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AdSlot;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class AdminAdController extends Controller
{
    public function index(): Response
    {
        return $this->render('admin/ads/index', [
            'slots'         => AdSlot::allOrdered(),
            'csrf'          => Csrf::token(),
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    public function update(string $id): Response
    {
        $r = Request::createFromGlobals();

        $adType       = $r->request->get('ad_type', 'image');
        $content      = trim((string)$r->request->get('content', ''));
        $linkUrl      = trim((string)$r->request->get('link_url', ''));
        $linkUrlTablet = trim((string)$r->request->get('link_url_tablet', ''));
        $linkUrlMobile = trim((string)$r->request->get('link_url_mobile', ''));
        $contentTablet = trim((string)$r->request->get('content_tablet', ''));
        $contentMobile = trim((string)$r->request->get('content_mobile', ''));
        $active       = $r->request->get('is_active') ? true : false;
        $start        = $r->request->get('start_date') ?: null;
        $end          = $r->request->get('end_date') ?: null;
        $deviceTarget = $r->request->get('device_target', 'all');
        $maxWidth     = trim((string)$r->request->get('max_width', ''));
        $maxHeight    = trim((string)$r->request->get('max_height', ''));
        $customCss    = trim((string)$r->request->get('custom_css', ''));
        $nofollow     = $r->request->get('nofollow') ? true : false;
        $altText      = trim((string)$r->request->get('alt_text', ''));

        // Validate device_target
        if (!in_array($deviceTarget, ['all', 'mobile', 'desktop'], true)) {
            $deviceTarget = 'all';
        }

        // Handle image uploads (desktop, tablet, mobile)
        $allowed = ['jpg','jpeg','png','gif','webp','svg'];
        $uploadDir = __DIR__ . '/../../storage/uploads/Ads/';
        @mkdir($uploadDir, 0755, true);

        $files = $r->files->get('ad_image');
        if ($files && $files->isValid()) {
            $ext = $files->guessExtension() ?: 'jpg';
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'ad-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                $files->move($uploadDir, $filename);
                $content = '/uploads/Ads/' . $filename;
                $adType = 'image';
            }
        }

        $tabletFile = $r->files->get('ad_image_tablet');
        if ($tabletFile && $tabletFile->isValid()) {
            $ext = $tabletFile->guessExtension() ?: 'jpg';
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'ad-tablet-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                $tabletFile->move($uploadDir, $filename);
                $contentTablet = '/uploads/Ads/' . $filename;
            }
        }

        $mobileFile = $r->files->get('ad_image_mobile');
        if ($mobileFile && $mobileFile->isValid()) {
            $ext = $mobileFile->guessExtension() ?: 'jpg';
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'ad-mobile-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                $mobileFile->move($uploadDir, $filename);
                $contentMobile = '/uploads/Ads/' . $filename;
            }
        }

        AdSlot::updateSlot($id, [
            'ad_type'         => $adType,
            'content'         => $content,
            'content_tablet'  => $contentTablet ?: '',
            'content_mobile'  => $contentMobile ?: '',
            'link_url'        => $linkUrl ?: null,
            'link_url_tablet' => $linkUrlTablet ?: '',
            'link_url_mobile' => $linkUrlMobile ?: '',
            'is_active'       => $active,
            'start_date'      => $start,
            'end_date'        => $end,
            'device_target'   => $deviceTarget,
            'max_width'       => $maxWidth ?: null,
            'max_height'      => $maxHeight ?: null,
            'custom_css'      => $customCss ?: null,
            'nofollow'        => $nofollow,
            'alt_text'        => $altText ?: null,
        ]);

        Flash::set('success', 'Ad slot updated.');
        return new RedirectResponse('/admin/ads');
    }

    public function toggle(string $id): Response
    {
        AdSlot::toggleActive($id);
        Flash::set('success', 'Ad slot toggled.');
        return new RedirectResponse('/admin/ads');
    }
}