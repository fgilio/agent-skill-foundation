<?php

declare(strict_types=1);

use Fgilio\AgentSkillFoundation\Testing\CliOutputSnapshot;

describe('CliOutputSnapshot', function () {
    it('strips ANSI codes', function () {
        $output = "\033[32mSuccess\033[0m: Operation completed";
        $stripped = CliOutputSnapshot::stripAnsi($output);

        expect($stripped)->toBe('Success: Operation completed');
    });

    it('strips complex ANSI sequences', function () {
        $output = "\033[1;31mError\033[0m: \033[33mWarning\033[0m text";
        $stripped = CliOutputSnapshot::stripAnsi($output);

        expect($stripped)->toBe('Error: Warning text');
    });

    it('normalizes output', function () {
        $snapshot = new CliOutputSnapshot();
        $output = "\033[32mTest\033[0m\r\n  Line 2  ";

        $normalized = $snapshot->normalizeOutput($output);

        expect($normalized)->toBe("Test\n  Line 2");
    });

    it('applies custom normalizer', function () {
        $snapshot = new CliOutputSnapshot();
        $snapshot->normalizer(fn ($s) => str_replace('FOO', 'BAR', $s));

        $normalized = $snapshot->normalizeOutput('Hello FOO World');

        expect($normalized)->toBe('Hello BAR World');
    });

    it('creates timestamp normalizer', function () {
        $normalizer = CliOutputSnapshot::timestampNormalizer();

        $output = 'Created at 2024-01-15T10:30:00+00:00';
        $normalized = $normalizer($output);

        expect($normalized)->toBe('Created at [TIMESTAMP]');
    });

    it('creates replacement normalizer', function () {
        $normalizer = CliOutputSnapshot::createNormalizer([
            '/v\d+\.\d+\.\d+/' => '[VERSION]',
            'localhost' => '[HOST]',
        ]);

        $output = 'Running v1.2.3 on localhost';
        $normalized = $normalizer($output);

        expect($normalized)->toBe('Running [VERSION] on [HOST]');
    });
});
