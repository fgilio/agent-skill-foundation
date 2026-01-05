<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Testing;

use Fgilio\AgentSkillFoundation\Router\ParsedInput;

/**
 * Test helpers for CLI commands.
 */
trait TestsCommands
{
    /**
     * Create a ParsedInput from an argv array for testing.
     * Pass argv WITHOUT the binary name - it will be added.
     */
    protected function createParsedInput(array $argv): ParsedInput
    {
        // Ensure first element is binary name (add if not present)
        if (empty($argv)) {
            $argv = ['app'];
        } elseif (! str_starts_with($argv[0], '/') && $argv[0] !== 'app') {
            array_unshift($argv, 'app');
        }

        return ParsedInput::fromArgv($argv);
    }

    /**
     * Create a ParsedInput from a command string.
     */
    protected function parsedInputFromString(string $commandLine): ParsedInput
    {
        $argv = str_getcsv($commandLine, ' ');

        return $this->createParsedInput($argv);
    }

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
