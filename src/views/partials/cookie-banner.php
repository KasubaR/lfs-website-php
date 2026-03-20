<?php
/**
 * LFS COOKIE BANNER PARTIAL — partials/cookie-banner.php
 * Rendered server-side when the user hasn't set a consent cookie.
 * Replace this stub with your full consent implementation.
 */
require_once __DIR__ . '/../../config/CookieConfig.php';
if (!empty($_COOKIE[CookieConfig::NAMES['CONSENT']])) return;
?>
<!-- Cookie banner placeholder — implement consent logic here -->
