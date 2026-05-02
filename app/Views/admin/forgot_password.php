<?php
$pageTitle    = 'Forgot Password';
$activeNav    = '';
$hideAdminNav = true;
$csrfToken    = $csrf ?? \App\Services\Csrf::token();
$siteTitle    = function_exists('site_name') ? site_name() : ($_ENV['APP_NAME'] ?? 'Northern Times');

ob_start();
?>

<div class="lp-wrap">
  <div class="lp-left" aria-hidden="true">
    <div class="lp-noise"></div>
    <div class="lp-rules"><span></span><span></span><span></span></div>
    <div class="lp-left-body">
      <div class="lp-masthead">
        <div class="lp-rule-top"></div>
        <div class="lp-nameplate"><?= h($siteTitle) ?></div>
        <div class="lp-rule-bot"></div>
      </div>
    </div>
  </div>

  <div class="lp-right">
    <div class="lp-form-wrap">
      <div class="lp-form-header">
        <h1 class="lp-form-title">Reset password</h1>
        <p class="lp-form-sub">Enter your email and we'll send a reset link</p>
      </div>

      <?php if (!empty($success)): ?>
        <div class="lp-error" style="background:rgba(5,150,105,.08);border-color:#059669;color:#065f46;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
          <?= h($success) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="lp-error">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <?= h($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="/admin/forgot-password" class="lp-form" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= h($csrfToken) ?>">
        <div class="lp-field">
          <label for="fpEmail" class="lp-label">Email address</label>
          <input id="fpEmail" name="email" type="email" required autocomplete="email"
                 placeholder="you@example.com" class="lp-input" autofocus>
        </div>
        <button type="submit" class="lp-submit">
          Send reset link
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </form>

      <div class="lp-foot">
        <a href="/admin/login" class="lp-back-link">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Back to login
        </a>
      </div>
    </div>
  </div>
</div>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';
