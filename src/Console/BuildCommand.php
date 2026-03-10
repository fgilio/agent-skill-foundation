<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Console;

use Fgilio\AgentSkillFoundation\Build\BoxConfig;
use Illuminate\Console\Command;

/**
 * Builds self-contained binary from Laravel Zero project.
 *
 * Tries php-cli-skill-build toolchain first, falls back to inline build.
 * Strips dev dependencies by default for smaller binaries.
 */
final class BuildCommand extends Command
{
    protected $signature = 'build
        {--no-install : Only build, do not copy to skill root}
        {--keep-dev : Skip stripping dev dependencies (larger binary)}';

    protected $description = 'Build self-contained binary';

    public function handle(): int
    {
        /** @var string $projectDir */
        $projectDir = base_path();
        $skillRoot = dirname($projectDir).'/skill';
        if (! is_dir($skillRoot)) {
            mkdir($skillRoot, 0755, true);
        }
        $microPath = $projectDir.'/buildroot/bin/micro.sfx';
        /** @var string $name */
        $name = config('app.name');

        if (! file_exists($microPath)) {
            $this->error('micro.sfx not found at: '.$microPath);
            $this->line('');
            $this->line('Run these commands first:');
            $this->line('  php-cli-skill-runtime-setup --doctor');
            $this->line('  php-cli-skill-runtime-build');

            return self::FAILURE;
        }

        /** @var list<string> $extraDirs */
        $extraDirs = config('build.extra_dirs', []);
        BoxConfig::write($name, $projectDir.'/box.json', $extraDirs);

        $strippedDev = false;

        try {
            if (! $this->option('keep-dev')) {
                $this->info('Stripping dev dependencies...');
                if (! $this->composerInstall($projectDir, noDev: true)) {
                    $this->warn('Failed to strip dev deps, continuing with full install');
                } else {
                    $strippedDev = true;
                }
            }

            if ($this->findToolchain()) {
                $binaryPath = $this->buildViaToolchain($projectDir);
            } else {
                $this->warn('Build toolchain not found on PATH, using fallback builder');
                $binaryPath = $this->buildInline($projectDir, $microPath, $name);
            }

            if ($binaryPath === null) {
                return self::FAILURE;
            }

            $binarySize = round(filesize($binaryPath) / 1024 / 1024, 2);
            $this->line("  Binary: {$binarySize}MB");

            if (! $this->option('no-install')) {
                if (! $this->installToSkillRoot($binaryPath, $skillRoot, $name)) {
                    return self::FAILURE;
                }
            } else {
                $this->codesignBinary($binaryPath);
            }

            $this->newLine();
            $this->info('Build complete!');

            return self::SUCCESS;
        } finally {
            if ($strippedDev) {
                $this->info('Restoring dev dependencies...');
                $this->composerInstall($projectDir, noDev: false);
            }
        }
    }

    private function buildViaToolchain(string $projectDir): ?string
    {
        $this->info('Building via toolchain...');

        [$exitCode, $output] = $this->shell(sprintf(
            'cd %s && php-cli-skill-build --keep-phar',
            escapeshellarg($projectDir)
        ));

        if ($exitCode !== 0) {
            $this->error('Toolchain build failed:');
            $this->line(implode("\n", $output));

            return null;
        }

        $decoded = json_decode(implode("\n", $output), true);

        if (! is_array($decoded) || ! isset($decoded['path']) || ! is_string($decoded['path'])) {
            $this->error('Invalid JSON from toolchain');
            $this->line(implode("\n", $output));

            return null;
        }

        return $decoded['path'];
    }

    private function buildInline(string $projectDir, string $microPath, string $name): ?string
    {
        $boxPath = $projectDir.'/vendor/laravel-zero/framework/bin/box';
        if (! file_exists($boxPath)) {
            $this->error('Box not found. Run: composer install');

            return null;
        }

        $buildsDir = $projectDir.'/builds';
        if (! is_dir($buildsDir)) {
            mkdir($buildsDir, 0755, true);
        }

        $this->info('Building PHAR...');

        [$exitCode, $output] = $this->shell(sprintf(
            'cd %s && php -d phar.readonly=Off %s compile --config=%s 2>&1',
            escapeshellarg($projectDir),
            escapeshellarg($boxPath),
            escapeshellarg($projectDir.'/box.json')
        ));

        if ($exitCode !== 0) {
            $this->error('Box compile failed:');
            $this->line(implode("\n", $output));

            return null;
        }

        $pharPath = $buildsDir.'/'.$name.'.phar';
        if (! file_exists($pharPath)) {
            $this->error('PHAR not created at: '.$pharPath);

            return null;
        }

        $pharSize = round(filesize($pharPath) / 1024 / 1024, 2);
        $this->line("  PHAR: {$pharSize}MB");

        $this->info('Combining with micro.sfx...');

        $binaryPath = $buildsDir.'/'.$name;
        [$exitCode] = $this->shell(sprintf(
            'cat %s %s > %s && chmod +x %s',
            escapeshellarg($microPath),
            escapeshellarg($pharPath),
            escapeshellarg($binaryPath),
            escapeshellarg($binaryPath)
        ));

        if ($exitCode !== 0 || ! file_exists($binaryPath)) {
            $this->error('Failed to combine binary');

            return null;
        }

        unlink($pharPath);

        return $binaryPath;
    }

    private function installToSkillRoot(string $binaryPath, string $skillRoot, string $name): bool
    {
        $installPath = $skillRoot.'/'.$name;
        $this->info('Installing to skill root...');

        if (! copy($binaryPath, $installPath)) {
            $this->error('Failed to copy to: '.$installPath);

            return false;
        }

        chmod($installPath, 0755);
        $this->codesignBinary($installPath);
        $this->line("  Installed: {$installPath}");

        return true;
    }

    private function codesignBinary(string $path): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return;
        }

        [$exitCode] = $this->shell(sprintf('php-cli-skill-codesign %s', escapeshellarg($path)));

        if ($exitCode !== 0) {
            $this->shell(sprintf('codesign -f -s - --timestamp=none %s 2>&1', escapeshellarg($path)));
        }
    }

    private function findToolchain(): bool
    {
        [$exitCode] = $this->shell('command -v php-cli-skill-build');

        return $exitCode === 0;
    }

    private function composerInstall(string $dir, bool $noDev): bool
    {
        $cmd = sprintf(
            'cd %s && composer install %s --quiet 2>&1',
            escapeshellarg($dir),
            $noDev ? '--no-dev --optimize-autoloader' : ''
        );

        [$exitCode] = $this->shell($cmd);

        return $exitCode === 0;
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function shell(string $command): array
    {
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        return [$exitCode, $output];
    }
}
