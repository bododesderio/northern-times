<?php
/**
 * CONTACT US PAGE — First-class major page (Nocturnal Prestige editorial design)
 * Route: /contact (GET + POST)
 */
$siteName = function_exists('site_name') ? site_name() : 'Northern Times';
$csrf = \App\Services\Csrf::token();
$formError   = $formError   ?? '';
$formSuccess = $formSuccess ?? false;
$formData    = $formData    ?? ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$hostDomain  = parse_url(\app_url('/'), PHP_URL_HOST) ?: 'northerntimes.news';
?>

<div class="np-page-wrap">

  <!-- HERO -->
  <section class="np-contact-hero">
    <div class="np-contact-hero-inner">
      <span class="np-about-label">GET IN TOUCH</span>
      <h1 class="np-about-headline">Contact Us</h1>
      <p class="np-contact-desc">Have a news tip, feedback, or inquiry? Reach out to our team. We read every message and respond within 48 hours.</p>
    </div>
  </section>

  <!-- MAIN CONTENT (7/5 grid) -->
  <section class="np-about-section">
    <div class="np-about-inner">
      <div class="np-contact-grid">

        <!-- Left: Contact form -->
        <div class="np-tip-form-wrap">
          <div class="np-tip-form">
            <div class="np-tip-form-header">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--np-accent)" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="m22 6-10 7L2 6"/></svg>
              <h2 class="np-tip-form-title">Send a Secure Tip</h2>
            </div>
            <p class="np-tip-form-desc">Your identity is protected. We never reveal sources without explicit consent.</p>

            <?php if ($formSuccess): ?>
              <div class="np-tip-msg np-tip-msg--success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                Thank you! Your message has been sent successfully. We will get back to you within 48 hours.
              </div>
            <?php else: ?>

              <?php if ($formError): ?>
                <div class="np-tip-msg np-tip-msg--error"><?= h($formError) ?></div>
              <?php endif; ?>

              <form method="POST" action="/contact" novalidate>
                <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

                <div class="np-tip-field">
                  <label for="contact-name" class="np-tip-label">Name</label>
                  <input type="text" id="contact-name" name="name" class="np-tip-input" placeholder="Your full name" value="<?= h($formData['name']) ?>" required autocomplete="name">
                </div>

                <div class="np-tip-field">
                  <label for="contact-email" class="np-tip-label">Email</label>
                  <input type="email" id="contact-email" name="email" class="np-tip-input" placeholder="your@email.com" value="<?= h($formData['email']) ?>" required autocomplete="email">
                </div>

                <div class="np-tip-field">
                  <label for="contact-subject" class="np-tip-label">Subject</label>
                  <input type="text" id="contact-subject" name="subject" class="np-tip-input" placeholder="What is this regarding?" value="<?= h($formData['subject']) ?>" required>
                </div>

                <div class="np-tip-field">
                  <label for="contact-message" class="np-tip-label">Message</label>
                  <textarea id="contact-message" name="message" class="np-tip-textarea" rows="6" placeholder="Share your tip, feedback, or inquiry..." required><?= h($formData['message']) ?></textarea>
                </div>

                <button type="submit" class="np-tip-submit">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  Send Message
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right: Departmental contacts -->
        <div class="np-dept-wrap">
          <h2 class="np-dept-title">Departmental Contacts</h2>
          <p class="np-dept-intro">For specific inquiries, reach out directly to the relevant department.</p>

          <div class="np-dept-contacts">
            <div class="np-dept-item">
              <div class="np-dept-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--np-accent)" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
              </div>
              <div class="np-dept-info">
                <h3 class="np-dept-name">Newsroom</h3>
                <p class="np-dept-desc">Breaking news tips, story leads, and editorial feedback.</p>
                <a href="mailto:newsroom@<?= h($hostDomain) ?>" class="np-dept-email">newsroom@<?= h($hostDomain) ?></a>
              </div>
            </div>

            <div class="np-dept-item">
              <div class="np-dept-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--np-accent)" stroke-width="2" stroke-linecap="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
              </div>
              <div class="np-dept-info">
                <h3 class="np-dept-name">Advertising</h3>
                <p class="np-dept-desc">Partnership opportunities, sponsored content, and display advertising.</p>
                <a href="mailto:ads@<?= h($hostDomain) ?>" class="np-dept-email">ads@<?= h($hostDomain) ?></a>
              </div>
            </div>

            <div class="np-dept-item">
              <div class="np-dept-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--np-accent)" stroke-width="2" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              </div>
              <div class="np-dept-info">
                <h3 class="np-dept-name">Press Inquiries</h3>
                <p class="np-dept-desc">Media requests, interviews, and press accreditation.</p>
                <a href="mailto:press@<?= h($hostDomain) ?>" class="np-dept-email">press@<?= h($hostDomain) ?></a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

</div>
