<?php

// Cron job to prune old audit log entries.
// Use a scheduler (cron, Task Scheduler) to run this weekly.

require_once __DIR__ . '/../php/init.php';

$days = (int) (getenv('AUDIT_RETENTION_DAYS') ?: 90);
if ($days <= 0) {
    $days = 90;
}

$removed = mol_prune_audit_log($days);

file_put_contents(__DIR__ . '/audit-prune.log', '[' . date('c') . "] Removed {$removed} audit entries older than {$days} days\n", FILE_APPEND);
