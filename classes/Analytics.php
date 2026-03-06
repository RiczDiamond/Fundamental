<?php

class Analytics
{
    private $link;
    private $table = 'analytics';

    public function __construct($link, $table = 'analytics')
    {
        $this->link   = $link;
        $this->table  = $table;
    }

    /**
     * Track a page view.
     *
     * @param string|null $page   URI/path to record; defaults to current REQUEST_URI
     * @param int|null    $userId Authenticated user id, or null for guests
     */
    public function pageview($page = null, $userId = null): void
    {
        $this->track('pageview', ['page' => $page ?? ($_SERVER['REQUEST_URI'] ?? '/')], $userId);
    }

    /**
     * Track a named event with optional payload.
     *
     * @param string      $event  Event name (e.g. 'signup', 'purchase')
     * @param array       $data   Arbitrary key-value context
     * @param int|null    $userId Authenticated user id, or null for guests
     */
    public function track(string $event, array $data = [], $userId = null): void
    {
        $page     = $data['page'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $ip       = $_SERVER['REMOTE_ADDR']     ?? '';
        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER']    ?? '';
        $payload  = empty($data)
            ? null
            : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $this->link->prepare(
                "INSERT INTO {$this->table}
                    (user_id, event, page, ip, user_agent, referrer, payload, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$userId, $event, $page, $ip, $ua, $referrer, $payload]);
        } catch (Exception $e) {
            // silent fail – analytics must never break the app
        }
    }

    /**
     * Count page views, optionally filtered by path and date range.
     *
     * @param string|null $page  Exact page path, or null for all pages
     * @param string|null $from  Start date (Y-m-d or Y-m-d H:i:s)
     * @param string|null $to    End date
     * @return int
     */
    public function get_pageviews($page = null, $from = null, $to = null): int
    {
        [$sql, $params] = $this->buildBaseQuery("SELECT COUNT(*)", 'pageview', $page, $from, $to);
        $stmt = $this->link->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Return the most visited pages as [page => views] sorted descending.
     *
     * @param int         $limit Max rows to return
     * @param string|null $from  Start date
     * @param string|null $to    End date
     * @return array<array{page: string, views: int}>
     */
    public function get_popular_pages(int $limit = 10, $from = null, $to = null): array
    {
        $params = ['pageview'];
        $sql    = "SELECT page, COUNT(*) AS views FROM {$this->table} WHERE event = ?";
        if ($from) { $sql .= ' AND created_at >= ?'; $params[] = $from; }
        if ($to)   { $sql .= ' AND created_at <= ?'; $params[] = $to;   }
        $sql .= ' GROUP BY page ORDER BY views DESC LIMIT ' . (int) $limit;

        $stmt = $this->link->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count unique visitors (distinct IPs) in the given time window.
     *
     * @param string|null $from  Start date
     * @param string|null $to    End date
     * @return int
     */
    public function get_unique_visitors($from = null, $to = null): int
    {
        [$sql, $params] = $this->buildBaseQuery("SELECT COUNT(DISTINCT ip)", 'pageview', null, $from, $to);
        $stmt = $this->link->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function get_daily_pageviews($from = null, $to = null): array
    {
        $params = ['pageview'];
        $sql = "SELECT DATE(created_at) AS day, COUNT(*) AS views
                FROM {$this->table}
                WHERE event = ?";

        if ($from !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND created_at <= ?';
            $params[] = $to;
        }

        $sql .= ' GROUP BY DATE(created_at) ORDER BY day ASC';

        $stmt = $this->link->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count occurrences of a named event.
     *
     * @param string      $event  Event name
     * @param string|null $from   Start date
     * @param string|null $to     End date
     * @return int
     */
    public function get_events(string $event, $from = null, $to = null): int
    {
        [$sql, $params] = $this->buildBaseQuery("SELECT COUNT(*)", $event, null, $from, $to);
        $stmt = $this->link->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Return raw event rows for a given event, newest first.
     *
     * @param string      $event
     * @param int         $limit
     * @param string|null $from
     * @param string|null $to
     * @return array
     */
    public function get_event_rows(string $event, int $limit = 100, $from = null, $to = null): array
    {
        [$sql, $params] = $this->buildBaseQuery("SELECT *", $event, null, $from, $to);
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->link->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build a parameterised partial query for a given event/page/date range.
     *
     * @return array{0: string, 1: array}
     */
    private function buildBaseQuery(
        string $select,
        string $event,
        $page,
        $from,
        $to
    ): array {
        $params = [$event];
        $sql    = "{$select} FROM {$this->table} WHERE event = ?";

        if ($page !== null) {
            $sql     .= ' AND page = ?';
            $params[] = $page;
        }
        if ($from !== null) {
            $sql     .= ' AND created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql     .= ' AND created_at <= ?';
            $params[] = $to;
        }

        return [$sql, $params];
    }
}
