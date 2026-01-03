<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Concerns;

use Fgilio\AgentSkillFoundation\Router\Router;
use Illuminate\Console\Command;

/**
 * Convenience trait for commands using the Router service.
 */
trait InteractsWithRouter
{
    /**
     * Get a new Router instance.
     */
    protected function router(): Router
    {
        return app(Router::class);
    }

    /**
     * Route using the provided routes array.
     */
    protected function route(array $routes): int
    {
        return $this->router()
            ->routes($routes)
            ->help(fn (Command $ctx, ?string $cmd = null) => $this->showHelp($cmd))
            ->unknown(fn (?string $cmd, Command $ctx) => $this->unknownCommand($cmd))
            ->run($this);
    }

    /**
     * Override this to customize help output.
     */
    protected function showHelp(?string $subcommand = null): int
    {
        fwrite(STDERR, "Usage: <command> [options]\n");
        fwrite(STDERR, "\nOverride showHelp() to customize.\n");

        return self::SUCCESS;
    }

    /**
     * Override this to customize unknown command handling.
     */
    protected function unknownCommand(?string $command): int
    {
        fwrite(STDERR, "Unknown command: {$command}\n");
        fwrite(STDERR, "Run with \"help\" for usage.\n");

        return self::FAILURE;
    }
}
