<?php
$status  = $status ?? 'error';
$message = $message ?? '';
$email   = $email ?? '';
$token   = $token ?? '';
?>

<section class="home" style="max-width:560px; margin:60px auto; padding:0 20px;">
  <div class="card" style="padding:40px; text-align:center;">

    <?php if ($status === 'confirm'): ?>
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5" style="margin-bottom:16px;">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
      </svg>
      <h2 style="margin:0 0 12px; font-size:24px;">Unsubscribe?</h2>
      <p style="color:#666; margin-bottom:24px;">
        Are you sure you want to unsubscribe <strong><?= h($email) ?></strong> from our newsletter?
      </p>
      <form method="POST" action="/unsubscribe?token=<?= h(urlencode($token)) ?>">
        <button type="submit" class="btn" style="background:#dc3545; color:#fff; padding:12px 32px; border:none; border-radius:8px; font-size:15px; cursor:pointer;">
          Yes, Unsubscribe Me
        </button>
      </form>
      <p style="margin-top:16px; font-size:13px; color:#999;">
        <a href="/" style="color:#999;">Never mind, take me home</a>
      </p>

    <?php elseif ($status === 'success'): ?>
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="1.5" style="margin-bottom:16px;">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
      <h2 style="margin:0 0 12px; font-size:24px;">Unsubscribed</h2>
      <p style="color:#666; margin-bottom:24px;"><?= h($message) ?></p>
      <a href="/" class="btn light">Go to Homepage</a>

    <?php elseif ($status === 'already'): ?>
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1.5" style="margin-bottom:16px;">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <h2 style="margin:0 0 12px; font-size:24px;">Already Unsubscribed</h2>
      <p style="color:#666; margin-bottom:24px;"><?= h($message) ?></p>
      <a href="/" class="btn light">Go to Homepage</a>

    <?php else: ?>
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="1.5" style="margin-bottom:16px;">
        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
      </svg>
      <h2 style="margin:0 0 12px; font-size:24px;">Oops</h2>
      <p style="color:#666; margin-bottom:24px;"><?= h($message) ?></p>
      <a href="/" class="btn light">Go to Homepage</a>
    <?php endif; ?>
  </div>
</section>