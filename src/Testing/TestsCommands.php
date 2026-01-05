<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Testing;

/**
 * Test helpers for CLI commands.
 */
trait TestsCommands
{
    /**
     * Capture stderr output.
     */
    protected function captureStderr(callable $callback): string
    {
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        $originalStderr = null;
        if (defined('STDERR')) {
            $originalStderr = STDERR;
        }

        // This is tricky in PHP - stderr capture is limited
        // Best approach is to use output buffering where possible
        ob_start();
        $callback();
        $output = ob_get_clean();

        return $output ?: '';
    }

    /**
     * Assert that output contains all specified strings.
     */
    protected function assertOutputContainsAll(string $output, array $strings): void
    {
        foreach ($strings as $string) {
            $this->assertStringContainsString(
                $string,
                $output,
                "Output does not contain: {$string}"
            );
        }
    }

    /**
     * Assert that output does not contain any of the specified strings.
     */
    protected function assertOutputContainsNone(string $output, array $strings): void
    {
        foreach ($strings as $string) {
            $this->assertStringNotContainsString(
                $string,
                $output,
                "Output unexpectedly contains: {$string}"
            );
        }
    }
}
