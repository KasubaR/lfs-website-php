<?php
/**
 * admin/views/blog/create.php — New blog post form
 *
 * Variables from BlogController::getCreate():
 *   $post        null
 *   $categories  array
 *   $csrfToken   string
 *   $error       string|null
 *   $breadcrumbs array
 */

$isEdit = false;
$postId = null;

// _form.php sets $extraStyles and $extraScripts for Quill CDN
require __DIR__ . '/_form.php';
