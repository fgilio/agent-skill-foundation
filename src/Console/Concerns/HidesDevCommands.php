<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Console\Concerns;

use Phar;

/**
 * Hides development-only commands in production binaries.
 *
 * Uses config('commands.hidden') - NOT setHidden() - because
 * Laravel Zero's Kernel reads that config array to hide commands.
 */
trait HidesDevCommands
{
    /**
     * @param  array<int, class-string>  $commands
     */
    protected function hideDevCommands(array $commands = []): void
    {
        if (! Phar::running() && ! getenv('SKILL_PRODUCTION')) {
            return;
        }

        /** @var array<int, class-string> $hidden */
        $hidden = config('commands.hidden', []);
        config(['commands.hidden' => array_merge($hidden, $commands)]);
    }
}
