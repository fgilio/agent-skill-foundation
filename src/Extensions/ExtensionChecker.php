<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Extensions;

use Fgilio\AgentSkillFoundation\Console\JsonExceptionRenderer;
use Phar;

/**
 * Validates that PHP extensions declared in composer.json are loaded.
 *
 * Only runs inside compiled PHAR binaries (Phar::running() is non-empty).
 * Reads ext-* keys from the require section and checks extension_loaded().
 */
final class ExtensionChecker
{
    /**
     * Check required extensions and exit with a clear error if any are missing.
     *
     * @param  string  $composerJsonPath  Absolute path to composer.json
     */
    public static function check(string $composerJsonPath): void
    {
        if (Phar::running() === '') {
            return;
        }

        $missing = self::getMissingExtensions($composerJsonPath);

        if ($missing === []) {
            return;
        }

        $names = implode(', ', $missing);

        if (JsonExceptionRenderer::wantsJson()) {
            fwrite(STDERR, json_encode([
                'error' => "Missing required PHP extensions: {$names}",
                'type' => 'missing_extensions',
                'missing' => $missing,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        } else {
            fwrite(STDERR, "Missing required PHP extensions: {$names}\n");
            fwrite(STDERR, "These extensions must be compiled into micro.sfx. See OPTIMIZATION_RUNBOOK.md.\n");
        }

        exit(1);
    }

    /**
     * @return list<string>
     */
    public static function getMissingExtensions(string $composerJsonPath): array
    {
        if (! file_exists($composerJsonPath)) {
            return [];
        }

        $contents = file_get_contents($composerJsonPath);
        if ($contents === false) {
            return [];
        }

        /** @var array{require?: array<string, string>}|null $composer */
        $composer = json_decode($contents, true);
        if (! is_array($composer) || ! isset($composer['require'])) {
            return [];
        }

        $missing = [];

        foreach ($composer['require'] as $package => $version) {
            if (! str_starts_with($package, 'ext-')) {
                continue;
            }

            $extension = mb_substr($package, 4);

            if (! extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        return $missing;
    }
}
