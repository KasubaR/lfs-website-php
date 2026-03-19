<?php
/**
 * PAGINATION PARTIAL — partials/pagination.php
 *
 * Required variables:
 *   $currentPage      int     — 1-based current page
 *   $totalPages       int     — total number of pages
 *   $baseUrl          string  — URL prefix, e.g. "/gallery?page="
 *                               The partial appends the page number directly.
 * Optional:
 *   $paginationLabel  string  — aria-label for the <nav> (default: "Pagination")
 *
 * Usage:
 *   <?php
 *     $currentPage = $page;
 *     $totalPages  = $pages;
 *     $baseUrl     = '/gallery?page=';
 *     require __DIR__ . '/../partials/pagination.php';
 *   ?>
 */

$_label = $paginationLabel ?? 'Pagination';
$_cur   = (int) ($currentPage ?? 1);
$_total = (int) ($totalPages  ?? 1);
if ($_cur   < 1) $_cur   = 1;
if ($_total < 1) $_total = 1;

/**
 * Build the window of visible page numbers.
 * Always shows: first page, last page, current ±2.
 * Fills gaps with null (rendered as …).
 */
$_pages = [];
if ($_total <= 7) {
    for ($_p = 1; $_p <= $_total; $_p++) {
        $_pages[] = $_p;
    }
} else {
    $_window = array_unique(array_merge(
        [1, $_total],
        range(max(1, $_cur - 2), min($_total, $_cur + 2))
    ));
    sort($_window);
    foreach ($_window as $_i => $_val) {
        if ($_i > 0 && $_val - $_window[$_i - 1] > 1) {
            $_pages[] = null; // ellipsis
        }
        $_pages[] = $_val;
    }
}
?>

<?php if ($_total > 1): ?>
<nav class="lfs-pagination" aria-label="<?= htmlspecialchars($_label) ?>">

  <?php /* Prev */ ?>
  <?php if ($_cur > 1): ?>
    <a href="<?= htmlspecialchars($baseUrl) ?><?= $_cur - 1 ?>" class="lfs-pagination__btn" aria-label="Previous page">
      <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </a>
  <?php else: ?>
    <span class="lfs-pagination__btn lfs-pagination__btn--disabled" aria-disabled="true" aria-label="Previous page">
      <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </span>
  <?php endif; ?>

  <?php /* Page numbers */ ?>
  <?php foreach ($_pages as $pg): ?>
    <?php if ($pg === null): ?>
      <span class="lfs-pagination__ellipsis" aria-hidden="true">&hellip;</span>
    <?php elseif ($pg === $_cur): ?>
      <span class="lfs-pagination__page lfs-pagination__page--active" aria-current="page" aria-label="Page <?= $pg ?>"><?= $pg ?></span>
    <?php else: ?>
      <a href="<?= htmlspecialchars($baseUrl) ?><?= $pg ?>" class="lfs-pagination__page" aria-label="Page <?= $pg ?>"><?= $pg ?></a>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php /* Next */ ?>
  <?php if ($_cur < $_total): ?>
    <a href="<?= htmlspecialchars($baseUrl) ?><?= $_cur + 1 ?>" class="lfs-pagination__btn" aria-label="Next page">
      <i class="fas fa-chevron-right" aria-hidden="true"></i>
    </a>
  <?php else: ?>
    <span class="lfs-pagination__btn lfs-pagination__btn--disabled" aria-disabled="true" aria-label="Next page">
      <i class="fas fa-chevron-right" aria-hidden="true"></i>
    </span>
  <?php endif; ?>

</nav>
<?php endif; ?>
