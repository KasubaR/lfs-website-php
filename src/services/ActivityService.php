<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/services/ActivityService.php
 *
 * Aggregates recent activity from orders, contact messages, blog posts, and events
 * for the admin dashboard (and optional "View all" page). No dedicated activity table.
 */

declare(strict_types=1);

require_once __DIR__ . '/../model/OrderModel.php';
require_once __DIR__ . '/../services/ContactMessageService.php';
require_once __DIR__ . '/../services/BlogPostService.php';
require_once __DIR__ . '/../services/EventService.php';
require_once __DIR__ . '/../utility/helpers.php';

class ActivityService
{
    private OrderModel $orderModel;
    private ContactMessageService $messageService;
    private BlogPostService $blogService;
    private EventService $eventService;

    public function __construct()
    {
        $this->orderModel   = new OrderModel();
        $this->messageService = new ContactMessageService();
        $this->blogService  = new BlogPostService();
        $this->eventService = new EventService();
    }

    /**
     * Build a unified recent-activity feed from all sources, sorted by date descending.
     *
     * @param  int $limit  Max number of items to return (default 20)
     * @return array<int, array{type: string, icon: string, title: string, subtitle: string, isoDate: string, timeAgo: string}>
     */
    public function getRecentActivity(int $limit = 20): array
    {
        $items = [];

        try {
            $orders = $this->orderModel->getAll(['limit' => 10, 'offset' => 0]);
            foreach ($orders as $o) {
                $items[] = $this->normalizeOrder($o);
            }
        } catch (Throwable) {
            // continue with other sources
        }

        try {
            $messages = $this->messageService->getAll();
            $messages = array_slice($messages, 0, 10);
            foreach ($messages as $m) {
                $items[] = $this->normalizeMessage($m);
            }
        } catch (Throwable) {
            // continue
        }

        try {
            $result = $this->blogService->getPosts(['limit' => 10]);
            foreach ($result['posts'] ?? [] as $p) {
                $items[] = $this->normalizeBlogPost($p);
            }
        } catch (Throwable) {
            // continue
        }

        try {
            $events = $this->eventService->getRecentEvents(10);
            foreach ($events as $e) {
                $items[] = $this->normalizeEvent($e);
            }
        } catch (Throwable) {
            // continue
        }

        usort($items, function (array $a, array $b): int {
            return strcmp($b['isoDate'], $a['isoDate']);
        });

        return array_slice($items, 0, max(1, $limit));
    }

    private function normalizeOrder(array $o): array
    {
        $createdAt = $o['created_at'] ?? '';
        $isoDate   = $createdAt !== '' ? date('c', strtotime($createdAt)) : date('c');
        $total     = number_format((float)($o['total'] ?? 0), 2);
        return [
            'type'     => 'order',
            'icon'     => 'fas fa-bag-shopping',
            'title'    => 'Order ' . ($o['order_number'] ?? ''),
            'subtitle' => ($o['customer_name'] ?? '') . ' · ZMW ' . $total,
            'isoDate'  => $isoDate,
            'timeAgo'  => lfs_timeAgo($createdAt),
        ];
    }

    private function normalizeMessage(array $m): array
    {
        $createdAt = $m['created_at'] ?? '';
        $isoDate   = $createdAt !== '' ? date('c', strtotime($createdAt)) : date('c');
        $name      = trim(($m['name'] ?? '') ?: 'Unknown');
        $sub       = $m['subject'] ?? $m['email'] ?? '';
        return [
            'type'     => 'message',
            'icon'     => 'fas fa-envelope',
            'title'    => 'Message from ' . $name,
            'subtitle' => $sub !== '' ? $sub : 'Contact form',
            'isoDate'  => $isoDate,
            'timeAgo'  => lfs_timeAgo($createdAt),
        ];
    }

    private function normalizeBlogPost(array $p): array
    {
        $createdAt = $p['createdAt'] ?? $p['created_at'] ?? '';
        $isoDate   = $createdAt !== '' && $createdAt !== null
            ? date('c', is_numeric($createdAt) ? (int) $createdAt : strtotime($createdAt))
            : date('c');
        $title     = $p['title'] ?? 'Post';
        $sub       = $p['author'] ?? $p['category'] ?? '';
        return [
            'type'     => 'blog',
            'icon'     => 'fas fa-pencil',
            'title'    => 'Post: ' . $title,
            'subtitle' => $sub !== '' ? (string) $sub : 'Blog',
            'isoDate'  => $isoDate,
            'timeAgo'  => lfs_timeAgo((string) $createdAt),
        ];
    }

    private function normalizeEvent(array $e): array
    {
        $createdAt = $e['createdAt'] ?? $e['created_at'] ?? '';
        $isoDate   = $createdAt !== '' && $createdAt !== null
            ? date('c', is_numeric($createdAt) ? (int) $createdAt : strtotime($createdAt))
            : date('c');
        $title = $e['title'] ?? 'Event';
        $sub   = $e['location'] ?? '';
        if ($sub === '' && !empty($e['eventDate'])) {
            $sub = date('j M Y', strtotime($e['eventDate']));
        }
        return [
            'type'     => 'event',
            'icon'     => 'fas fa-calendar-days',
            'title'    => 'Event: ' . $title,
            'subtitle' => $sub,
            'isoDate'  => $isoDate,
            'timeAgo'  => lfs_timeAgo((string) $createdAt),
        ];
    }
}
