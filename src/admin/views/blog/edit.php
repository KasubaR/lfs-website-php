<?php
/**
 * admin/views/blog/edit.php — Edit blog post form
 *
 * Variables from BlogController::getEdit():
 *   $post        array   existing post data
 *   $postId      string  post ID
 *   $categories  array
 *   $csrfToken   string
 *   $error       string|null
 *   $breadcrumbs array
 */

$isEdit = true;
// $post and $postId are already set by the controller

// _form.php sets $extraStyles and $extraScripts for Quill CDN
require __DIR__ . '/_form.php';
