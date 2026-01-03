<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Router;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command router with lenient parsing for CLI skills.
 *
 * Uses composition over inheritance - inject this service into your
 * DefaultCommand rather than extending a base class.
 */
class Router
{
    private array $routes = [];

    /** @var callable|null */
    private $helpCallback = null;

    /** @var callable|null */
    private $unknownCallback = null;

    private ?InputDefinition $definition = null;

    /**
     * Define routes as command => handler array.
     * Each handler: callable(ParsedInput $p, Command $ctx): int
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
     * Define unknown command callback.
     * Signature: callable(?string $command, Command $ctx): int
     */
    public function unknown(callable $callback): self
    {
        $this->unknownCallback = $callback;

        return $this;
    }

    /**
     * Define custom input definition for complex options.
     */
    public function definition(InputDefinition $definition): self
    {
        $this->definition = $definition;

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
     * Run with pre-parsed input (for nested routers).
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
        $rawArgv = $_SERVER['argv'] ?? [];

        // Test harness fallback: synthesize from context when no real argv
        if (empty($rawArgv) || count($rawArgv) <= 1) {
            $contextArgs = method_exists($context, 'argument')
                ? (array) $context->argument('args')
                : [];
            $rawArgv = ['app', ...$contextArgs];
        }

        // Strip "default" token if present (entrypoint artifact)
        $commandName = $context->getName();
        if (isset($rawArgv[1]) && $rawArgv[1] === $commandName) {
            array_splice($rawArgv, 1, 1);
        }

        // Extract positional args only (filter out options for ArgvInput)
        $positionals = array_values(array_filter($rawArgv, fn ($arg, $i) => $i === 0 || ! str_starts_with($arg, '-'), ARRAY_FILTER_USE_BOTH
        ));

        // Create ArgvInput with positionals only (never throws on options)
        $definition = $this->definition ?? $this->lenientDefinition();
        $input = new ArgvInput($positionals, $definition);

        // Pass full rawArgv for scanOption() to work
        return new ParsedInput($input, $rawArgv);
    }

    /**
     * Get available route names.
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
        $command = $parsed->command();

        // Help requested
        if ($parsed->wantsHelp()) {
            if ($command && $command !== 'help' && isset($this->routes[$command])) {
                return $this->showCommandHelp($context, $command);
            }

            return $this->helpCallback
                ? ($this->helpCallback)($context, null)
                : $this->defaultHelp($context);
        }

        // Route to handler
        if ($command && isset($this->routes[$command])) {
            return $this->routes[$command]($parsed, $context);
        }

        // Unknown command
        return $this->unknownCallback
            ? ($this->unknownCallback)($command, $context)
            : $this->defaultUnknown($context, $command);
    }

    /**
     * Lenient definition - only capture command + args + help.
     * Other options scanned manually via ParsedInput::scanOption().
     */
    private function lenientDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::OPTIONAL),
            new InputArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL),
            new InputOption('help', 'h', InputOption::VALUE_NONE),
        ]);
    }

    private function defaultHelp(Command $context): int
    {
        fwrite(STDERR, "Usage: <command> [options]\n\n");
        fwrite(STDERR, "Available commands:\n");
        foreach (array_keys($this->routes) as $cmd) {
            fwrite(STDERR, "  {$cmd}\n");
        }

        return Command::SUCCESS;
    }

    private function defaultUnknown(Command $context, ?string $command): int
    {
        fwrite(STDERR, "Unknown command: {$command}\n\n");
        fwrite(STDERR, "Run with \"help\" to see available commands.\n");

        return Command::FAILURE;
    }

    private function showCommandHelp(Command $context, string $command): int
    {
        return $this->helpCallback
            ? ($this->helpCallback)($context, $command)
            : $this->defaultHelp($context);
    }
}
