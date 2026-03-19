<?php /* LFS — Lusaka Fitness Squad — pages/contact-us.php */ ?>

<!-- ══════════════════════════════════════════════
     1. HERO
     ══════════════════════════════════════════════ -->
<section class="py-20 px-6 md:px-16 bg-black text-white">
  <div class="max-w-4xl" data-reveal>
    <h1 class="font-['Bebas_Neue'] text-5xl md:text-7xl leading-tight mt-4">Contact Us</h1>
    <p class="mt-6 text-white/70 text-lg max-w-2xl">
      Reach out to us or contact the captain of the satellite closest to you.
      We'd love to welcome you into the LFS family.
    </p>
  </div>
</section>


<!-- ══════════════════════════════════════════════
     2. FAQ
     ══════════════════════════════════════════════ -->
<section id="faq" class="py-20 px-6 md:px-16 bg-lfs-off-white">
  <div class="grid md:grid-cols-2 gap-12 lg:gap-16 items-start max-w-6xl">
    <!-- Left: FAQ content -->
    <div>
      <h2 class="font-['Bebas_Neue'] text-5xl md:text-6xl" data-reveal>FAQ</h2>
      <p class="mt-4 mb-10" style="color:var(--green)" data-reveal>
        Frequently asked questions about LFS — membership, runs, events, and more.
      </p>

      <?php if (!empty($faqs)): ?>
      <div class="space-y-3" role="list">
        <?php foreach ($faqs as $faq): ?>
        <details class="faq-item group" data-reveal
                 id="faq-<?= htmlspecialchars(
                     rtrim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($faq['question'])), '-'),
                     ENT_QUOTES, 'UTF-8'
                 ) ?>">
          <summary class="faq-item__question">
            <?= htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8') ?>
            <i class="fas fa-chevron-down faq-item__icon" aria-hidden="true"></i>
          </summary>
          <div class="faq-item__answer">
            <?= nl2br(htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8')) ?>
          </div>
        </details>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="text-black/50" data-reveal>No FAQs at the moment — check back soon.</p>
      <?php endif; ?>
    </div>

    <!-- Right: Contact image -->
    <div class="relative order-first md:order-none contact-page-image-wrap" data-reveal="right">
      <div class="aspect-[4/5] md:aspect-square rounded-lg overflow-hidden shadow-xl contact-page-image">
        <img
          src="/images/contact.jpg"
          alt="LFS runners together at a group run"
          class="w-full h-full object-cover"
          loading="lazy">
      </div>
    </div>
  </div>
</section>


<!-- ══════════════════════════════════════════════
     3. CONTACT INFO + FORM
     ══════════════════════════════════════════════ -->
<section id="contact" class="py-20 px-6 md:px-16 grid md:grid-cols-2 gap-12 bg-black text-white">

  <!-- Contact info -->
  <div data-reveal="left">
    <h2 class="font-['Bebas_Neue'] text-5xl md:text-6xl text-white">Ready to<br>Join the Squad?</h2>
    <p class="mt-4 text-white/70">
      Whether you have questions about membership, events, or your nearest satellite —
      we're here to help.
    </p>

    <div class="mt-8 space-y-5">
      <div class="contact-row">
        <div class="contact-row__icon"><i class="fas fa-envelope" aria-hidden="true"></i></div>
        <div>
          <div class="contact-row__label">Email</div>
          <div class="contact-row__value"><a href="mailto:info@lfszambia.run">info@lfszambia.run</a></div>
        </div>
      </div>
      <div class="contact-row">
        <div class="contact-row__icon"><i class="fas fa-map-pin" aria-hidden="true"></i></div>
        <div>
          <div class="contact-row__label">Address</div>
          <div class="contact-row__value">CV-6 COMESA Village, Lusaka Showgrounds, Lusaka, Zambia</div>
        </div>
      </div>
      <div class="contact-row">
        <div class="contact-row__icon" style="color:var(--flag-red);background:rgba(192,57,43,0.1)">
          <i class="fas fa-phone-alt" aria-hidden="true"></i>
        </div>
        <div>
          <div class="contact-row__label">President — Katai Chola</div>
          <div class="contact-row__value">
            <a href="tel:+260966755326">+260 966 755 326</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Contact form -->
  <!-- data-native-submit: tells main.js initContactForm() NOT to intercept this form.
       This form POSTs natively to /contact (src/routes/contact.php).
       Remove only if you also update initContactForm() in public/js/main.js. -->
  <div class="p-8 rounded-lg border lfs-form lfs-form--dark"
       style="background:var(--black-soft);border-color:rgba(255,255,255,0.1)"
       data-reveal="right"
       data-native-submit>

    <?php if (!empty($submitted)): ?>
    <!-- ── Success banner ── -->
    <div class="mb-6 p-4 rounded-lg lfs-form__success"
         style="background:rgba(74,124,89,0.2);border:1px solid var(--green);color:var(--green-bright)"
         role="alert">
      <i class="fas fa-check-circle mr-2" aria-hidden="true"></i>
      Your message has been sent. We'll be in touch soon!
    </div>
    <?php endif; ?>

    <?php if (!empty($errors['_general'])): ?>
    <!-- ── General error banner ── -->
    <div class="mb-6 p-4 rounded-lg"
         style="background:rgba(192,57,43,0.15);border:1px solid var(--flag-red);color:#e88;"
         role="alert">
      <i class="fas fa-exclamation-circle mr-2" aria-hidden="true"></i>
      <?= htmlspecialchars($errors['_general'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <div class="font-['Bebas_Neue'] text-4xl md:text-5xl mb-6 text-white">Send Us a Message</div>

    <form action="/contact" method="post" class="space-y-4" novalidate>
      <!-- CSRF hidden field -->
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="grid grid-cols-2 gap-4">
        <div class="form-group<?= isset($errors['firstName']) ? ' form-group--error' : '' ?>">
          <label for="firstName">First Name</label>
          <input id="firstName" type="text" name="firstName"
                 placeholder="e.g. Katai"
                 autocomplete="given-name"
                 value="<?= htmlspecialchars($old['firstName'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 required>
          <?php if (!empty($errors['firstName'])): ?>
          <p class="form-group__error" role="alert">
            <?= htmlspecialchars($errors['firstName'], ENT_QUOTES, 'UTF-8') ?>
          </p>
          <?php endif; ?>
        </div>

        <div class="form-group<?= isset($errors['lastName']) ? ' form-group--error' : '' ?>">
          <label for="lastName">Last Name</label>
          <input id="lastName" type="text" name="lastName"
                 placeholder="e.g. Chola"
                 autocomplete="family-name"
                 value="<?= htmlspecialchars($old['lastName'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 required>
          <?php if (!empty($errors['lastName'])): ?>
          <p class="form-group__error" role="alert">
            <?= htmlspecialchars($errors['lastName'], ENT_QUOTES, 'UTF-8') ?>
          </p>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-group<?= isset($errors['email']) ? ' form-group--error' : '' ?>">
        <label for="email">Email</label>
        <input id="email" type="email" name="email"
               placeholder="you@example.com"
               autocomplete="email"
               value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required>
        <?php if (!empty($errors['email'])): ?>
        <p class="form-group__error" role="alert">
          <?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php endif; ?>
      </div>

      <div class="form-group<?= isset($errors['phone']) ? ' form-group--error' : '' ?>">
        <label for="phone">Phone</label>
        <input id="phone" type="tel" name="phone"
               placeholder="+260 9XX XXX XXX"
               autocomplete="tel"
               value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($errors['phone'])): ?>
        <p class="form-group__error" role="alert">
          <?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php endif; ?>
      </div>

      <div class="form-group<?= isset($errors['satellite']) ? ' form-group--error' : '' ?>">
        <label for="satellite">Nearest Satellite</label>
        <?php
            $oldSatellite = $old['satellite'] ?? '';
            $satelliteOptions = [
                ''              => 'Select satellite…',
                'arcades'       => 'Arcades',
                'avondale'      => 'Avondale',
                'chamba-valley' => 'Chamba Valley',
                'woodies'       => 'Woodies',
                'north-side'    => 'North Side',
                'south-side'    => 'South Side',
            ];
        ?>
        <select id="satellite" name="satellite">
          <?php foreach ($satelliteOptions as $val => $label): ?>
          <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"
            <?= $oldSatellite === $val ? 'selected' : '' ?>>
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['satellite'])): ?>
        <p class="form-group__error" role="alert">
          <?= htmlspecialchars($errors['satellite'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php endif; ?>
      </div>

      <div class="form-group<?= isset($errors['message']) ? ' form-group--error' : '' ?>">
        <label for="message">Message</label>
        <textarea id="message" name="message" rows="4"
                  placeholder="Tell us about yourself…"
                  maxlength="5000"
                  required><?= htmlspecialchars($old['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        <p class="form-group__counter" id="contactMessageCharCount" aria-live="polite">0 / 5000</p>
        <?php if (!empty($errors['message'])): ?>
        <p class="form-group__error" role="alert">
          <?= htmlspecialchars($errors['message'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php endif; ?>
      </div>

      <button class="btn btn-primary w-full justify-center mt-2 btn-submit"
              type="submit"
              aria-label="Send your message to LFS">
        <i class="fas fa-paper-plane mr-2" aria-hidden="true"></i> Send Message
      </button>
    </form>
  </div>

</section>


<!-- ══════════════════════════════════════════════
     4. SATELLITES
     ══════════════════════════════════════════════ -->
<?php
$sectionClass = 'bg-lfs-off-white';
require __DIR__ . '/../partials/satellites.php';
?>
