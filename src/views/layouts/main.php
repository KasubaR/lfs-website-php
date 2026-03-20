<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($title) ? htmlspecialchars($title) . ' — LFS' : 'LFS — Lusaka Fitness Squad' ?></title>
  <meta name="description" content="<?= isset($description) ? htmlspecialchars($description) : "Zambia's biggest running community. Train. Run. Compete. Together." ?>">
  <meta name="theme-color" content="#0f0f0f">

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(lfs_public_url('/images/Logo/1024%20512%20LFS_512x512%201.svg'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="alternate icon" href="<?= htmlspecialchars(lfs_public_url('/images/Logo/1024%20512%20LFS_512x512%201.svg'), ENT_QUOTES, 'UTF-8') ?>">

  <!-- Open Graph -->
  <meta property="og:title" content="<?= isset($title) ? htmlspecialchars($title) . ' — LFS' : 'LFS — Lusaka Fitness Squad' ?>">
  <meta property="og:description" content="<?= isset($description) ? htmlspecialchars($description) : "Zambia's biggest running community." ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.lfszambia.run">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,300&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Tailwind (built; run npm run build:css to regenerate) -->
  <link rel="stylesheet" href="<?= htmlspecialchars(lfs_public_url('/css/tailwind-build.css'), ENT_QUOTES, 'UTF-8') ?>">
  <!-- LFS custom utilities and tokens -->
  <link rel="stylesheet" href="<?= htmlspecialchars(lfs_public_url('/css/tailwind.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(lfs_public_url('/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(lfs_public_url('/css/cookie-banner.css'), ENT_QUOTES, 'UTF-8') ?>">

  <!-- Page-specific styles (optional — trusted controller output only, never from user input) -->
  <?= ($styles ?? '') . ($extraStyles ?? '') ?>
</head>

<body class="antialiased<?= isset($bodyClass) && $bodyClass !== '' ? ' ' . htmlspecialchars($bodyClass) : '' ?>">

  <!-- ── NAVBAR PARTIAL ── -->
  <?php require __DIR__ . '/../partials/navbar.php'; ?>

  <!-- ── MAIN CONTENT ── -->
  <main id="main-content">
    <?= $content ?? '' ?>
  </main>

  <!-- ── FOOTER PARTIAL ── -->
  <?php require __DIR__ . '/../partials/footer.php'; ?>

  <!-- ── FLOATING CART FAB (hidden when cart empty) ── -->
  <?php $cartCount = $cartCount ?? 0; ?>
  <button class="lfs-cart-fab<?= $cartCount === 0 ? ' lfs-cart-fab--hidden' : '' ?>" onclick="window.location='/shop/cart'" aria-label="View cart (<?= $cartCount ?> items)">
    <i class="fas fa-shopping-bag"></i>
    <span class="lfs-cart-fab__count"<?= $cartCount === 0 ? ' style="display:none"' : '' ?>><?= $cartCount ?></span>
  </button>

  <!-- Cookie consent banner -->
  <?php require __DIR__ . '/../partials/cookie-banner.php'; ?>
  <script src="<?= htmlspecialchars(lfs_public_url('/js/cookie-banner.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

  <!-- Global JS -->
  <script>window.__LFS_CART_COUNT__ = <?= $cartCount ?>;</script>
  <script src="<?= htmlspecialchars(lfs_public_url('/js/input-sanitizer.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(lfs_public_url('/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(lfs_public_url('/js/cart.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

  <!-- Page-specific scripts (optional — trusted controller output only, never from user input) -->
  <?= ($scripts ?? '') . ($extraScripts ?? '') ?>

</body>
</html>
