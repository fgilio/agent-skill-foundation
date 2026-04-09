<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Console;

use Fgilio\AgentSkillFoundation\Output\OutputsJson;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Trait for commands that support agent-friendly output.
 *
 * Provides standardized JSON output and error handling for CLI skills
 * built on the foundation. Mix this into a base command class and you
 * get:
 *
 * - A centralized {@see execute()} that delegates to Laravel's default
 *   dispatcher (preserving DI, Isolatable, ManuallyFailedException)
 *   inside a try/catch that routes throwables through {@see handleException()}.
 * - A {@see wantsJson()} check against the global --json option that
 *   {@see \Fgilio\AgentSkillFoundation\AgentSkillFoundationServiceProvider}
 *   registers via Artisan::starting().
 * - A {@see outputJson()} that wraps payloads in a standard envelope:
 *   `{"data": ...}`. The envelope is deliberate — it gives agents a
 *   stable top-level key to check, leaves room for future additions
 *   like `meta` / `errors` without breaking parsers, and avoids
 *   collisions with domain keys.
 * - Two extension hooks for per-skill customization:
 *   - {@see prepareJsonData()} — transform data before encoding
 *     (e.g. flattening an Illuminate Collection to an array).
 *   - {@see extractExceptionDetails()} — customize the rendered
 *     message and/or metadata for domain-specific exceptions
 *     (e.g. parsing Google API JSON error bodies).
 *
 * IMPORTANT: commands using this trait MUST NOT declare `{--json}` in
 * their `$signature`. The foundation registers it globally; declaring
 * it per-command causes Symfony's InputDefinition::addOption() to
 * throw LogicException on duplicate definitions.
 *
 * @mixin Command
 */
trait AgentCommand
{
    /**
     * Execute the command with centralized exception handling.
     *
     * Delegates to Laravel's default execute() via parent::execute()
     * so we preserve DI into handle(), Isolatable lock handling, and
     * ManuallyFailedException dispatch. Any Throwable that escapes
     * handle() is routed through {@see handleException()}.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::execute($input, $output);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Check if output should be JSON.
     *
     * Uses the global --json option registered by the foundation
     * service provider.
     */
    protected function wantsJson(): bool
    {
        return $this->option('json') === true;
    }

    /**
     * Output data as JSON to stdout in the standard envelope.
     *
     * Calls {@see prepareJsonData()} first so base classes can
     * transform the payload (e.g. flatten a Collection) without
     * re-overriding this method.
     */
    protected function outputJson(mixed $data): int
    {
        $this->line(json_encode(
            ['data' => $this->prepareJsonData($data)],
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
     * Hook: transform data before JSON encoding.
     *
     * Default is identity. Override in a base class to normalize
     * domain types (e.g. flatten an Illuminate Collection to array).
     */
    protected function prepareJsonData(mixed $data): mixed
    {
        return $data;
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
     * Emit a user-facing failure in the right format for the current
     * mode and return FAILURE. Use this for validation errors, missing
     * config, and other expected failures where throwing an exception
     * would be semantically wrong.
     *
     * @param  array<string, mixed>  $meta  Additional metadata (JSON mode only)
     */
    protected function failWith(string $message, array $meta = []): int
    {
        if ($this->wantsJson()) {
            return $this->jsonError($message, $meta);
        }

        $this->error($message);

        return Command::FAILURE;
    }

    /**
     * Output a "resource not found" error in the appropriate format
     * and return FAILURE. Use inline from handle() when a missing
     * resource is business logic, not an exceptional condition:
     *
     *   if (! $issue) {
     *       return $this->jsonNotFound('issue', $id);
     *   }
     */
    protected function jsonNotFound(string $resource, ?string $identifier = null): int
    {
        $message = $identifier
            ? "Unable to find {$resource}: {$identifier}"
            : "{$resource} not found";

        if ($this->wantsJson()) {
            return OutputsJson::jsonNotFound($this, $resource, $identifier);
        }

        $this->error($message);

        return Command::FAILURE;
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
     * Hook: customize exception rendering for domain-specific errors.
     *
     * Return null to use default behavior (render the exception's
     * raw message). Return an array to replace the message and/or
     * attach structured metadata that will be merged into the JSON
     * error envelope and shown as lines below the error in text mode.
     *
     * Example: gccli's BaseCalendarCommand overrides this to parse
     * Google API error bodies and extract the "enable API" URL into
     * the `meta.enable_url` field.
     *
     * @return array{message: string, meta: array<string, mixed>}|null
     */
    protected function extractExceptionDetails(Throwable $e): ?array
    {
        return null;
    }

    /**
     * Handle exception with appropriate output format.
     *
     * Consults {@see extractExceptionDetails()} first so base classes
     * can customize messages and metadata without overriding this
     * whole method.
     */
    protected function handleException(Throwable $e): int
    {
        $details = $this->extractExceptionDetails($e);
        $message = $details['message'] ?? $e->getMessage();
        /** @var array<string, mixed> $meta */
        $meta = $details['meta'] ?? [];

        if ($this->wantsJson()) {
            return $this->jsonError($message, $meta + [
                'type' => 'exception',
                'class' => get_class($e),
            ]);
        }

        $this->error($message);

        foreach ($meta as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $this->line("{$key}: {$value}");
            }
        }

        return Command::FAILURE;
    }
}
