<?php
/**
 * admin/views/messages/show.php — Single contact message view
 *
 * Variables from messages.php:
 *   $message    array   single message row
 *   $replies    array   replies for this message (latest first)
 *   $pageTitle  string
 *   $activePage string
 *   $counts     array
 */

$message     = $message     ?? [];
$replies     = $replies     ?? [];
$id          = (string)($message['id'] ?? '');
$csrfToken   = $csrfToken   ?? '';
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin/dashboard'],
    ['label' => 'Messages', 'url' => '/admin/messages'],
    ['label' => 'View'],
];
?>

<section class="message-view">
<div class="admin-page-header message-view__header">
  <a href="/admin/messages" class="message-view__back-link">
    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to messages
  </a>
  <h2 class="message-view__title">Message from <?= htmlspecialchars($message['name'] ?? '') ?></h2>
</div>

<div class="admin-card message-view__card">
  <dl class="message-view__meta">
    <div class="message-view__meta-item">
      <dt class="message-view__label">From</dt>
      <dd class="message-view__value message-view__value--strong"><?= htmlspecialchars($message['name'] ?? '—') ?></dd>
    </div>
    <div class="message-view__meta-item">
      <dt class="message-view__label">Email</dt>
      <dd class="message-view__value">
        <a class="message-view__link" href="mailto:<?= htmlspecialchars($message['email'] ?? '') ?>"><?= htmlspecialchars($message['email'] ?? '—') ?></a>
      </dd>
    </div>
    <?php if (!empty($message['phone'])): ?>
    <div class="message-view__meta-item">
      <dt class="message-view__label">Phone</dt>
      <dd class="message-view__value">
        <a class="message-view__link" href="tel:<?= htmlspecialchars($message['phone']) ?>"><?= htmlspecialchars($message['phone']) ?></a>
      </dd>
    </div>
    <?php endif; ?>
    <?php if (!empty($message['satellite'])): ?>
    <div class="message-view__meta-item">
      <dt class="message-view__label">Satellite</dt>
      <dd class="message-view__value"><?= htmlspecialchars($message['satellite']) ?></dd>
    </div>
    <?php endif; ?>
    <div class="message-view__meta-item">
      <dt class="message-view__label">Received</dt>
      <dd class="message-view__value"><?= htmlspecialchars($message['created_at'] ?? '—') ?></dd>
    </div>
    <div class="message-view__meta-item">
      <dt class="message-view__label">Status</dt>
      <dd class="message-view__value">
        <span class="message-view__status-pill"><?= htmlspecialchars($message['status'] ?? '—') ?></span>
      </dd>
    </div>
  </dl>

  <div class="message-view__body-block">
    <p class="message-view__label">Message</p>
    <div class="message-view__body"><?= htmlspecialchars($message['message'] ?? '—') ?></div>
  </div>
</div>

<!-- Reply -->
<div class="admin-card message-view__card">
  <h3 class="message-view__section-title">Reply to this message</h3>
  <p class="message-view__reply-note">
    Compose and send a reply directly to <?= htmlspecialchars($message['email'] ?? '') ?>.
    The status will be updated to <strong>Responded</strong> automatically.
  </p>
  <a href="/admin/messages/<?= urlencode($id) ?>/reply" class="admin-btn admin-btn--primary">
    <i class="fas fa-reply" aria-hidden="true"></i> Reply
  </a>
</div>

<?php if (!empty($replies)): ?>
<div class="admin-card message-view__card">
  <h3 class="message-view__section-title">Reply history</h3>
  <div class="message-view__history">
    <?php foreach ($replies as $reply): ?>
    <article class="message-view__history-item">
      <p class="message-view__history-time">
        <?= htmlspecialchars((string)($reply['created_at'] ?? '')) ?>
      </p>
      <div class="message-view__history-body">
        <?= nl2br(htmlspecialchars((string)($reply['reply_message'] ?? ''))) ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Status update -->
<div class="admin-card message-view__card">
  <h3 class="message-view__section-title">Update status</h3>
  <form method="post" action="/admin/messages/<?= urlencode($id) ?>/status" class="message-view__status-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
    <select name="status" class="admin-input message-view__status-select">
      <?php foreach (\ContactMessage::STATUS as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= ($message['status'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="admin-btn admin-btn--primary">Save status</button>
  </form>
</div> 

<!-- Delete -->
<div class="message-view__danger-zone">
  <form method="post" action="/admin/messages/<?= urlencode($id) ?>/delete">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
    <button type="submit" class="admin-btn admin-btn--danger">Delete message</button>
  </form>
</div>
</section>
