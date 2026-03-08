<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use samdark\sitemap\Sitemap;

final class SitemapService
{
    public function __construct(
        private readonly PDO $link,
        private readonly string $baseUrl,
    ) {
    }

    public function buildXml(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fundamental-sitemap-');
        if ($tempFile === false) {
            throw new \RuntimeException('Kon geen tijdelijk sitemap bestand aanmaken.');
        }

        $sitemap = new Sitemap($tempFile);
        $now = time();

        $sitemap->addItem($this->absoluteUrl('/'), $now, Sitemap::DAILY, 1.0);

        foreach ($this->publishedPages() as $item) {
            $sitemap->addItem(
                $this->absoluteUrl('/' . ltrim((string)($item['slug'] ?? ''), '/')),
                (int)strtotime((string)($item['updated_at'] ?? 'now')),
                Sitemap::WEEKLY,
                0.8
            );
        }

        foreach ($this->publishedBlogs() as $item) {
            $sitemap->addItem(
                $this->absoluteUrl('/blog/' . ltrim((string)($item['slug'] ?? ''), '/')),
                (int)strtotime((string)($item['updated_at'] ?? 'now')),
                Sitemap::WEEKLY,
                0.7
            );
        }

        foreach ($this->publishedContentItems() as $item) {
            $typeSlug = trim((string)($item['type_slug'] ?? ''));
            $slug = trim((string)($item['slug'] ?? ''));
            if ($typeSlug === '' || $slug === '') {
                continue;
            }

            $sitemap->addItem(
                $this->absoluteUrl('/' . $typeSlug . '/' . $slug),
                (int)strtotime((string)($item['updated_at'] ?? 'now')),
                Sitemap::WEEKLY,
                0.6
            );
        }

        $sitemap->write();
        $xml = (string)file_get_contents($tempFile);
        @unlink($tempFile);

        return $xml;
    }

    private function publishedPages(): array
    {
        try {
            $stmt = $this->link->query("SELECT slug, updated_at FROM pages WHERE status = 'published' AND slug <> ''");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function publishedBlogs(): array
    {
        try {
            $stmt = $this->link->query("SELECT slug, updated_at FROM blogs WHERE status = 'published' AND slug <> ''");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function publishedContentItems(): array
    {
        try {
            $stmt = $this->link->query("SELECT type, slug, updated_at FROM content_items WHERE status = 'published' AND slug <> ''");

            if (!$stmt) {
                return [];
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $typeSlugMap = [];
            if (class_exists('ContentTypeRegistry')) {
                $registry = new \ContentTypeRegistry();
                foreach ((array)$registry->getAll() as $typeKey => $definition) {
                    $typeSlugMap[(string)$typeKey] = (string)($definition['slug'] ?? $typeKey);
                }
            }

            foreach ($rows as &$row) {
                $typeKey = (string)($row['type'] ?? '');
                $row['type_slug'] = $typeSlugMap[$typeKey] ?? $typeKey;
            }

            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function absoluteUrl(string $path): string
    {
        $base = rtrim($this->baseUrl, '/');
        $normalizedPath = '/' . ltrim($path, '/');
        if ($normalizedPath === '/home') {
            $normalizedPath = '/';
        }

        return $base . $normalizedPath;
    }
}
