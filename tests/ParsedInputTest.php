<?php

declare(strict_types=1);

use Fgilio\AgentSkillFoundation\Router\ParsedInput;
use Fgilio\AgentSkillFoundation\Testing\TestsCommands;

uses(TestsCommands::class);

describe('ParsedInput', function () {
    it('extracts subcommand from argv', function () {
        $parsed = $this->createParsedInput(['search', 'query']);

        expect($parsed->subcommand())->toBe('search');
    });

    it('extracts remaining args', function () {
        $parsed = $this->createParsedInput(['search', 'foo', 'bar']);

        expect($parsed->args())->toBe(['foo', 'bar']);
        expect($parsed->remainingArgs())->toBe(['foo', 'bar']);
        expect($parsed->arg(0))->toBe('foo');
        expect($parsed->arg(1))->toBe('bar');
        expect($parsed->arg(2, 'default'))->toBe('default');
    });

    it('stops collecting args at first option', function () {
        $parsed = $this->createParsedInput(['search', 'foo', '--limit', '10']);

        expect($parsed->subcommand())->toBe('search');
        expect($parsed->args())->toBe(['foo']);
        expect($parsed->scanOption('limit'))->toBe('10');
    });

    it('does not include option values in args', function () {
        $parsed = $this->createParsedInput(['search', 'foo', '--limit', '10', '--json']);

        expect($parsed->args())->toBe(['foo']);
        expect($parsed->scanOption('limit'))->toBe('10');
        expect($parsed->wantsJson())->toBe(true);
    });

    it('scans long options', function () {
        $parsed = $this->createParsedInput(['search', '--limit', '10']);

        expect($parsed->scanOption('limit'))->toBe('10');
    });

    it('scans long options with equals', function () {
        $parsed = $this->createParsedInput(['search', '--limit=20']);

        expect($parsed->scanOption('limit'))->toBe('20');
    });

    it('scans boolean options', function () {
        $parsed = $this->createParsedInput(['search', '--json']);

        expect($parsed->hasFlag('json'))->toBe(true);
        expect($parsed->wantsJson())->toBe(true);
    });

    it('scans short options', function () {
        $parsed = $this->createParsedInput(['search', '-l', '5']);

        expect($parsed->scanOption('limit', 'l'))->toBe('5');
    });

    it('scans short options with equals', function () {
        $parsed = $this->createParsedInput(['search', '-l=15']);

        expect($parsed->scanOption('limit', 'l'))->toBe('15');
    });

    it('returns default when option not found', function () {
        $parsed = $this->createParsedInput(['search']);

        expect($parsed->scanOption('limit', 'l', 100))->toBe(100);
    });

    it('detects help flag', function () {
        $parsed = $this->createParsedInput(['--help']);

        expect($parsed->wantsHelp())->toBe(true);
    });

    it('detects help subcommand', function () {
        $parsed = $this->createParsedInput(['help']);

        expect($parsed->wantsHelp())->toBe(true);
    });

    it('collects repeated options', function () {
        $parsed = $this->createParsedInput([
            'send', '--attach', 'file1.txt', '--attach', 'file2.txt',
        ]);

        expect($parsed->collectOption('attach'))->toBe(['file1.txt', 'file2.txt']);
    });

    it('collects repeated short options', function () {
        $parsed = $this->createParsedInput([
            'send', '-a', 'file1.txt', '-a', 'file2.txt',
        ]);

        expect($parsed->collectOption('attach', 'a'))->toBe(['file1.txt', 'file2.txt']);
    });

    it('returns empty array when no repeated options', function () {
        $parsed = $this->createParsedInput(['send']);

        expect($parsed->collectOption('attach'))->toBe([]);
    });

    it('exposes raw argv', function () {
        $parsed = $this->createParsedInput(['search', '--json', 'query']);

        // Raw argv includes the 'app' prefix added by helper
        expect($parsed->rawArgv())->toBe(['app', 'search', '--json', 'query']);
    });

    it('returns null subcommand when no args', function () {
        $parsed = $this->createParsedInput([]);

        expect($parsed->subcommand())->toBeNull();
    });
});

describe('ParsedInput::shift()', function () {
    it('shifts positional args for nested routing', function () {
        $parsed = $this->createParsedInput(['accounts', 'list', '--json']);
        $shifted = $parsed->shift(1);

        expect($shifted->subcommand())->toBe('list');
        expect($shifted->wantsJson())->toBe(true);
    });

    it('shifts multiple positional args', function () {
        $parsed = $this->createParsedInput(['accounts', 'credentials', 'show', 'id123']);
        $shifted = $parsed->shift(2);

        expect($shifted->subcommand())->toBe('show');
        expect($shifted->arg(0))->toBe('id123');
    });

    it('preserves options when shifting', function () {
        $parsed = $this->createParsedInput(['accounts', '--verbose', 'list', '--limit', '5']);
        $shifted = $parsed->shift(1);

        // Note: with args-before-options, --verbose comes before 'list'
        // so 'list' is not considered an arg, it's after options
        // This test verifies option preservation
        expect($shifted->hasFlag('verbose'))->toBe(true);
        expect($shifted->scanOption('limit'))->toBe('5');
    });

    it('returns immutable copy', function () {
        $original = $this->createParsedInput(['accounts', 'list']);
        $shifted = $original->shift(1);

        expect($original->subcommand())->toBe('accounts');
        expect($shifted->subcommand())->toBe('list');
    });
});

describe('ParsedInput::fromArgv()', function () {
    it('creates instance from argv array', function () {
        $parsed = ParsedInput::fromArgv(['mycli', 'search', 'query']);

        expect($parsed->subcommand())->toBe('search');
        expect($parsed->args())->toBe(['query']);
    });

    it('handles empty argv', function () {
        $parsed = ParsedInput::fromArgv(['mycli']);

        expect($parsed->subcommand())->toBeNull();
        expect($parsed->args())->toBe([]);
    });
});
