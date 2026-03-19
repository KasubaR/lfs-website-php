<?php
/**
 * admin/views/faqs/form.php — FAQ create/edit form
 *
 * Variables from faqs.php:
 *   $faq       array   question, answer, category, sort_order (and id on edit)
 *   $errors    array   field errors
 *   $pageTitle string
 *   $activePage string
 *   $counts    array
 */

$faq         = $faq         ?? ['question' => '', 'answer' => '', 'category' => '', 'sort_order' => 0];
$errors      = $errors      ?? [];
$csrfToken   = $csrfToken   ?? '';
$isEdit      = isset($faq['id']);
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin/dashboard'],
    ['label' => 'FAQ', 'url' => '/admin/faqs'],
    ['label' => $isEdit ? 'Edit' : 'Create'],
];
$formAction  = $isEdit ? '/admin/faqs/' . (int)$faq['id'] . '/edit' : '/admin/faqs/create';
?>

<div class="admin-page-header">
  <a href="/admin/faqs" class="admin-page-header__back">
    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to FAQs
  </a>
  <h2 class="admin-page-header__heading"><?= $isEdit ? 'Edit FAQ' : 'Add FAQ' ?></h2>
</div>

<div class="admin-form-card">
  <form method="post" action="<?= htmlspecialchars($formAction) ?>">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="form-group<?= !empty($errors['question']) ? ' form-group--error' : '' ?>">
      <label for="question" class="admin-label">Question</label>
      <input type="text" id="question" name="question" required
             value="<?= htmlspecialchars($faq['question'] ?? '') ?>"
             class="admin-input">
      <?php if (!empty($errors['question'])): ?>
      <p class="form-group__error" role="alert"><?= htmlspecialchars($errors['question']) ?></p>
      <?php endif; ?>
    </div>

    <div class="form-group<?= !empty($errors['answer']) ? ' form-group--error' : '' ?>">
      <label for="answer" class="admin-label">Answer</label>
      <textarea id="answer" name="answer" rows="6" required class="admin-input"><?= htmlspecialchars($faq['answer'] ?? '') ?></textarea>
      <?php if (!empty($errors['answer'])): ?>
      <p class="form-group__error" role="alert"><?= htmlspecialchars($errors['answer']) ?></p>
      <?php endif; ?>
    </div>

    <div class="form-group<?= !empty($errors['category']) ? ' form-group--error' : '' ?>">
      <label for="category" class="admin-label">Category (optional)</label>
      <input type="text" id="category" name="category"
             value="<?= htmlspecialchars($faq['category'] ?? '') ?>"
             placeholder="e.g. General, Membership"
             class="admin-input">
      <?php if (!empty($errors['category'])): ?>
      <p class="form-group__error" role="alert"><?= htmlspecialchars($errors['category']) ?></p>
      <?php endif; ?>
    </div>

    <div class="form-group<?= !empty($errors['sort_order']) ? ' form-group--error' : '' ?>">
      <label for="sort_order" class="admin-label"><?= $isEdit ? 'Sort order (1 = first; 0 = move to last)' : 'Sort order (1 = first; pre-filled number = append at end)' ?></label>
      <input type="number" id="sort_order" name="sort_order" min="0" step="1"
             value="<?= (int)($faq['sort_order'] ?? 0) ?>"
             class="admin-input admin-input--number">
      <?php if (!empty($errors['sort_order'])): ?>
      <p class="form-group__error" role="alert"><?= htmlspecialchars($errors['sort_order']) ?></p>
      <?php endif; ?>
    </div>

    <button type="submit" class="admin-btn admin-btn--primary"><?= $isEdit ? 'Save changes' : 'Create FAQ' ?></button>
    <a href="/admin/faqs" class="admin-btn admin-btn--ghost">Cancel</a>
  </form>
</div>
