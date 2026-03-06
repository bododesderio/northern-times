<?php
$pageTitle = 'Social & Push Settings';
$activeNav = 'push-settings';
$slot = null;
ob_start();

function settingField(string $key, string $label, string $value, string $type = 'text', string $help = ''): void {
  echo '<div style="margin-bottom:16px">';
  echo '<label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">' . htmlspecialchars($label) . '</label>';
  if ($type === 'password') {
    echo '<input type="password" name="' . $key . '" value="' . htmlspecialchars($value) . '" autocomplete="new-password" style="width:100%;padding:10px 14px;border:1px solid #e2e2e2;border-radius:10px;font:inherit;font-size:14px;box-sizing:border-box">';
  } else {
    echo '<input type="text" name="' . $key . '" value="' . htmlspecialchars($value) . '" style="width:100%;padding:10px 14px;border:1px solid #e2e2e2;border-radius:10px;font:inherit;font-size:14px;box-sizing:border-box">';
  }
  if ($help) echo '<p style="margin:4px 0 0;font-size:12px;color:#888">' . $help . '</p>';
  echo '</div>';
}

function toggleField(string $key, string $label, string $value): void {
  echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">';
  echo '<input type="checkbox" name="' . $key . '" value="1" id="' . $key . '" ' . ($value === '1' ? 'checked' : '') . ' style="width:18px;height:18px;cursor:pointer;accent-color:#c00">';
  echo '<label for="' . $key . '" style="font-size:14px;cursor:pointer">' . htmlspecialchars($label) . '</label>';
  echo '</div>';
}
?>

<?php if (!empty($flash_success)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#d4edda;color:#155724"><?= h($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
  <div style="padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;background:#fde8e8;color:#991b1b"><?= h($flash_error) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:22px;font-weight:800;margin:0">Social & Push Notification Settings</h1>
    <p style="margin:4px 0 0;color:#666;font-size:14px">Configure social auto-posting and browser push notifications.</p>
  </div>
  <a href="/admin/social/posts" class="btn light" style="font-size:13px">📋 View Post Log</a>
</div>

<form method="POST" action="/admin/push/settings">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

  <div style="background:#fff;border:1px solid #e2e2e2;border-radius:16px;padding:28px;margin-bottom:24px">
    <h2 style="font-size:17px;font-weight:700;margin:0 0 6px">🔔 Browser Push Notifications</h2>
    <p style="color:#666;font-size:13px;margin:0 0 20px">Send instant notifications to subscribers when articles are published. Requires VAPID keys.</p>
    <div style="background:#f8f8f8;border-radius:12px;padding:18px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <div>
        <div style="font-size:28px;font-weight:800;color:#121212"><?= number_format($push_count) ?></div>
        <div style="font-size:12px;color:#888">Active subscribers</div>
      </div>
      <?php if (!$push_vapid_public): ?>
      <div style="flex:1;background:#fef3c7;padding:12px 16px;border-radius:10px;font-size:13px;color:#92400e">⚠ VAPID keys not generated yet. Generate them below to enable push notifications.</div>
      <?php else: ?>
      <div style="flex:1;background:#d4edda;padding:12px 16px;border-radius:10px;font-size:13px;color:#155724">✓ VAPID keys configured. Push notifications are <?= $push_enabled === '1' ? 'enabled' : 'disabled' ?>.</div>
      <?php endif; ?>
    </div>
    <?php toggleField('push_enabled', 'Enable browser push notifications', $push_enabled) ?>
    <?php settingField('push_vapid_public',  'VAPID Public Key',           $push_vapid_public,  'text',     'Base64url-encoded uncompressed EC public key (65 bytes). Auto-generated below.') ?>
    <?php settingField('push_vapid_private', 'VAPID Private Key',          $push_vapid_private, 'password', 'Keep this secret. Base64url-encoded EC private key.') ?>
    <?php settingField('push_subject',       'Contact URL (push_subject)', $push_subject,       'text',     'e.g. mailto:admin@yourdomain.com — required by push services.') ?>
    <div style="margin-top:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <button type="button" id="generateVapidBtn" class="btn light" style="font-size:13px">🔑 Generate New VAPID Keys</button>
      <span style="font-size:12px;color:#888">Only generate once — changing keys invalidates all existing subscriptions.</span>
    </div>
  </div>

  <div style="background:#fff;border:1px solid #e2e2e2;border-radius:16px;padding:28px;margin-bottom:24px">
    <h2 style="font-size:17px;font-weight:700;margin:0 0 6px">📘 Facebook</h2>
    <p style="color:#666;font-size:13px;margin:0 0 20px">Auto-post to a Facebook Page via the Graph API. Requires a Page Access Token with <code>pages_manage_posts</code> permission.</p>
    <?php toggleField('social_facebook_enabled', 'Enable Facebook auto-posting', get_site_setting('social_facebook_enabled')) ?>
    <?php settingField('social_facebook_page_id', 'Page ID',           get_site_setting('social_facebook_page_id'), 'text',     'Your Facebook Page numeric ID.') ?>
    <?php settingField('social_facebook_token',   'Page Access Token', get_site_setting('social_facebook_token'),   'password', 'Long-lived page access token from Meta for Developers.') ?>
  </div>

  <div style="background:#fff;border:1px solid #e2e2e2;border-radius:16px;padding:28px;margin-bottom:24px">
    <h2 style="font-size:17px;font-weight:700;margin:0 0 6px">🐦 Twitter / X</h2>
    <p style="color:#666;font-size:13px;margin:0 0 20px">Auto-tweet via the Twitter v2 API. Requires a Developer App with Read+Write permissions and OAuth 1.0a credentials.</p>
    <?php toggleField('social_twitter_enabled', 'Enable Twitter/X auto-posting', get_site_setting('social_twitter_enabled')) ?>
    <?php settingField('social_twitter_api_key',       'API Key (Consumer Key)', get_site_setting('social_twitter_api_key'),       'password') ?>
    <?php settingField('social_twitter_api_secret',    'API Key Secret',         get_site_setting('social_twitter_api_secret'),    'password') ?>
    <?php settingField('social_twitter_access_token',  'Access Token',           get_site_setting('social_twitter_access_token'),  'password') ?>
    <?php settingField('social_twitter_access_secret', 'Access Token Secret',    get_site_setting('social_twitter_access_secret'), 'password') ?>
  </div>

  <div style="background:#fff;border:1px solid #e2e2e2;border-radius:16px;padding:28px;margin-bottom:24px">
    <h2 style="font-size:17px;font-weight:700;margin:0 0 6px">✈️ Telegram</h2>
    <p style="color:#666;font-size:13px;margin:0 0 20px">Auto-post to a Telegram channel or group. Create a bot via @BotFather, add it as admin to your channel.</p>
    <?php toggleField('social_telegram_enabled', 'Enable Telegram auto-posting', get_site_setting('social_telegram_enabled')) ?>
    <?php settingField('social_telegram_bot_token', 'Bot Token', get_site_setting('social_telegram_bot_token'), 'password', 'From @BotFather. Format: 123456789:AAAA...') ?>
    <?php settingField('social_telegram_chat_id',   'Chat ID',   get_site_setting('social_telegram_chat_id'),   'text',     'Channel: @yourchannel or numeric ID like -1001234567890') ?>
  </div>

  <div style="background:#fff;border:1px solid #e2e2e2;border-radius:16px;padding:28px;margin-bottom:24px">
    <h2 style="font-size:17px;font-weight:700;margin:0 0 6px">💬 WhatsApp</h2>
    <p style="color:#666;font-size:13px;margin:0 0 20px">Send via the WhatsApp Business Cloud API (Meta). Requires a Business account and verified phone number.</p>
    <?php toggleField('social_whatsapp_enabled', 'Enable WhatsApp auto-posting', get_site_setting('social_whatsapp_enabled')) ?>
    <?php settingField('social_whatsapp_phone_id', 'Phone Number ID', get_site_setting('social_whatsapp_phone_id'), 'text',     'From Meta Business > WhatsApp > Getting Started.') ?>
    <?php settingField('social_whatsapp_token',    'Access Token',    get_site_setting('social_whatsapp_token'),    'password', 'Permanent token from Meta Business Suite.') ?>
    <?php settingField('social_whatsapp_to',       'Send To',         get_site_setting('social_whatsapp_to'),       'text',     'Recipient phone number with country code, e.g. 256700000000') ?>
  </div>

  <div style="background:#fff;border:1px solid #e2e2e2;border-radius:16px;padding:28px;margin-bottom:24px">
    <h2 style="font-size:17px;font-weight:700;margin:0 0 6px">💼 LinkedIn</h2>
    <p style="color:#666;font-size:13px;margin:0 0 20px">Auto-post to a LinkedIn profile via the UGC Posts API. Requires a Developer App with <code>w_member_social</code> permission.</p>
    <?php toggleField('social_linkedin_enabled', 'Enable LinkedIn auto-posting', get_site_setting('social_linkedin_enabled')) ?>
    <?php settingField('social_linkedin_token',      'Access Token', get_site_setting('social_linkedin_token'),      'password', 'OAuth 2.0 access token with w_member_social scope.') ?>
    <?php settingField('social_linkedin_person_urn', 'Person URN',   get_site_setting('social_linkedin_person_urn'), 'text',     'e.g. urn:li:person:xxxxxxxx — from /v2/me endpoint.') ?>
  </div>

  <div style="display:flex;gap:12px;margin-top:8px">
    <button type="submit" class="btn primary" style="padding:12px 28px;font-size:15px;font-weight:700">Save All Settings</button>
    <a href="/admin/social/posts" class="btn light" style="padding:12px 20px;font-size:14px">View Post Log</a>
  </div>
</form>

<!-- VAPID key generation form — MUST be outside the main form above -->
<form id="generateVapidForm" method="POST" action="/admin/push/generate-keys" style="display:none">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
</form>
<script>
document.getElementById('generateVapidBtn').addEventListener('click', function () {
  if (confirm('Generate new VAPID keys?\n\nWarning: this invalidates all existing push subscriptions. Subscribers re-subscribe automatically on their next visit.')) {
    document.getElementById('generateVapidForm').submit();
  }
});
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';
?>