<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Router;

use Illuminate\Console\Command;

/**
 * Command router with lenient parsing for CLI skills.
 *
 * Uses composition over inheritance - inject this service into your
 * DefaultCommand rather than extending a base class.
 */
class Router
{
    /** @var array<string, callable(ParsedInput, Command): int> */
    private array $routes = [];

    /** @var callable|null */
    private $helpCallback = null;

    /** @var callable|null */
    private $unknownCallback = null;

    /**
     * Define routes as subcommand => handler array.
     * Each handler: callable(ParsedInput $p, Command $ctx): int
     *
     * @param array<string, callable(ParsedInput, Command): int> $routes
     */
    public function routes(array $routes): self
    {
        $this->routes = $routes;

        return $this;
    }

    /**
     * Define help callback.
     * Signature: callable(Command $ctx, ?string $subcommand = null): int
     */
    public function help(callable $callback): self
    {
        $this->helpCallback = $callback;

        return $this;
    }

    /**
     * Define unknown subcommand callback.
     * Signature: callable(ParsedInput $p, Command $ctx): int
     */
    public function unknown(callable $callback): self
    {
        $this->unknownCallback = $callback;

        return $this;
    }

    /**
     * Parse and route the command.
     */
    public function run(Command $context): int
    {
        $parsed = $this->parse($context);

        return $this->dispatch($parsed, $context);
    }

    /**
     * Run with pre-parsed input (for nested routers or analytics).
     */
    public function runWith(ParsedInput $parsed, Command $context): int
    {
        return $this->dispatch($parsed, $context);
    }

    /**
     * Parse CLI input - prefers raw argv, falls back to context for tests.
     */
    public function parse(Command $context): ParsedInput
    {
        // Prefer raw argv (preserves all options)
        /** @var array<int, string> $rawArgv */
        $rawArgv = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];

        // Test harness fallback: synthesize from context when no real argv
        if ($rawArgv === [] || count($rawArgv) <= 1) {
            /** @var array<int, string> $contextArgs */
            $contextArgs = (array) $context->argument('args');
            $rawArgv = ['app', ...$contextArgs];
        }

        // Strip "default" token if present (entrypoint artifact)
        $commandName = $context->getName();
        if (isset($rawArgv[1]) && $rawArgv[1] === $commandName) {
            array_splice($rawArgv, 1, 1);
        }

        return ParsedInput::fromArgv($rawArgv);
    }

    /**
     * Get available route names.
     *
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return array_keys($this->routes);
    }

    /**
     * Check if a route exists.
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /**
     * Dispatch parsed input to appropriate handler.
     */
    private function dispatch(ParsedInput $parsed, Command $context): int
    {
        $subcommand = $parsed->subcommand();

        // Help requested or no subcommand
        if ($parsed->wantsHelp() || $subcommand === null) {
            if ($subcommand !== null && $subcommand !== 'help' && isset($this->routes[$subcommand])) {
                return $this->showSubcommandHelp($context, $subcommand);
            }

            if ($this->helpCallback !== null) {
                /** @var int $result */
                $result = ($this->helpCallback)($context, null);

                return $result;
            }

            return $this->defaultHelp($context);
        }

        // Route to handler
        if (isset($this->routes[$subcommand])) {
            /** @var int $result */
            $result = $this->routes[$subcommand]($parsed, $context);

            return $result;
        }

        // Unknown subcommand
        if ($this->unknownCallback !== null) {
            /** @var int $result */
            $result = ($this->unknownCallback)($parsed, $context);

            return $result;
        }

        return $this->defaultUnknown($context, $parsed);
    }

    private function defaultHelp(Command $context): int
    {
        fwrite(STDERR, "Usage: <subcommand> [args...] [options...]\n\n");
        fwrite(STDERR, "Available subcommands:\n");
        foreach (array_keys($this->routes) as $cmd) {
            fwrite(STDERR, "  {$cmd}\n");
        }

        return Command::SUCCESS;
    }

    private function defaultUnknown(Command $context, ParsedInput $parsed): int
    {
        $subcommand = $parsed->subcommand();
        fwrite(STDERR, "Unknown subcommand: {$subcommand}\n\n");
        fwrite(STDERR, "Run with --help to see available subcommands.\n");

        return Command::FAILURE;
    }

    private function showSubcommandHelp(Command $context, string $subcommand): int
    {
        if ($this->helpCallback !== null) {
            /** @var int $result */
            $result = ($this->helpCallback)($context, $subcommand);

            return $result;
        }

        return $this->defaultHelp($context);
    }
}
