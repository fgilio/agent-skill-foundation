<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Router;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * Value object for parsed CLI input with lenient option scanning.
 */
class ParsedInput
{
    public function __construct(
        private ArgvInput $input,
        private array $rawArgv
    ) {}

    /**
     * Get the command name (first positional arg).
     */
    public function command(): ?string
    {
        return $this->input->getArgument('command');
    }

    /**
     * Alias for command() - improves readability.
     */
    public function firstArg(): ?string
    {
        return $this->command();
    }

    /**
     * Get remaining positional args after command.
     */
    public function args(): array
    {
        return $this->input->getArgument('args') ?? [];
    }

    /**
     * Alias for args() - improves readability.
     */
    public function remainingArgs(): array
    {
        return $this->args();
    }

    /**
     * Get a specific positional arg by index.
     */
    public function arg(int $index, mixed $default = null): mixed
    {
        return $this->args()[$index] ?? $default;
    }

    /**
     * Scan raw argv for an option value (lenient parsing).
     * Handles: --option=value, --option value, -o value, -o=value
     */
    public function scanOption(string $long, ?string $short = null, mixed $default = null): mixed
    {
        $argv = $this->rawArgv;

        for ($i = 0; $i < count($argv); $i++) {
            $arg = $argv[$i];

            // --option=value
            if (str_starts_with($arg, "--{$long}=")) {
                return substr($arg, strlen("--{$long}="));
            }
            // --option value
            if ($arg === "--{$long}" && isset($argv[$i + 1]) && ! str_starts_with($argv[$i + 1], '-')) {
                return $argv[$i + 1];
            }
            // --option (boolean)
            if ($arg === "--{$long}") {
                return true;
            }
            // Short form: -o=value
            if ($short && str_starts_with($arg, "-{$short}=")) {
                return substr($arg, strlen("-{$short}="));
            }
            // Short form: -o value
            if ($short && $arg === "-{$short}" && isset($argv[$i + 1]) && ! str_starts_with($argv[$i + 1], '-')) {
                return $argv[$i + 1];
            }
            // Short form: -o (boolean)
            if ($short && $arg === "-{$short}") {
                return true;
            }
        }

        return $default;
    }

    /**
     * Check if a boolean flag is present (no value).
     */
    public function hasFlag(string $long, ?string $short = null): bool
    {
        foreach ($this->rawArgv as $arg) {
            if ($arg === "--{$long}") {
                return true;
            }
            if ($short && $arg === "-{$short}") {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user wants help.
     */
    public function wantsHelp(): bool
    {
        return $this->hasFlag('help', 'h') || $this->command() === 'help';
    }

    /**
     * Check if user wants JSON output.
     */
    public function wantsJson(): bool
    {
        return $this->hasFlag('json');
    }

    /**
     * Collect all values for a repeatable option from raw argv.
     * Handles: --attach file1 --attach file2, -a file1 -a file2
     */
    public function collectOption(string $long, ?string $short = null): array
    {
        $values = [];
        $argv = array_slice($this->rawArgv, 1);

        for ($i = 0; $i < count($argv); $i++) {
            $arg = $argv[$i];

            // Long form: --option value
            if ($arg === "--{$long}" && isset($argv[$i + 1]) && ! str_starts_with($argv[$i + 1], '-')) {
                $values[] = $argv[++$i];
            }
            // Long form: --option=value
            elseif (str_starts_with($arg, "--{$long}=")) {
                $values[] = substr($arg, strlen("--{$long}="));
            }
            // Short form: -o value
            elseif ($short && $arg === "-{$short}" && isset($argv[$i + 1]) && ! str_starts_with($argv[$i + 1], '-')) {
                $values[] = $argv[++$i];
            }
            // Short form: -o=value
            elseif ($short && str_starts_with($arg, "-{$short}=")) {
                $values[] = substr($arg, strlen("-{$short}="));
            }
        }

        return $values;
    }

    /**
     * Get the underlying Symfony input.
     */
    public function symfonyInput(): ArgvInput
    {
        return $this->input;
    }

    /**
     * Get raw argv array.
     */
    public function rawArgv(): array
    {
        return $this->rawArgv;
    }

    /**
     * Shift N tokens from the front (for nested routing).
     * Keeps argv[0] (binary name), removes next N positional args.
     */
    public function shift(int $n = 1): self
    {
        $newArgv = $this->rawArgv;

        // Find and remove N positional args (not options)
        $removed = 0;
        $i = 1;
        while ($removed < $n && $i < count($newArgv)) {
            if (! str_starts_with($newArgv[$i], '-')) {
                array_splice($newArgv, $i, 1);
                $removed++;
            } else {
                // Skip option and its value if present
                $i++;
                if ($i < count($newArgv) && ! str_starts_with($newArgv[$i], '-')) {
                    $i++;
                }
            }
        }

        // Re-create ArgvInput with shifted positionals
        $positionals = array_values(array_filter($newArgv, fn ($arg, $i) => $i === 0 || ! str_starts_with($arg, '-'), ARRAY_FILTER_USE_BOTH
        ));

        $definition = new InputDefinition([
            new InputArgument('command', InputArgument::OPTIONAL),
            new InputArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL),
            new InputOption('help', 'h', InputOption::VALUE_NONE),
        ]);

        $input = new ArgvInput($positionals, $definition);

        return new self($input, $newArgv);
    }
}
