<?php

/**
 * Cleanup expired session entries stored in usermeta.
 *
 * Run from the command line (e.g. via cron):
 * php resources/cron/cleanup_sessions.php
 */

require_once __DIR__ . '/../php/init.php';

$account = new Account($link);
$removed = $account->cleanup_all_expired_sessions();

echo "Removed {$removed} expired session(s)\n";
