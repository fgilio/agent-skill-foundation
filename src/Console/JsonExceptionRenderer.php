<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Console;

use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\RuntimeException;
use Throwable;

/**
 * Renders console exceptions as JSON when --json flag is present.
 *
 * Use this to guarantee JSON output for parse-time failures (unknown
 * command, unknown option, missing required argument) when agents
 * request JSON output.
 */
final class JsonExceptionRenderer
{
    /**
     * Check if the current invocation wants JSON output.
     *
     * Scans raw argv for --json flag, works even before command parsing.
     */
    public static function wantsJson(): bool
    {
        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        return in_array('--json', $argv, true);
    }

    /**
     * Render an exception as JSON to stderr.
     *
     * Returns true if rendered (caller should exit), false to use default rendering.
     */
    public static function render(Throwable $e): bool
    {
        if (! self::wantsJson()) {
            return false;
        }

        $response = self::formatException($e);
        fwrite(STDERR, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        return true;
    }

    /**
     * Get the appropriate exit code for an exception.
     */
    public static function exitCode(Throwable $e): int
    {
        if ($e instanceof CommandNotFoundException) {
            return 127; // Command not found (shell convention)
        }

        if ($e instanceof RuntimeException) {
            return 1;
        }

        return $e->getCode() > 0 ? $e->getCode() : 1;
    }

    /**
     * Format exception data for JSON output.
     *
     * @return array{error: string, type?: string, suggestions?: list<string>}
     */
    private static function formatException(Throwable $e): array
    {
        $response = ['error' => $e->getMessage()];

        // Add exception type for debugging
        $response['type'] = match (true) {
            $e instanceof CommandNotFoundException => 'command_not_found',
            $e instanceof RuntimeException => 'runtime_error',
            default => 'error',
        };

        // Include alternatives for command not found
        if ($e instanceof CommandNotFoundException) {
            $alternatives = $e->getAlternatives();
            if (! empty($alternatives)) {
                $response['suggestions'] = array_values($alternatives);
            }
        }

        return $response;
    }
}
