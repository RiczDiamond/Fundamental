<?php

namespace App\Services;

class SanitizerService
{
    public function sanitizeText($value)
    {
        $value = trim((string)$value);
        $value = strip_tags($value);
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    public function sanitizeHtml($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (class_exists('HTMLPurifier') && class_exists('HTMLPurifier_Config')) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp', '%^(https?:)?//%');
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $purifier = new \HTMLPurifier($config);
            return (string)$purifier->purify($value);
        }

        return strip_tags($value, '<p><br><strong><em><b><i><ul><ol><li><h2><h3><h4><blockquote><a><img>');
    }

    public function sanitizeUrl($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (strpos($value, '/') === 0) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return '';
    }
}
