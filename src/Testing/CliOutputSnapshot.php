<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Testing;

use PHPUnit\Framework\Assert;

/**
 * CLI output snapshot testing helper.
 *
 * Captures CLI output and compares against stored snapshots,
 * with ANSI stripping and customizable normalization.
 */
class CliOutputSnapshot
{
    private string $snapshotDir;

    /** @var callable|null */
    private $customNormalizer = null;

    public function __construct(string $snapshotDir = 'tests/snapshots')
    {
        $this->snapshotDir = $snapshotDir;
    }

    /**
     * Set custom normalizer for timestamps, paths, etc.
     */
    public function normalizer(callable $normalizer): self
    {
        $this->customNormalizer = $normalizer;

        return $this;
    }

    /**
     * Assert CLI output matches snapshot.
     */
    public function assertMatchesSnapshot(string $name, string $output): void
    {
        $path = "{$this->snapshotDir}/{$name}.txt";

        if (! file_exists($path)) {
            // Create snapshot on first run
            @mkdir(dirname($path), 0755, true);
            file_put_contents($path, $this->normalizeOutput($output));
            Assert::markTestIncomplete("Snapshot created: {$path}");

            return;
        }

        $expected = file_get_contents($path);
        Assert::assertEquals(
            $this->normalizeOutput($expected),
            $this->normalizeOutput($output),
            "CLI output does not match snapshot: {$name}"
        );
    }

    /**
     * Update snapshot file.
     */
    public function updateSnapshot(string $name, string $output): void
    {
        $path = "{$this->snapshotDir}/{$name}.txt";
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $this->normalizeOutput($output));
    }

    /**
     * Check if snapshot exists.
     */
    public function hasSnapshot(string $name): bool
    {
        return file_exists("{$this->snapshotDir}/{$name}.txt");
    }

    /**
     * Get snapshot path.
     */
    public function snapshotPath(string $name): string
    {
        return "{$this->snapshotDir}/{$name}.txt";
    }

    /**
     * Normalize output for comparison.
     */
    public function normalizeOutput(string $output): string
    {
        // Strip ANSI escape sequences
        $output = self::stripAnsi($output);

        // Normalize line endings
        $output = str_replace("\r\n", "\n", $output);

        // Apply custom normalizer if set
        if ($this->customNormalizer) {
            $output = ($this->customNormalizer)($output);
        }

        return trim($output);
    }

    /**
     * Strip ANSI codes from output (static helper).
     */
    public static function stripAnsi(string $output): string
    {
        return preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $output);
    }

    /**
     * Create a normalizer that replaces dynamic values.
     */
    public static function createNormalizer(array $replacements): callable
    {
        return function (string $output) use ($replacements): string {
            foreach ($replacements as $pattern => $replacement) {
                if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
                    // Regex pattern
                    $output = preg_replace($pattern, $replacement, $output);
                } else {
                    // Simple string replacement
                    $output = str_replace($pattern, $replacement, $output);
                }
            }

            return $output;
        };
    }

    /**
     * Common normalizer for timestamps.
     */
    public static function timestampNormalizer(): callable
    {
        return self::createNormalizer([
            '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}/' => '[TIMESTAMP]',
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/' => '[DATETIME]',
        ]);
    }
}
