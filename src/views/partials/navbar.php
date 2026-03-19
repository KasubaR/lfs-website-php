<!-- ══════════════════════════════════════════════════════
     LFS NAVBAR PARTIAL — partials/navbar.php
     Fixed top nav with scroll-shrink, active links & mobile drawer.
     ══════════════════════════════════════════════════════ -->
<?php
  $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
  $uriPath = rtrim($uriPath, '/') ?: '/';
  $base    = defined('BASE_PATH') ? BASE_PATH : '';
  $relativePath = $uriPath;
  if ($base && str_starts_with($uriPath, $base)) {
    $relativePath = substr($uriPath, strlen($base)) ?: '/';
  }
  $navIsHome    = $relativePath === '/' || $relativePath === '';
  $navIsShop    = str_starts_with($relativePath, '/shop');
  $navIsAbout   = str_starts_with($relativePath, '/about');
  $navIsGallery = str_starts_with($relativePath, '/gallery');
  $navIsContact = str_starts_with($relativePath, '/contact');
?>

<nav class="lfs-nav" role="navigation" aria-label="Main navigation">

  <!-- Logo → home -->
  <a href="<?= BASE_PATH ?>/#hero" class="lfs-nav__logo" aria-label="LFS — Home">
    <img src="<?= BASE_PATH ?>/images/Logo/1024%20512%20LFS_1024.svg" alt="LFS — Lusaka Fitness Squad" />
  </a>

  <!-- Desktop links -->
  <ul class="lfs-nav__links" role="list">
    <li class="lfs-nav__item lfs-nav__item--has-dropdown">
      <a href="<?= BASE_PATH ?>/#hero" class="nav-link<?= $navIsHome ? ' nav-link--active' : '' ?>">Home <i class="fas fa-chevron-down lfs-nav__chevron" aria-hidden="true"></i></a>
      <ul class="lfs-nav__dropdown" role="list">
        <li><a href="<?= BASE_PATH ?>/#activities">Activities</a></li>
        <li><a href="<?= BASE_PATH ?>/#events">Events</a></li>
        <li><a href="<?= BASE_PATH ?>/#news">News</a></li>
      </ul>
    </li>
    <li><a href="<?= BASE_PATH ?>/shop" class="nav-link<?= $navIsShop ? ' nav-link--active' : '' ?>">Shop</a></li>
    <li><a href="<?= BASE_PATH ?>/about" class="nav-link<?= $navIsAbout ? ' nav-link--active' : '' ?>">About Us</a></li>
    <li><a href="<?= BASE_PATH ?>/gallery" class="nav-link<?= $navIsGallery ? ' nav-link--active' : '' ?>">Gallery</a></li>
    <li><a href="<?= BASE_PATH ?>/contact" class="nav-link<?= $navIsContact ? ' nav-link--active' : '' ?>">Contact Us</a></li>
    <li>
      <a href="https://squidal.com/lfsmembership" class="lfs-nav__cta" target="_blank" rel="noopener noreferrer">
        Join Now
      </a>
    </li>
  </ul>

  <!-- Hamburger (mobile) -->
  <button class="lfs-nav__hamburger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-nav">
    <span></span>
    <span></span>
    <span></span>
  </button>

</nav>

<!-- ── MOBILE NAV DRAWER (same order & links as desktop) ── -->
<div id="mobile-nav" class="lfs-nav__mobile" role="dialog" aria-label="Mobile navigation" aria-hidden="true">
  <a href="<?= BASE_PATH ?>/#hero" class="<?= $navIsHome ? 'lfs-nav__mobile-link--active' : '' ?>">Home</a>
  <a href="<?= BASE_PATH ?>/#activities">Activities</a>
  <a href="<?= BASE_PATH ?>/#events">Events</a>
  <a href="<?= BASE_PATH ?>/#news">News</a>
  <a href="<?= BASE_PATH ?>/shop" class="<?= $navIsShop ? 'lfs-nav__mobile-link--active' : '' ?>">Shop</a>
  <a href="<?= BASE_PATH ?>/about" class="<?= $navIsAbout ? 'lfs-nav__mobile-link--active' : '' ?>">About Us</a>
  <a href="<?= BASE_PATH ?>/gallery" class="<?= $navIsGallery ? 'lfs-nav__mobile-link--active' : '' ?>">Gallery</a>
  <a href="<?= BASE_PATH ?>/contact" class="<?= $navIsContact ? 'lfs-nav__mobile-link--active' : '' ?>">Contact Us</a>
  <a href="https://squidal.com/lfsmembership" class="lfs-nav__mobile-cta" target="_blank" rel="noopener noreferrer">
    Join Now
  </a>
</div>
