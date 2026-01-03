<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Analytics;

/**
 * JSONL file storage with concurrent write safety.
 */
class JsonlStorage
{
    public function __construct(
        private string $path
    ) {}

    /**
     * Append a record to the JSONL file.
     */
    public function append(array $record): bool
    {
        // Ensure directory exists
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true)) {
                return false;
            }
        }

        // Check writability
        if (file_exists($this->path) && ! is_writable($this->path)) {
            return false;
        }

        if (! file_exists($this->path) && ! is_writable($dir)) {
            return false;
        }

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        // Use FILE_APPEND | LOCK_EX for concurrent safety
        $result = @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);

        return $result !== false;
    }

    /**
     * Read all records from the JSONL file.
     */
    public function read(): array
    {
        if (! file_exists($this->path)) {
            return [];
        }

        $records = [];
        $handle = @fopen($this->path, 'r');

        if (! $handle) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line !== '') {
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    $records[] = $decoded;
                }
            }
        }

        fclose($handle);

        return $records;
    }

    /**
     * Get the storage path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Clear all records.
     */
    public function clear(): bool
    {
        if (! file_exists($this->path)) {
            return true;
        }

        return @unlink($this->path);
    }

    /**
     * Get record count.
     */
    public function count(): int
    {
        if (! file_exists($this->path)) {
            return 0;
        }

        $count = 0;
        $handle = @fopen($this->path, 'r');

        if (! $handle) {
            return 0;
        }

        while (fgets($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return $count;
    }
}
