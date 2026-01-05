<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Console;

use Fgilio\AgentSkillFoundation\Output\OutputsJson;
use Illuminate\Console\Command;
use Throwable;

/**
 * Trait for commands that support agent-friendly output.
 *
 * Provides standardized JSON output methods using the global --json option.
 * Use this with RegistersGlobalJsonOption for consistent behavior.
 *
 * @mixin Command
 */
trait AgentCommand
{
    /**
     * Check if output should be JSON.
     *
     * Uses the global --json option registered via RegistersGlobalJsonOption.
     */
    protected function wantsJson(): bool
    {
        return $this->option('json') === true;
    }

    /**
     * Output data as JSON to stdout.
     *
     * Wraps data in standard envelope: {"data": ...}
     */
    protected function outputJson(mixed $data): int
    {
        $this->line(json_encode(
            ['data' => $data],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));

        return Command::SUCCESS;
    }

    /**
     * Output raw data as JSON to stdout (no envelope).
     */
    protected function outputJsonRaw(mixed $data): int
    {
        return OutputsJson::jsonOk($this, $data);
    }

    /**
     * Output error as JSON to stderr.
     *
     * @param  array<string, mixed>  $meta  Additional metadata
     */
    protected function jsonError(string $message, array $meta = []): int
    {
        return OutputsJson::jsonError($this, $message, $meta);
    }

    /**
     * Output not found error as JSON.
     */
    protected function jsonNotFound(string $resource, ?string $identifier = null): int
    {
        return OutputsJson::jsonNotFound($this, $resource, $identifier);
    }

    /**
     * Conditionally output as JSON or execute plain formatter.
     *
     * @param  callable(Command, mixed): void  $plainFormatter  Called if not JSON mode
     */
    protected function outputMaybeJson(mixed $data, callable $plainFormatter): int
    {
        if ($this->wantsJson()) {
            return $this->outputJson($data);
        }

        $plainFormatter($this, $data);

        return Command::SUCCESS;
    }

    /**
     * Handle exception with appropriate output format.
     */
    protected function handleException(Throwable $e): int
    {
        if ($this->wantsJson()) {
            return $this->jsonError($e->getMessage(), [
                'type' => 'exception',
                'class' => get_class($e),
            ]);
        }

        $this->error($e->getMessage());

        return Command::FAILURE;
    }
}
