<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/controllers/BlogController.php
 *
 * Blog post admin: list, create, edit, update, delete.
 * Depends on:
 *   - BlogPostService  (src/services/BlogPostService.php)
 *   - BlogPost model   (src/model/BlogPost.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../services/BlogPostService.php';
require_once __DIR__ . '/../../model/BlogPost.php';

class BlogController
{
    private BlogPostService $service;
    private string $publicRoot;

    public function __construct()
    {
        $this->service    = new BlogPostService();
        $this->publicRoot = defined('PUBLIC_ROOT')
            ? PUBLIC_ROOT
            : realpath(__DIR__ . '/../../../public');
    }

    /* ════════════════════════════════════════════════════════════
       LIST — GET /admin/blog/list
       ════════════════════════════════════════════════════════════ */

    public function list(): void
    {
        $status   = $_GET['status']   ?? '';
        $category = $_GET['category'] ?? '';
        $search   = $_GET['search']   ?? '';

        $opts = ['limit' => 100];
        if ($status)   $opts['status']   = $status;
        if ($category) $opts['category'] = $category;
        if ($search)   $opts['search']   = $search;

        $posts      = [];
        $postsError = null;
        $total      = 0;

        try {
            ['posts' => $posts, 'total' => $total] = $this->service->getPosts($opts);
        } catch (Throwable $e) {
            $postsError = $e->getMessage() ?: 'Could not load posts. Check database connection.';
            error_log('[LFS Admin] BlogController::list — ' . $e->getMessage());
        }

        $this->render('blog/list', [
            'pageTitle'      => 'Blog Posts',
            'activePage'     => 'blog',
            'posts'          => $posts,
            'total'          => $total,
            'postsError'     => $postsError,
            'categories'     => BlogPost::CATEGORIES,
            'statuses'       => BlogPost::STATUSES,
            'filterStatus'   => $status,
            'filterCategory' => $category,
            'filterSearch'   => $search,
            'breadcrumbs'    => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Blog'],
            ],
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       CREATE (GET) — GET /admin/blog/create
       ════════════════════════════════════════════════════════════ */

    public function getCreate(): void
    {
        $this->render('blog/create', [
            'pageTitle'  => 'New Post',
            'activePage' => 'blog',
            'post'       => null,
            'categories' => BlogPost::CATEGORIES,
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Blog',  'url' => '/admin/blog'],
                ['label' => 'New Post'],
            ],
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       CREATE (POST) — POST /admin/blog
       ════════════════════════════════════════════════════════════ */

    public function postCreate(?string $uploadedImagePath = null, ?string $uploadError = null): void
    {
        if ($uploadError !== null && $uploadError !== '') {
            $this->renderFormError('blog/create', $_POST, 'New Post', $uploadError);
            return;
        }

        $data   = $this->extractPostData($_POST);
        $errors = BlogPost::validate($data);

        if (!empty($errors)) {
            // For now we surface only the first error message to the UI.
            $firstError = reset($errors);
            $this->renderFormError('blog/create', $data, 'New Post', (string)$firstError);
            return;
        }

        $data['featuredImage'] = $this->resolveUploadedImage(
            $_POST['featuredImage'] ?? null,
            $uploadedImagePath
        );

        try {
            $this->service->createPost($data);
            header('Location: /admin/blog/list');
            exit;
        } catch (Throwable $e) {
            $this->renderFormError('blog/create', $data, 'New Post', $e->getMessage());
        }
    }

    /* ════════════════════════════════════════════════════════════
       EDIT (GET) — GET /admin/blog/:id/edit
       ════════════════════════════════════════════════════════════ */

    public function getEdit(string $id): void
    {
        $post = $this->safeGetById($id);
        if (!$post) {
            header('Location: /admin/blog/list');
            exit;
        }

        $this->render('blog/edit', [
            'pageTitle'  => 'Edit Post',
            'activePage' => 'blog',
            'post'       => $post,
            'postId'     => $id,
            'categories' => BlogPost::CATEGORIES,
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Blog',  'url' => '/admin/blog'],
                ['label' => $post['title']],
            ],
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       UPDATE (POST) — POST /admin/blog/:id
       ════════════════════════════════════════════════════════════ */

    public function postUpdate(string $id, ?string $uploadedImagePath = null, ?string $uploadError = null): void
    {
        // Fetch existing record once — used for image cleanup, error re-render,
        // and the not-found guard.  All three paths below reuse this same fetch.
        $existing = $this->safeGetById($id);
        if (!$existing) {
            header('Location: /admin/blog/list');
            exit;
        }

        if ($uploadError !== null && $uploadError !== '') {
            $this->renderFormError('blog/edit', $existing, 'Edit Post', $uploadError, $id);
            return;
        }

        $data   = $this->extractPostData($_POST);
        $errors = BlogPost::validate($data);

        if (!empty($errors)) {
            $firstError = reset($errors);
            $this->renderFormError(
                'blog/edit',
                array_merge($existing, $data),
                'Edit Post',
                (string)$firstError,
                $id
            );
            return;
        }

        $newImage = $this->resolveUploadedImage(
            $_POST['featuredImage'] ?? null,
            $uploadedImagePath
        );
        if ($newImage !== null) {
            $data['featuredImage'] = $newImage;
            // Delete old local image only when a new one is saved
            $oldImage = $existing['featuredImage'] ?? '';
            if ($oldImage && str_starts_with($oldImage, '/images/blog/') && $oldImage !== $newImage) {
                $oldPath = $this->publicRoot . '/' . ltrim($oldImage, '/');
                if (file_exists($oldPath) && !unlink($oldPath)) {
                    error_log('[LFS Admin] Failed to delete blog featured image at: ' . $oldPath);
                }
            }
        }

        try {
            $this->service->updatePost($id, $data);
            header('Location: /admin/blog/list');
            exit;
        } catch (Throwable $e) {
            $this->renderFormError('blog/edit', array_merge($existing, $data), 'Edit Post', $e->getMessage(), $id);
        }
    }

    /* ════════════════════════════════════════════════════════════
       DELETE (GET) — GET /admin/blog/:id/delete — confirmation page
       ════════════════════════════════════════════════════════════ */

    public function getDelete(string $id): void
    {
        $post = $this->safeGetById($id);
        if (!$post) {
            header('Location: /admin/blog/list');
            exit;
        }

        $this->render('blog/delete', [
            'pageTitle'  => 'Delete Post',
            'activePage' => 'blog',
            'post'       => $post,
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Blog',  'url' => '/admin/blog'],
                ['label' => 'Delete: ' . $post['title']],
            ],
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       DELETE (POST) — POST /admin/blog/:id/delete
       ════════════════════════════════════════════════════════════ */

    public function postDelete(string $id): void
    {
        // Defence-in-depth: verify CSRF inside the controller so this method
        // is safe even if the router middleware is ever skipped or re-ordered.
        $tokenProvided = $_POST['_csrf'] ?? '';
        $tokenExpected = $_SESSION['csrf_token'] ?? '';
        if ($tokenProvided === '' || !hash_equals($tokenExpected, $tokenProvided)) {
            http_response_code(403);
            exit('Forbidden: invalid CSRF token.');
        }

        $post    = $this->safeGetById($id);
        $deleted = $this->service->deletePost($id);

        if (!$deleted) {
            error_log('[LFS Admin] BlogController::postDelete — no blog post deleted for id ' . $id);
        }

        if ($post) {
            $img = $post['featuredImage'] ?? '';
            if ($img && str_starts_with($img, '/images/blog/')) {
                $path = $this->publicRoot . '/' . ltrim($img, '/');
                if (file_exists($path) && !unlink($path)) {
                    error_log('[LFS Admin] Failed to delete blog featured image at: ' . $path);
                }
            }
        }

        header('Location: /admin/blog/list');
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    private function render(string $view, array $vars = []): void
    {
        $csrfToken = $_SESSION['csrf_token'] ?? '';

        $viewPath   = __DIR__ . '/../views/' . $view . '.php';
        $layoutPath = __DIR__ . '/../views/layouts/admin.php';

        // Render the inner view to a string with an isolated variable scope.
        $content = $this->renderTemplate($viewPath, $vars + [
            'csrfToken' => $csrfToken,
        ]);

        // Render the layout, injecting the page content and CSRF token.
        $this->renderTemplate($layoutPath, $vars + [
            'csrfToken' => $csrfToken,
            'content'   => $content,
        ], false);
    }

    private function renderFormError(
        string  $view,
        ?array  $post,
        string  $pageTitle,
        string  $error,
        ?string $postId = null
    ): void {
        // Unprocessable Entity — payload was syntactically valid but failed
        // validation (e.g. missing title, invalid status). This helps caches,
        // monitoring tools, and tests distinguish soft validation failures from
        // successful form renders.
        http_response_code(422);

        $this->render($view, [
            'pageTitle'  => $pageTitle,
            'activePage' => 'blog',
            'post'       => $post,
            'postId'     => $postId,
            'categories' => BlogPost::CATEGORIES,
            'error'      => $error,
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Blog',  'url' => '/admin/blog'],
                ['label' => $pageTitle],
            ],
        ]);
    }

    /**
     * Render a PHP template file with a dedicated variable scope.
     *
     * @param  string $file   Absolute path to the template file.
     * @param  array  $vars   Variables to expose to the template.
     * @param  bool   $return When true, returns the rendered string; otherwise echoes it.
     * @return string         The rendered output.
     */
    private function renderTemplate(string $file, array $vars, bool $return = true): string
    {
        ob_start();
        (static function (string $___file, array $___vars): void {
            // Variables are scoped to this anonymous function; they cannot
            // overwrite controller locals such as $content or $csrfToken.
            extract($___vars, EXTR_SKIP);
            require $___file;
        })($file, $vars);

        $output = ob_get_clean();

        if (!$return) {
            echo $output;
        }

        return $output;
    }

    /**
     * Extract and sanitise form fields from $_POST into a data array.
     * Handles tags (comma-separated → array) and publish mode logic.
     */
    private function extractPostData(array $post): array
    {
        // Tags: "php, laravel, running" → ['php', 'laravel', 'running']
        $rawTags = $post['tags'] ?? '';
        $tags    = array_values(array_filter(
            array_map('trim', explode(',', $rawTags))
        ));

        $status      = in_array($post['status'] ?? '', BlogPost::STATUSES, true)
            ? $post['status']
            : 'draft';
        $publishDate = null;

        $publishMode = $post['publishMode'] ?? '';

        if ($publishMode === 'schedule' && !empty($post['publishDate'])) {
            // Scheduled: stays draft; publish_date stores the target time
            $status      = 'draft';
            $publishDate = $post['publishDate'];
        } elseif ($status === 'published') {
            // Publishing now: set publish_date to current time if not already set
            $publishDate = !empty($post['publishDate'])
                ? $post['publishDate']
                : date('Y-m-d H:i:s');
        } else {
            $publishDate = !empty($post['publishDate']) ? $post['publishDate'] : null;
        }

        $data = [
            'title'       => trim($post['title']   ?? ''),
            'slug'        => preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($post['slug'] ?? ''))),
            'excerpt'     => trim($post['excerpt'] ?? ''),
            'content'     => $post['content']      ?? '',
            'author'      => trim($post['author']  ?? 'LFS Admin'),
            'category'    => $post['category']     ?? '',
            'tags'        => $tags,
            'status'      => $status,
            'featured'    => !empty($post['featured']),
            'publishDate' => $publishDate,
        ];

        // Server-side HTML sanitisation for rich content (defence against stored XSS)
        $data['content'] = $this->purifyHtml((string) $data['content']);

        return $data;
    }

    /**
     * Sanitise rich HTML content using HTMLPurifier if available.
     * Falls back to the original HTML when the library is not installed.
     */
    private function purifyHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // HTMLPurifier MUST be available. If it is not installed, refuse to
        // save rich content rather than silently storing unsanitised HTML.
        if (!class_exists('\HTMLPurifier')) {
            throw new \RuntimeException(
                'HTMLPurifier is not installed. Run `composer require ezyang/htmlpurifier` ' .
                'or add it to your vendor autoloader before saving post content.'
            );
        }

        static $purifier = null;

        if ($purifier === null) {
            $config = \HTMLPurifier_Config::createDefault();

            // Allow the same elements Quill's Snow toolbar can produce.
            $config->set('HTML.Allowed',
                'h1,h2,h3,p,br,strong,em,u,s,ul,ol,li,blockquote,pre,code,' .
                'a[href|title|target],img[src|alt|width|height],' .
                'span[class],div[class]'
            );
            // Force all links to be http/https — blocks javascript: URIs.
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
            // Rel="noopener noreferrer" on external links.
            $config->set('HTML.TargetBlank', true);
            $config->set('HTML.TargetNoreferrer', true);
            $config->set('HTML.TargetNoopener', true);

            $purifier = new \HTMLPurifier($config);
        }

        return $purifier->purify($html);
    }

    /**
     * Resolve the featured image path for a blog post.
     *
     * Priority:
     *   1) $uploadedPath — returned by BlogImageUpload::handle() when a file
     *      was successfully uploaded (e.g. "/images/blog/blog-abc123.jpg").
     *   2) $bodyUrl      — the URL/path typed into the form body.
     *
     * Returns null if neither is set or when the body value fails validation.
     */
    private function resolveUploadedImage(?string $bodyUrl, ?string $uploadedPath): ?string
    {
        // Uploaded file takes priority; path is passed explicitly from middleware.
        if ($uploadedPath !== null && $uploadedPath !== '') {
            return $uploadedPath;
        }

        if ($bodyUrl === null || $bodyUrl === '') {
            return null;
        }

        // Allow absolute server-relative paths (e.g. /images/blog/foo.jpg).
        if (str_starts_with($bodyUrl, '/')) {
            // Block any attempt to smuggle a protocol via a path-like string.
            if (preg_match('/[<>"\'`\s]/', $bodyUrl)) {
                return null;
            }
            return $bodyUrl;
        }

        // For full URLs, only permit http and https schemes.
        if (filter_var($bodyUrl, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($bodyUrl, PHP_URL_SCHEME));
            if (in_array($scheme, ['http', 'https'], true)) {
                return $bodyUrl;
            }
        }

        // Anything else (javascript:, data:, bare filenames, …) is rejected.
        return null;
    }

    private function safeGetById(string $id): ?array
    {
        try {
            return $this->service->getPostById($id);
        } catch (Throwable) {
            return null;
        }
    }
}
