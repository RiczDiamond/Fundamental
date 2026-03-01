<?php

class RateLimiter {

    private $link;
    private $sessionKey = 'mw_rate_limiter';
    private $maxAttempts = 5;
    private $blockSeconds = 900;

    public function __construct($link = null, $maxAttempts = 5, $blockSeconds = 900) {
        $this->link = $link;
        $this->maxAttempts = (int) $maxAttempts;
        $this->blockSeconds = (int) $blockSeconds;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }
    }

    public function check($ip) {
        return !$this->isBlocked($ip);
    }

    public function isBlocked($ip) {
        $record = $this->getRecord($ip);

        if (empty($record['blocked_until'])) {
            return false;
        }

        $blockedUntil = (int) $record['blocked_until'];

        if (time() >= $blockedUntil) {
            $this->clear($ip);
            return false;
        }

        return true;
    }

    public function increment($ip) {
        $record = $this->getRecord($ip);

        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
        $record['last_attempt'] = time();

        if ($record['attempts'] >= $this->maxAttempts) {
            $record['blocked_until'] = time() + $this->blockSeconds;
        }

        $_SESSION[$this->sessionKey][$this->normalizeIp($ip)] = $record;
    }

    public function reset($ip) {
        $this->clear($ip);
    }

    public function timeRemaining($ip) {
        $record = $this->getRecord($ip);
        $blockedUntil = (int) ($record['blocked_until'] ?? 0);

        if ($blockedUntil <= time()) {
            return 0;
        }

        return (int) ceil(($blockedUntil - time()) / 60);
    }

    private function clear($ip) {
        unset($_SESSION[$this->sessionKey][$this->normalizeIp($ip)]);
    }

    private function getRecord($ip) {
        $key = $this->normalizeIp($ip);
        return $_SESSION[$this->sessionKey][$key] ?? [];
    }

    private function normalizeIp($ip) {
        $ip = trim((string) $ip);
        return $ip !== '' ? $ip : 'unknown';
    }
}
