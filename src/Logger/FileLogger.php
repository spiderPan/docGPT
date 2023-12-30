<?php

namespace Pan\DocGpt\Logger;

use Exception;

class FileLogger implements Logger
{
    private string $log_file_path;

    protected array $log_types = ['fatal', 'error', 'warning', 'info', 'debug', 'trace', 'all'];

    /**
     * @throws Exception
     */
    public function __construct(string $log_folder_path = '')
    {
        if (!file_exists($log_folder_path)) {
            if (!mkdir($log_folder_path, 0777, true)) {
                throw new Exception('Failed to create directory: ' . $log_folder_path);
            }
        }

        $this->log_file_path = $log_folder_path . '/' . date('Y-m-d-H-i-s') . '.log';
    }

    public function log($type, $message): void
    {
        $log_entry = [
            'timestamp' => time(),
            'type'      => $type,
            'content'   => $message,
        ];

        $this->writeLogToFile($log_entry);
    }

    protected function writeLogToFile($log_entry): void
    {
        $log_entries   = $this->getLogEntries();
        $log_entries[] = $log_entry;

        file_put_contents($this->log_file_path, json_encode($log_entries));
    }

    /**
     * The Filter is an array of key-value pairs.
     * - When filter by type, it will return all log entries with the type equal or less than the given type.
     */
    public function getLogEntries($filter = []): array
    {
        if (!file_exists($this->log_file_path)) {
            return [];
        }

        $log_string  = file_get_contents($this->log_file_path);
        $log_entries = json_decode($log_string, true);

        if (!is_array($log_entries)) {
            return [];
        }

        if (!empty($filter)) {
            if (isset($filter['type'])) {
                $filter['type'] = strtolower($filter['type']);
                $selected_index = array_search($filter['type'], $this->log_types);

                if ($selected_index === false) {
                    return [];
                }

                $filter['type'] = array_slice($this->log_types, 0, $selected_index + 1);
            }

            $log_entries = array_filter($log_entries, function ($log_entry) use ($filter) {
                foreach ($filter as $key => $value) {
                    if (!isset($log_entry[$key])) {
                        return false;
                    }

                    if (is_array($value)) {
                        if (!in_array($log_entry[$key], $value)) {
                            return false;
                        }
                    } else {
                        if ($log_entry[$key] !== $value) {
                            return false;
                        }
                    }
                }

                return true;
            });
        }

        return $log_entries;
    }

}
