<?php

declare(strict_types=1);

use Fgilio\AgentSkillFoundation\Extensions\ExtensionChecker;

describe('ExtensionChecker', function () {
    it('returns empty array when composer.json does not exist', function () {
        $missing = ExtensionChecker::getMissingExtensions('/nonexistent/composer.json');

        expect($missing)->toBe([]);
    });

    it('returns empty array when no ext-* requirements', function () {
        $path = tempnam(sys_get_temp_dir(), 'ext-test-');
        file_put_contents($path, json_encode([
            'require' => [
                'php' => '^8.5',
                'laravel-zero/framework' => '^12.0',
            ],
        ]));

        $missing = ExtensionChecker::getMissingExtensions($path);

        expect($missing)->toBe([]);

        unlink($path);
    });

    it('returns empty array when all required extensions are loaded', function () {
        $path = tempnam(sys_get_temp_dir(), 'ext-test-');
        file_put_contents($path, json_encode([
            'require' => [
                'php' => '^8.5',
                'ext-curl' => '*',
                'ext-mbstring' => '*',
            ],
        ]));

        $missing = ExtensionChecker::getMissingExtensions($path);

        expect($missing)->toBe([]);

        unlink($path);
    });

    it('detects missing extensions', function () {
        $path = tempnam(sys_get_temp_dir(), 'ext-test-');
        file_put_contents($path, json_encode([
            'require' => [
                'php' => '^8.5',
                'ext-curl' => '*',
                'ext-nonexistent_extension_xyz' => '*',
            ],
        ]));

        $missing = ExtensionChecker::getMissingExtensions($path);

        expect($missing)->toBe(['nonexistent_extension_xyz']);

        unlink($path);
    });

    it('returns empty array for invalid JSON', function () {
        $path = tempnam(sys_get_temp_dir(), 'ext-test-');
        file_put_contents($path, 'not json');

        $missing = ExtensionChecker::getMissingExtensions($path);

        expect($missing)->toBe([]);

        unlink($path);
    });
});
