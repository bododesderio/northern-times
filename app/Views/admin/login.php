<?php
$pageTitle    = 'Login';
$activeNav    = '';
$hideAdminNav = true;
$csrfToken    = $csrf ?? \App\Services\Csrf::token();
$siteTitle    = function_exists('site_name') ? site_name() : ($_ENV['APP_NAME'] ?? 'Northern Times');

// Load active quotes for the rotating panel
$loginQuotes = [];
if (class_exists('\App\Models\LoginQuote')) {
    $loginQuotes = \App\Models\LoginQuote::allActive();
}
// Fallback so the panel is never empty
if (empty($loginQuotes)) {
    $loginQuotes = [
        ['quote' => 'The press is the best instrument for enlightening the mind of man.', 'author' => 'Thomas Jefferson'],
    ];
}

ob_start();
?>

<div class="lp-wrap">

  <!-- ── Left editorial panel ──────────────────────────────── -->
  <div class="lp-left" aria-hidden="true">
    <div class="lp-noise"></div>
    <div class="lp-rules"><span></span><span></span><span></span></div>

    <div class="lp-left-body">

      <div class="lp-masthead">
        <div class="lp-rule-top"></div>
        <div class="lp-nameplate"><?= h($siteTitle) ?></div>
        <div class="lp-rule-bot"></div>
        <div class="lp-edition">
          <span id="lpDate"></span>
          <span class="lp-edition-sep">·</span>
          <span>Admin Edition</span>
        </div>
      </div>

      <div class="lp-quote-block">
        <p class="lp-quote" id="lpQuote"></p>
        <cite class="lp-cite" id="lpCite"></cite>
      </div>

      <div class="lp-grid-preview" aria-hidden="true">
        <div class="lp-preview-col">
          <div class="lp-pline w80"></div><div class="lp-pline w60"></div>
          <div class="lp-pline w90"></div><div class="lp-pline w50"></div>
          <div class="lp-pline w70"></div><div class="lp-pline w40"></div>
          <div class="lp-pline w80"></div><div class="lp-pline w55"></div>
        </div>
        <div class="lp-preview-col lp-preview-img"></div>
        <div class="lp-preview-col">
          <div class="lp-pline w70"></div><div class="lp-pline w90"></div>
          <div class="lp-pline w45"></div><div class="lp-pline w80"></div>
          <div class="lp-pline w60"></div><div class="lp-pline w75"></div>
          <div class="lp-pline w50"></div><div class="lp-pline w65"></div>
        </div>
      </div>

    </div>

    <div class="lp-left-foot">Powered by Northern Times CMS</div>
  </div>

  <!-- ── Right form panel ──────────────────────────────────── -->
  <div class="lp-right">
    <div class="lp-form-wrap">

      <div class="lp-mobile-brand"><?= h($siteTitle) ?></div>

      <div class="lp-form-header">
        <h1 class="lp-form-title">Welcome back</h1>
        <p class="lp-form-sub">Sign in to your newsroom dashboard</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="lp-error">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= h($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="/admin/login" class="lp-form" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= h($csrfToken) ?>">

        <div class="lp-field">
          <label class="lp-label" for="lpEmail">Email address</label>
          <div class="lp-input-wrap">
            <svg class="lp-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            <input id="lpEmail" name="email" type="email" required autocomplete="email"
                   placeholder="editor@example.com" class="lp-input"
                   value="<?= h($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="lp-field">
          <label class="lp-label" for="lpPassword">Password</label>
          <div class="lp-input-wrap">
            <svg class="lp-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input id="lpPassword" name="password" type="password" required
                   autocomplete="current-password" placeholder="••••••••" class="lp-input">
            <button type="button" class="lp-pw-toggle" onclick="lpTogglePw()" tabindex="-1" aria-label="Show password">
              <svg id="lpEyeOpen"  width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="lpEyeSlash" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="lp-submit">
          Sign in to Newsroom
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </form>

      <div class="lp-foot">
        <a href="/" class="lp-back-link">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Back to front page
        </a>
      </div>

    </div>
  </div>

</div>

<script>
// ── Date in masthead ─────────────────────────────────────────
(function () {
  var el = document.getElementById('lpDate');
  if (!el) return;
  var d = new Date();
  var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  el.textContent = days[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
})();

// ── Rotating quotes (fade out → swap → fade in) ──────────────
(function () {
  var quotes = <?= json_encode(array_values($loginQuotes), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  if (!quotes.length) return;

  var qEl  = document.getElementById('lpQuote');
  var cEl  = document.getElementById('lpCite');
  if (!qEl || !cEl) return;

  // Pick a random starting index
  var idx = Math.floor(Math.random() * quotes.length);

  function show(i) {
    qEl.textContent = '\u201c' + quotes[i].quote + '\u201d';
    cEl.textContent = quotes[i].author ? '\u2014 ' + quotes[i].author : '';
  }

  function next() {
    // Fade out
    qEl.style.opacity = '0';
    cEl.style.opacity = '0';
    setTimeout(function () {
      idx = (idx + 1) % quotes.length;
      show(idx);
      // Fade in
      qEl.style.opacity = '1';
      cEl.style.opacity = '1';
    }, 520); // wait for CSS transition (.5s) to finish
  }

  // Show first quote immediately
  show(idx);

  // Switch every 6 seconds
  setInterval(next, 6000);
})();

// ── Password toggle ──────────────────────────────────────────
function lpTogglePw() {
  var inp   = document.getElementById('lpPassword');
  var open  = document.getElementById('lpEyeOpen');
  var slash = document.getElementById('lpEyeSlash');
  if (!inp) return;
  var show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  if (open)  open.style.display  = show ? 'none' : '';
  if (slash) slash.style.display = show ? '' : 'none';
}
</script>

<?php
$slot = ob_get_clean();
require __DIR__ . '/layout.php';