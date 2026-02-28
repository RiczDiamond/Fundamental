<?php

declare(strict_types=1);

// Helper functions to retrieve simple meta values for posts and users.

function get_post_meta(int $post_id, ?string $key = null)
{
    global $link;
    if (!isset($link)) {
        return $key === null ? [] : null;
    }

    try {
        if ($key === null) {
            $stmt = $link->prepare('SELECT meta_key, meta_value FROM postmeta WHERE post_id = :id');
            $stmt->execute([':id' => $post_id]);
            $rows = $stmt->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[$r['meta_key']][] = $r['meta_value'];
            }
            return $out;
        }

        $stmt = $link->prepare('SELECT meta_value FROM postmeta WHERE post_id = :id AND meta_key = :k');
        $stmt->execute([':id' => $post_id, ':k' => $key]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!$rows) return null;
        return count($rows) === 1 ? $rows[0] : $rows;
    } catch (Exception $e) {
        return $key === null ? [] : null;
    }
}

function get_user_meta(int $user_id, ?string $key = null)
{
    global $link;
    if (!isset($link)) {
        return $key === null ? [] : null;
    }

    try {
        if ($key === null) {
            $stmt = $link->prepare('SELECT meta_key, meta_value FROM usermeta WHERE user_id = :id');
            $stmt->execute([':id' => $user_id]);
            $rows = $stmt->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[$r['meta_key']][] = $r['meta_value'];
            }
            return $out;
        }

        $stmt = $link->prepare('SELECT meta_value FROM usermeta WHERE user_id = :id AND meta_key = :k');
        $stmt->execute([':id' => $user_id, ':k' => $key]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!$rows) return null;
        return count($rows) === 1 ? $rows[0] : $rows;
    } catch (Exception $e) {
        return $key === null ? [] : null;
    }
}
