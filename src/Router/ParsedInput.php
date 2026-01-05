<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Router;

/**
 * Value object for parsed CLI input with lenient option scanning.
 *
 * Contract: `<subcommand> [args...] [options...]`
 * - Args must come before options
 * - Options are scanned from raw argv via scanOption()/hasFlag()
 */
class ParsedInput
{
    /**
     * @param array<int, string> $rawArgv
     * @param array<int, string> $args
     */
    private function __construct(
        private array $rawArgv,
        private ?string $subcommand,
        private array $args
    ) {}

    /**
     * Parse argv into a ParsedInput instance.
     *
     * Extracts subcommand and positional args (stopping at first option).
     * Options are accessed via scanOption()/hasFlag() from raw argv.
     *
     * @param array<int, string> $argv
     */
    public static function fromArgv(array $argv): self
    {
        // Skip binary name (argv[0])
        $tokens = array_slice($argv, 1);

        $subcommand = null;
        $args = [];

        foreach ($tokens as $token) {
            // Stop collecting positionals at first option
            if (str_starts_with($token, '-')) {
                break;
            }

            if ($subcommand === null) {
                $subcommand = $token;
            } else {
                $args[] = $token;
            }
        }

        return new self($argv, $subcommand, $args);
    }

    /**
     * Get the subcommand name (first positional arg).
     */
    public function subcommand(): ?string
    {
        return $this->subcommand;
    }

    /**
     * Get positional args after subcommand (before any options).
     *
     * @return array<int, string>
     */
    public function args(): array
    {
        return $this->args;
    }

    /**
     * Alias for args() - improves readability in some contexts.
     *
     * @return array<int, string>
     */
    public function remainingArgs(): array
    {
        return $this->args;
    }

    /**
     * Get a specific positional arg by index.
     */
    public function arg(int $index, mixed $default = null): mixed
    {
        return $this->args[$index] ?? $default;
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
        return $this->hasFlag('help', 'h') || $this->subcommand === 'help';
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
     *
     * @return array<int, string>
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
     * Get raw argv array.
     *
     * @return array<int, string>
     */
    public function rawArgv(): array
    {
        return $this->rawArgv;
    }

    /**
     * Shift N positional tokens from the front (for nested routing).
     *
     * Removes the current subcommand and optionally N-1 args,
     * making the next positional become the new subcommand.
     */
    public function shift(int $n = 1): self
    {
        // Build new argv: keep binary name, remove N positionals, keep options
        $newArgv = [$this->rawArgv[0]];

        $skipped = 0;
        foreach (array_slice($this->rawArgv, 1) as $token) {
            if (str_starts_with($token, '-')) {
                // Options always preserved
                $newArgv[] = $token;
            } elseif ($skipped < $n) {
                // Skip this positional
                $skipped++;
            } else {
                // Keep remaining positionals
                $newArgv[] = $token;
            }
        }

        return self::fromArgv($newArgv);
    }
}
