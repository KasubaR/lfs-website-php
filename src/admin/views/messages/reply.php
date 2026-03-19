<?php
/**
 * admin/views/messages/reply.php — Reply-to-contact-message form
 *
 * Variables from messages.php:
 *   $message    array   single message row
 *   $pageTitle  string
 *   $activePage string
 *   $counts     array
 *   $flash      array   ['error' => '...'] on validation failure
 */

$message   = $message   ?? [];
$id        = (string)($message['id'] ?? '');
$csrfToken = $csrfToken ?? '';
$flash     = $flash     ?? [];

$breadcrumbs = [
    ['label' => 'Admin',    'url' => '/admin/dashboard'],
    ['label' => 'Messages', 'url' => '/admin/messages'],
    ['label' => 'Reply'],
];
?>

<section class="message-reply">

<div class="admin-page-header message-reply__header">
  <a href="/admin/messages/<?= urlencode($id) ?>" class="message-reply__back-link">
    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to message
  </a>
  <h2 class="message-reply__title">Reply to <?= htmlspecialchars($message['name'] ?? '') ?></h2>
</div>

<!-- Original message summary -->
<div class="admin-card message-reply__original">
  <h3 class="message-reply__section-title">Original message</h3>
  <div class="message-reply__readonly-grid">
    <div class="admin-form-group">
      <label class="admin-label" for="readonly_email">User email</label>
      <input id="readonly_email"
             class="admin-input"
             type="email"
             readonly
             value="<?= htmlspecialchars((string)($message['email'] ?? '')) ?>">
    </div>
    <div class="admin-form-group">
      <label class="admin-label" for="readonly_received">Received</label>
      <input id="readonly_received"
             class="admin-input"
             type="text"
             readonly
             value="<?= htmlspecialchars((string)($message['created_at'] ?? '')) ?>">
    </div>
  </div>

  <div class="admin-form-group message-reply__body-block">
    <label class="admin-label" for="readonly_original_message">Original message</label>
    <textarea id="readonly_original_message"
              class="admin-input admin-textarea"
              rows="6"
              readonly><?= htmlspecialchars((string)($message['message'] ?? '')) ?></textarea>
  </div>
</div>

<!-- Reply form -->
<div class="admin-card message-reply__form-card">
  <h3 class="message-reply__section-title">Your reply</h3>

  <?php if (!empty($flash['error'])): ?>
    <div class="sys-notif sys-notif--error message-reply__error" role="alert">
      <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
      <span><?= htmlspecialchars($flash['error']) ?></span>
    </div>
  <?php endif; ?>

  <form method="post"
        action="/admin/messages/<?= urlencode($id) ?>/reply"
        class="message-reply__form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="admin-form-group">
      <label for="reply_message" class="admin-label">
        Reply message <span aria-hidden="true" class="message-reply__required">*</span>
      </label>
      <textarea id="reply_message"
                name="reply_message"
                class="admin-input admin-textarea"
                rows="10"
                maxlength="5000"
                required
                placeholder="Type your reply here…"><?= htmlspecialchars($_POST['reply_message'] ?? '') ?></textarea>
      <p class="message-reply__char-count" id="replyCharCount" aria-live="polite">0 / 5000</p>
    </div>

    <p class="message-reply__notice">
      <i class="fas fa-envelope" aria-hidden="true"></i>
      This reply will be emailed to <strong><?= htmlspecialchars($message['email'] ?? '') ?></strong>
      and the message status will be set to <strong>Responded</strong>.
    </p>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fas fa-paper-plane" aria-hidden="true"></i> Send reply
      </button>
      <a href="/admin/messages/<?= urlencode($id) ?>" class="admin-btn admin-btn--secondary">Cancel</a>
    </div>
  </form>
</div>

</section>
