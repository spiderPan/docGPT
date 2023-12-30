<?php

namespace Pan\DocGpt\Logger;

class SystemLogger implements Logger
{
    protected array $log_types = ['fatal', 'error'];

    public function log(string $type, string|array $message): void
    {
        if (is_array($message)) {
            $message = json_encode($message);
        }

        if (!in_array($type, $this->log_types)) {
            // System logger only logs fatal and error messages.
            return;
        }

        error_log($message);
    }
}
