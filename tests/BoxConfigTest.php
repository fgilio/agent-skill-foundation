<?php

declare(strict_types=1);

use Fgilio\AgentSkillFoundation\Build\BoxConfig;

describe('BoxConfig::generate', function () {
    it('derives main and output from app name', function () {
        $config = BoxConfig::generate('my-app');

        expect($config['main'])->toBe('my-app')
            ->and($config['output'])->toBe('builds/my-app.phar');
    });

    it('uses base dirs app, bootstrap, config in first finder', function () {
        $config = BoxConfig::generate('test-app');

        expect($config['finder'][0]['in'])->toBe(['app', 'bootstrap', 'config']);
    });

    it('appends extraDirs to first finder only', function () {
        $config = BoxConfig::generate('test-app', ['data']);

        expect($config['finder'][0]['in'])->toBe(['app', 'bootstrap', 'config', 'data'])
            ->and($config['finder'][1]['in'])->toBe(['vendor']);
    });

    it('includes vendor excludes', function () {
        $config = BoxConfig::generate('test-app');

        expect($config['finder'][1]['exclude'])
            ->toContain('jolicode/jolinotif/bin')
            ->toContain('symfony/var-dumper/Test')
            ->toContain('laravel-zero/foundation/src/Illuminate/Foundation/Testing');
    });

    it('includes compactors', function () {
        $config = BoxConfig::generate('test-app');

        expect($config['compactors'])->toBe([
            'KevinGH\\Box\\Compactor\\Php',
            'KevinGH\\Box\\Compactor\\Json',
        ]);
    });

    it('matches canonical structure', function () {
        $config = BoxConfig::generate('slack-cli');

        expect($config)->toBe([
            'chmod' => '0755',
            'finder' => [
                [
                    'in' => ['app', 'bootstrap', 'config'],
                ],
                [
                    'in' => ['vendor'],
                    'exclude' => [
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
                    ],
                    'notName' => [
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
                    ],
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
            'main' => 'slack-cli',
            'output' => 'builds/slack-cli.phar',
            'check-requirements' => false,
        ]);
    });

    it('produces singlestore-equivalent with data extraDir', function () {
        $config = BoxConfig::generate('singlestore-docs', ['data']);

        expect($config['finder'][0]['in'])->toBe(['app', 'bootstrap', 'config', 'data'])
            ->and($config['main'])->toBe('singlestore-docs')
            ->and($config['output'])->toBe('builds/singlestore-docs.phar');
    });
});

describe('BoxConfig::write', function () {
    it('writes valid JSON with trailing newline', function () {
        $tmpDir = sys_get_temp_dir().'/boxconfig-test-'.getmypid();
        mkdir($tmpDir, 0755, true);
        $path = $tmpDir.'/box.json';

        try {
            BoxConfig::write('test-app', $path);

            $content = file_get_contents($path);
            expect($content)->toEndWith("\n");

            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            expect($decoded['main'])->toBe('test-app');
        } finally {
            @unlink($path);
            @rmdir($tmpDir);
        }
    });
});
