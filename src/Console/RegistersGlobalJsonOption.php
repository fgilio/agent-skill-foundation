<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Console;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * Registers --json as a global application option.
 *
 * Use this trait in your Laravel Zero Application class to make
 * --json available to all commands without declaring it per-command.
 *
 * @example
 * class Application extends LaravelZeroApplication
 * {
 *     use RegistersGlobalJsonOption;
 * }
 */
trait RegistersGlobalJsonOption
{
    /**
     * Get the default input definition with --json option.
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(new InputOption(
            name: 'json',
            shortcut: null,
            mode: InputOption::VALUE_NONE,
            description: 'Output as JSON for agent consumption'
        ));

        return $definition;
    }
}
