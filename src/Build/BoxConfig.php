<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Build;

use RuntimeException;

final class BoxConfig
{
    private const array BASE_DIRS = ['app', 'bootstrap', 'config'];

    private const array VENDOR_EXCLUDES = [
        'jolicode/jolinotif/bin',
        'jolicode/jolinotif/tests',
        'jolicode/jolinotif/doc',
        'jolicode/jolinotif/example',
        'jolicode/jolinotif/tools',
        'jolicode/php-os-helper/tools',
        'nesbot/carbon/bin',
        'nesbot/carbon/src/Carbon/Lang',
        'laravel-zero/framework/bin',
        'laravel-zero/foundation/src/Illuminate/Foundation/resources/exceptions',
        'laravel-zero/foundation/src/Illuminate/Foundation/Console/stubs',
        'laravel-zero/foundation/src/Illuminate/Foundation/Testing',
        'laravel-zero/framework/src/Testing',
        'illuminate/support/Testing',
        'illuminate/http/Testing',
        'filp/whoops/src/Whoops/Resources',
        'symfony/error-handler/Resources',
        'symfony/var-dumper/Resources/bin',
        'symfony/var-dumper/Resources/css',
        'symfony/var-dumper/Resources/js',
        'symfony/translation/Resources/bin',
        'symfony/translation/Resources/schemas',
        'psr/http-message/docs',
        'symfony/http-kernel/Resources',
        'nunomaduro/collision/src/Adapters/Laravel/Commands/stubs',
        'symfony/clock/Test',
        'symfony/http-foundation/Test',
        'symfony/mime/Test',
        'symfony/service-contracts/Test',
        'symfony/translation/Test',
        'symfony/translation-contracts/Test',
        'symfony/var-dumper/Test',
    ];

    private const array VENDOR_NOT_NAME = [
        '*.exe',
        '*.dll',
        '*.bat',
        '*.phar',
        '*.md',
        '*.txt',
        '*.rst',
        '*.dist',
        '*.neon',
        '*.stub',
        '*.gif',
        '*.png',
        '*.svg',
        '*.jpg',
        '*.xsd',
        '*.yml',
        '*.yaml',
        '*.css',
        '*.js',
        '*.h',
        '*.lock',
        'LICENSE*',
        'CHANGELOG*',
        'UPGRADE*',
        'castor.php',
        '.gitignore',
        '.gitattributes',
        '.editorconfig',
        '.php-cs-fixer.php',
        '.deepsource.toml',
        '.styleci.yml',
    ];

    /**
     * @param  list<string>  $extraDirs  Additional directories for the first finder (e.g. ['data'])
     * @return array<string, mixed>
     */
    public static function generate(string $appName, array $extraDirs = []): array
    {
        return [
            'chmod' => '0755',
            'finder' => [
                [
                    'in' => [...self::BASE_DIRS, ...$extraDirs],
                ],
                [
                    'in' => ['vendor'],
                    'exclude' => self::VENDOR_EXCLUDES,
                    'notName' => self::VENDOR_NOT_NAME,
                ],
            ],
            'files' => ['composer.json'],
            'exclude-composer-files' => false,
            'compression' => 'GZ',
            'compactors' => [
                'KevinGH\\Box\\Compactor\\Php',
                'KevinGH\\Box\\Compactor\\Json',
            ],
            'exclude-dev-files' => true,
            'main' => $appName,
            'output' => "builds/{$appName}.phar",
            'check-requirements' => false,
        ];
    }

    /**
     * @param  list<string>  $extraDirs
     */
    public static function write(string $appName, string $path = 'box.json', array $extraDirs = []): void
    {
        $json = json_encode(
            self::generate($appName, $extraDirs),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $dir = dirname($path);
        $tmpFile = $dir.'/box.json.'.getmypid().'.tmp';

        if (file_put_contents($tmpFile, $json."\n") === false) {
            throw new RuntimeException("Failed to write box.json to: {$tmpFile}");
        }

        if (! rename($tmpFile, $path)) {
            @unlink($tmpFile);

            throw new RuntimeException("Failed to rename {$tmpFile} to {$path}");
        }
    }
}
