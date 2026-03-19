<?php
/**
 * admin/views/products/list.php — Products index
 *
 * Variables from controller:
 *   $products[]          { id, name, slug, category, price,
 *                          totalStock, featured, isActive }
 *   $PRODUCT_CATEGORIES  array
 *   $filters             array { category, search }
 *   $total               int
 *   $pages               int
 *   $currentPage         int
 *   $formatPrice         callable
 *   $csrfToken           string
 */

$pageTitle   = $pageTitle  ?? 'Products';
$activePage  = 'products';
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Products'],
];

$list               = is_array($products ?? null) ? $products : [];
$PRODUCT_CATEGORIES = $PRODUCT_CATEGORIES ?? [];
$filters            = $filters ?? [];
$formatPrice        = $formatPrice ?? fn ($v) => 'ZMW ' . number_format((float)$v, 2);

$activeCount = count(array_filter($list, fn ($p) => !empty($p['isActive'])));
?>

<!-- Stats row -->
<section class="stats-grid" aria-label="Product stats">
  <article class="stat-card stat-card--green">
    <div class="stat-card__icon"><i class="fas fa-shirt"></i></div>
    <p class="stat-card__label">Total Products</p>
    <p class="stat-card__value"><?= count($list) ?></p>
  </article>
  <article class="stat-card stat-card--orange">
    <div class="stat-card__icon"><i class="fas fa-circle-check"></i></div>
    <p class="stat-card__label">Active</p>
    <p class="stat-card__value"><?= $activeCount ?></p>
  </article>
</section>

<!-- Toolbar -->
<div class="admin-toolbar">
  <form method="GET" action="/admin/products" class="admin-toolbar__filters">
    <select name="category" class="admin-select">
      <option value="">All Categories</option>
      <?php foreach ($PRODUCT_CATEGORIES as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>"
          <?= ($filters['category'] ?? '') === $c ? 'selected' : '' ?>>
          <?= htmlspecialchars(str_replace('-', ' ', $c)) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="search"
           value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
           class="admin-input"
           placeholder="Search name or description…" />
    <button type="submit" class="admin-btn admin-btn--primary">
      <i class="fas fa-filter" aria-hidden="true"></i> Apply
    </button>
    <a href="/admin/products" class="admin-btn admin-btn--ghost">
      <i class="fas fa-xmark" aria-hidden="true"></i> Clear
    </a>
  </form>

  <a href="/admin/products/create" class="admin-btn admin-btn--primary">
    <i class="fas fa-plus" aria-hidden="true"></i> New Product
  </a>
</div>

<!-- Products table -->
<?php if (!empty($list)): ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Featured</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $p): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($p['name']) ?></strong>
            <div class="admin-table__sub">
              <span>Slug: <code><?= htmlspecialchars($p['slug'] ?? '') ?></code></span>
            </div>
          </td>
          <td><?= htmlspecialchars(str_replace('-', ' ', $p['category'] ?? '')) ?></td>
          <td><?= htmlspecialchars(($formatPrice)($p['price'] ?? 0)) ?></td>
          <td>
            <?php if (empty($p['isActive'])): ?>
              <span class="badge badge--red">Inactive</span>
            <?php elseif (($p['totalStock'] ?? -1) === 0): ?>
              <span class="badge badge--red">Out</span>
            <?php elseif (isset($p['totalStock']) && $p['totalStock'] <= 5): ?>
              <span class="badge badge--orange">Low (<?= (int)$p['totalStock'] ?>)</span>
            <?php else: ?>
              <span class="badge badge--green"><?= (int)($p['totalStock'] ?? 0) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($p['featured'])): ?>
              <span class="badge badge--gold"><i class="fas fa-star" aria-hidden="true"></i> Yes</span>
            <?php else: ?>
              <span class="badge">No</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($p['isActive'])): ?>
              <span class="status-pill status-pill--green">Active</span>
            <?php else: ?>
              <span class="status-pill status-pill--muted">Hidden</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="/shop/product/<?= htmlspecialchars($p['slug'] ?? '') ?>"
               class="admin-btn admin-btn--ghost admin-btn--sm"
               target="_blank" rel="noopener">
              View
            </a>
            <a href="/admin/products/<?= htmlspecialchars($p['id']) ?>/edit"
               class="admin-btn admin-btn--primary admin-btn--sm">
              Edit
            </a>
            <form method="POST"
                  action="/admin/products/<?= htmlspecialchars($p['id']) ?>/delete"
                  class="admin-inline-form"
                  onsubmit="return confirm('Hide this product from the shop?');">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
              <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
                Delete
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>

  <div class="admin-empty">
    <i class="fas fa-shirt admin-empty__icon" aria-hidden="true"></i>
    <p class="admin-empty__title">No products yet</p>
    <p class="admin-empty__body">
      Create your first product to start populating the LFS shop.
    </p>
    <a href="/admin/products/create" class="admin-btn admin-btn--primary">
      <i class="fas fa-plus" aria-hidden="true"></i> New Product
    </a>
  </div>

<?php endif; ?>
