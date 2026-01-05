<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Output;

use Illuminate\Console\Command;

/**
 * JSON output helpers for CLI skills.
 *
 * Provides standardized JSON responses for agent consumption.
 * Success data goes to stdout, errors go to stderr.
 */
final class OutputsJson
{
    /**
     * Output successful JSON response to stdout.
     */
    public static function jsonOk(Command $ctx, mixed $data, int $options = 0): int
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | $options;

        $json = json_encode($data, $flags);
        $ctx->line($json !== false ? $json : '{}');

        return Command::SUCCESS;
    }

    /**
     * Output successful JSON response with pretty printing.
     */
    public static function jsonOkPretty(Command $ctx, mixed $data): int
    {
        return self::jsonOk($ctx, $data, JSON_PRETTY_PRINT);
    }

    /**
     * Output error JSON response to stderr.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function jsonError(Command $ctx, string $message, array $meta = [], int $exitCode = 1): int
    {
        $response = ['error' => $message, ...$meta];
        $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        fwrite(STDERR, $json."\n");

        return $exitCode;
    }

    /**
     * Output validation error response.
     *
     * @param  array<string, string|array<int, string>>  $errors
     */
    public static function jsonValidationError(Command $ctx, array $errors): int
    {
        return self::jsonError($ctx, 'Validation failed', ['errors' => $errors], 2);
    }

    /**
     * Output not found error response.
     */
    public static function jsonNotFound(Command $ctx, string $resource, ?string $identifier = null): int
    {
        $message = $identifier
            ? "Unable to find {$resource}: {$identifier}"
            : "{$resource} not found";

        return self::jsonError($ctx, $message, [], 1);
    }

    /**
     * Conditionally output as JSON or plain text based on ParsedInput.
     */
    public static function maybeJson(
        Command $ctx,
        bool $wantsJson,
        mixed $data,
        ?callable $plainFormatter = null
    ): int {
        if ($wantsJson) {
            return self::jsonOkPretty($ctx, $data);
        }

        if ($plainFormatter) {
            $plainFormatter($ctx, $data);
        } else {
            $ctx->line(is_string($data) ? $data : print_r($data, true));
        }

        return Command::SUCCESS;
    }
}
