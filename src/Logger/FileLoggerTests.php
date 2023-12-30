<?php

namespace Pan\DocGpt\Logger;

use Exception;
use PHPUnit\Framework\TestCase;

class FileLoggerTests extends TestCase
{
    private FileLogger $fileLogger;
    private string     $log_folder_path;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->log_folder_path = sys_get_temp_dir() . '/logs';
        $this->fileLogger      = new FileLogger($this->log_folder_path);
    }

    protected function reset(): void
    {
        array_map('unlink', glob($this->log_folder_path . '/*.log'));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->log_folder_path . '/*.log'));
        rmdir($this->log_folder_path);
    }

    public function testLog()
    {
        $this->reset();
        $this->fileLogger->log('info', 'Test message');
        $this->fileLogger->log('error', 'Test message 2');
        $files = glob($this->log_folder_path . '/*.log');
        $this->assertCount(1, $files);
    }

    public function testGetLogEntries()
    {
        $this->reset();
        $this->fileLogger->log('info', 'Test message');
        $this->fileLogger->log('error', 'Test message 2');
        $this->fileLogger->log('fatal', 'Test message 3');

        // get all
        $logEntries = $this->fileLogger->getLogEntries();
        $this->assertCount(3, $logEntries);
        foreach ($logEntries as $logEntry) {
            $this->assertArrayHasKey('timestamp', $logEntry);
            $this->assertArrayHasKey('type', $logEntry);
            $this->assertArrayHasKey('content', $logEntry);
        }

        // get error type and above
        $logEntries = $this->fileLogger->getLogEntries(['type' => 'error']);
        $this->assertCount(2, $logEntries);

        // get fatal type only
        $logEntries = $this->fileLogger->getLogEntries(['type' => 'fatal']);
        $this->assertCount(1, $logEntries);
    }

}
