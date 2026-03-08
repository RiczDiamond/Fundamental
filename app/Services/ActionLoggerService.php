<?php

namespace App\Services;

class ActionLoggerService
{
    private $logDir;

    public function __construct($basePath)
    {
        $this->logDir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }
    }

    public function info($message, array $context = [])
    {
        $this->write('INFO', $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->write('ERROR', $message, $context);
    }

    private function write($level, $message, array $context)
    {
        $file = $this->logDir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents($file, $line, FILE_APPEND);
    }
}
