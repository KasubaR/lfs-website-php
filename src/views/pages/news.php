<?php
/**
 * LFS — News & Blog Listing Page
 * /views/news.php  (rendered inside main.php layout)
 *
 * Expected variables (from controller):
 *   $posts        array   — paginated array of post objects/arrays
 *   $categories   array   — list of category strings
 *   $currentPage  int     — 1-based current page
 *   $totalPages   int     — total pages
 *   $total        int     — total post count
 *   $featured     array   — first/featured post (may be null)
 *   $activeCategory string — currently filtered category slug
 *   $searchQuery  string  — current search term
 */

$posts          = $posts          ?? [];
$categories     = $categories     ?? ['Club News','Race Results','Event Announcements','Training Tips','Member Stories'];
$currentPage    = $currentPage    ?? 1;
$totalPages     = $totalPages     ?? 1;
$total          = $total          ?? 0;
$featured       = $featured       ?? ($posts[0] ?? null);
$activeCategory = $activeCategory ?? '';
$searchQuery    = $searchQuery    ?? '';

/* ── Category color map ─────────────────────────────────── */
$catColors = [
  'Club News'            => 'green',
  'Race Results'         => 'orange',
  'Event Announcements'  => 'red',
  'Training Tips'        => 'blue',
  'Member Stories'       => 'gold',
];

function catClass(string $cat): string {
  global $catColors;
  return 'blog-badge--' . ($catColors[$cat] ?? 'green');
}

/* ── Helpers ─────────────────────────────────────────────── */
function safeStr($v, string $fallback = ''): string {
  return htmlspecialchars((string)($v ?? $fallback), ENT_QUOTES, 'UTF-8');
}
function postUrl(array $p): string {
  $slug = $p['slug'] ?? $p['id'] ?? 0;
  return defined('BASE_PATH') ? BASE_PATH . '/news/' . $slug : '/news/' . $slug;
}
function fmtDate(string $date): string {
  return date('d M Y', strtotime($date));
}
function excerpt(string $text, int $max = 160): string {
  $plain = strip_tags($text);
  return mb_strlen($plain) > $max ? mb_substr($plain, 0, $max) . '…' : $plain;
}
?>

<!-- ══════════════════════════════════════════════════════
     BLOG HERO (same structure as event detail hero)
     ══════════════════════════════════════════════════════ -->
<div class="blog-hero">
  <div class="blog-hero__bg blog-hero__bg--placeholder">
    <div class="blog-hero__overlay" aria-hidden="true"></div>
  </div>

  <div class="blog-hero__content">
    <nav class="blog-hero-breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?= safeStr(defined('BASE_PATH') ? BASE_PATH : '') ?>/">Home</a></li>
        <li><i class="fas fa-chevron-right" aria-hidden="true"></i></li>
        <li aria-current="page">News</li>
      </ol>
    </nav>

    <div class="blog-hero__inner">
      <div class="blog-hero__text">
        <p class="blog-hero__label">Lusaka Fitness Squad</p>
        <h1 class="blog-hero__title">News &amp; <span class="text-gradient-lfs">Updates</span></h1>
        <p class="blog-hero__sub">Stories from the track, the trail, and the community.</p>

        <form class="blog-search" action="<?= safeStr(defined('BASE_PATH') ? BASE_PATH : '') ?>/news" method="GET" role="search">
          <?php if ($activeCategory): ?>
            <input type="hidden" name="category" value="<?= safeStr($activeCategory) ?>">
          <?php endif; ?>
          <div class="blog-search__wrap">
            <i class="fas fa-search blog-search__icon" aria-hidden="true"></i>
            <input
              type="search"
              name="q"
              class="blog-search__input"
              placeholder="Search posts…"
              value="<?= safeStr($searchQuery) ?>"
              aria-label="Search blog posts"
            >
            <button type="submit" class="blog-search__btn">Search</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     CATEGORY FILTER STRIP
     ══════════════════════════════════════════════════════ -->
<div class="blog-filter-strip">
  <div class="blog-filter-strip__inner">
    <a
      href="<?= safeStr(defined('BASE_PATH') ? BASE_PATH : '') ?>/news<?= $searchQuery ? '?q=' . urlencode($searchQuery) : '' ?>"
      class="blog-filter-chip<?= !$activeCategory ? ' blog-filter-chip--active' : '' ?>"
    >All Posts</a>

    <?php foreach ($categories as $cat): ?>
      <?php
        $slug   = urlencode($cat);
        $href   = (defined('BASE_PATH') ? BASE_PATH : '') . '/news?category=' . $slug . ($searchQuery ? '&q=' . urlencode($searchQuery) : '');
        $active = ($activeCategory === $cat || $activeCategory === $slug);
      ?>
      <a
        href="<?= safeStr($href) ?>"
        class="blog-filter-chip <?= catClass($cat) ?><?= $active ? ' blog-filter-chip--active' : '' ?>"
      ><?= safeStr($cat) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MAIN CONTENT
     ══════════════════════════════════════════════════════ -->
<div class="blog-layout">

  <?php if (empty($posts)): ?>
    <!-- ── Empty State ── -->
    <div class="blog-empty">
      <i class="fas fa-newspaper blog-empty__icon"></i>
      <h2 class="blog-empty__heading">No posts found</h2>
      <p class="blog-empty__desc">
        <?= $searchQuery
            ? 'No results for "' . safeStr($searchQuery) . '". Try a different search.'
            : 'Check back soon — stories from the squad are coming.' ?>
      </p>
      <a href="<?= safeStr(defined('BASE_PATH') ? BASE_PATH : '') ?>/news" class="btn btn-primary mt-6">
        <i class="fas fa-arrow-left"></i> All Posts
      </a>
    </div>

  <?php else: ?>

    <!-- ── FEATURED HERO POST (first post on page 1, no active filter) ── -->
    <?php if ($currentPage === 1 && !$activeCategory && !$searchQuery && $featured): ?>
      <?php $f = $featured; ?>
      <article class="blog-featured">
        <a href="<?= safeStr(postUrl($f)) ?>" class="blog-featured__img-link" tabindex="-1" aria-hidden="true">
          <div class="blog-featured__img-wrap">
            <?php if (!empty($f['image'])): ?>
              <img src="<?= safeStr($f['image']) ?>" alt="<?= safeStr($f['title']) ?>" loading="eager">
            <?php else: ?>
              <div class="blog-featured__img-placeholder">
                <i class="fas fa-running"></i>
              </div>
            <?php endif; ?>
            <span class="blog-badge <?= catClass($f['category'] ?? '') ?>"><?= safeStr($f['category'] ?? 'News') ?></span>
          </div>
        </a>
        <div class="blog-featured__body">
          <div class="blog-featured__meta">
            <span class="blog-meta-date">
              <i class="far fa-calendar-alt" aria-hidden="true"></i>
              <?= fmtDate($f['published_at'] ?? $f['date'] ?? 'now') ?>
            </span>
            <?php if (!empty($f['author'])): ?>
              <span class="blog-meta-sep" aria-hidden="true">·</span>
              <span class="blog-meta-author">
                <i class="far fa-user" aria-hidden="true"></i>
                <?= safeStr($f['author']) ?>
              </span>
            <?php endif; ?>
          </div>
          <h2 class="blog-featured__title">
            <a href="<?= safeStr(postUrl($f)) ?>"><?= safeStr($f['title']) ?></a>
          </h2>
          <p class="blog-featured__excerpt"><?= safeStr(excerpt($f['excerpt'] ?? $f['content'] ?? '', 220)) ?></p>
          <a href="<?= safeStr(postUrl($f)) ?>" class="btn btn-primary blog-featured__cta">
            Read Full Story <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </article>
    <?php endif; ?>

    <!-- ── POSTS GRID ── -->
    <?php
      /* Skip featured post in grid when on page 1 with no filters */
      $gridPosts = $posts;
      if ($currentPage === 1 && !$activeCategory && !$searchQuery && count($gridPosts) > 0) {
        array_shift($gridPosts);
      }
    ?>

    <?php if (!empty($gridPosts)): ?>
    <div class="blog-result-bar">
      <span class="blog-result-bar__count">
        <?= $total ?> post<?= $total !== 1 ? 's' : '' ?>
        <?= $activeCategory ? ' in <strong>' . safeStr($activeCategory) . '</strong>' : '' ?>
        <?= $searchQuery ? ' for "<strong>' . safeStr($searchQuery) . '</strong>"' : '' ?>
      </span>
    </div>

    <div class="blog-grid">
      <?php foreach ($gridPosts as $i => $post): ?>
        <article class="blog-card animate-fadeUp" style="animation-delay:<?= ($i * 0.07) ?>s">
          <!-- Image -->
          <a href="<?= safeStr(postUrl($post)) ?>" class="blog-card__img-link" tabindex="-1" aria-hidden="true">
            <div class="blog-card__img-wrap">
              <?php if (!empty($post['image'])): ?>
                <img src="<?= safeStr($post['image']) ?>" alt="<?= safeStr($post['title']) ?>" loading="lazy">
              <?php else: ?>
                <div class="blog-card__img-placeholder">
                  <i class="fas fa-running" aria-hidden="true"></i>
                </div>
              <?php endif; ?>
              <span class="blog-badge <?= catClass($post['category'] ?? '') ?>">
                <?= safeStr($post['category'] ?? 'News') ?>
              </span>
            </div>
          </a>

          <!-- Body -->
          <div class="blog-card__body">
            <div class="blog-card__meta">
              <time class="blog-meta-date" datetime="<?= safeStr($post['published_at'] ?? '') ?>">
                <i class="far fa-calendar-alt" aria-hidden="true"></i>
                <?= fmtDate($post['published_at'] ?? $post['date'] ?? 'now') ?>
              </time>
              <?php if (!empty($post['author'])): ?>
                <span class="blog-meta-sep" aria-hidden="true">·</span>
                <span class="blog-meta-author"><?= safeStr($post['author']) ?></span>
              <?php endif; ?>
            </div>

            <h3 class="blog-card__title">
              <a href="<?= safeStr(postUrl($post)) ?>"><?= safeStr($post['title']) ?></a>
            </h3>

            <p class="blog-card__excerpt">
              <?= safeStr(excerpt($post['excerpt'] ?? $post['content'] ?? '', 160)) ?>
            </p>

            <a href="<?= safeStr(postUrl($post)) ?>" class="blog-card__read-more" aria-label="Read more: <?= safeStr($post['title']) ?>">
              Read More <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── PAGINATION ── -->
    <?php if ($totalPages > 1): ?>
      <?php
        $baseUrl         = (defined('BASE_PATH') ? BASE_PATH : '') . '/news?';
        $qParams         = [];
        if ($activeCategory) $qParams[] = 'category=' . urlencode($activeCategory);
        if ($searchQuery)    $qParams[] = 'q=' . urlencode($searchQuery);
        $qParams[]       = 'page=';
        $baseUrl        .= implode('&', $qParams);
        $paginationLabel = 'Blog pages';
        require __DIR__ . '/../partials/pagination.php';
      ?>
    <?php endif; ?>

  <?php endif; ?>
</div><!-- /.blog-layout -->
