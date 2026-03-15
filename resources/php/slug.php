<?php

/**
 * Generate a URL-safe slug from a string.
 */
function mol_sanitize_slug(string $text): string
{
    $slug = preg_replace('/[^a-z0-9\-]+/i', '-', mb_strtolower($text));
    $slug = trim($slug, '-');
    $slug = preg_replace('/-+/', '-', $slug);
    return $slug === '' ? 'item' : $slug;
}

/**
 * Ensure a slug is unique for a post type.
 */
function mol_unique_post_slug(string $slug, string $postType, ?int $postId = null): string
{
    $base = $slug;
    $i = 1;
    while (true) {
        $params = ['post_name' => $slug, 'post_type' => $postType];
        $sql = 'SELECT ID FROM ' . table('posts') . ' WHERE post_name = :post_name AND post_type = :post_type';
        if ($postId !== null) {
            $sql .= ' AND ID != :id';
            $params['id'] = $postId;
        }
        $exists = get_row($sql, $params);
        if (!$exists) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}
