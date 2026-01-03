<?php

declare(strict_types=1);

use Fgilio\AgentSkillFoundation\Router\ParsedInput;
use Fgilio\AgentSkillFoundation\Router\Router;
use Fgilio\AgentSkillFoundation\Testing\TestsCommands;

uses(TestsCommands::class);

describe('Router', function () {
    it('provides fluent interface', function () {
        $router = new Router();

        $result = $router
            ->routes(['test' => fn () => 0])
            ->help(fn () => 0)
            ->unknown(fn () => 1);

        expect($result)->toBeInstanceOf(Router::class);
    });

    it('reports available routes', function () {
        $router = new Router();
        $router->routes([
            'search' => fn () => 0,
            'show' => fn () => 0,
            'list' => fn () => 0,
        ]);

        expect($router->routeNames())->toBe(['search', 'show', 'list']);
    });

    it('checks if route exists', function () {
        $router = new Router();
        $router->routes([
            'search' => fn () => 0,
        ]);

        expect($router->hasRoute('search'))->toBe(true);
        expect($router->hasRoute('missing'))->toBe(false);
    });
});

describe('Router::runWith()', function () {
    it('dispatches to correct handler', function () {
        $router = new Router();
        $called = false;
        $receivedParsed = null;

        $router->routes([
            'test' => function (ParsedInput $p) use (&$called, &$receivedParsed) {
                $called = true;
                $receivedParsed = $p;

                return 0;
            },
        ]);

        // Create a simple mock command using anonymous class
        $command = new class extends \Illuminate\Console\Command {
            protected $name = 'test';
        };

        $parsed = $this->createParsedInput(['test', '--json']);

        $result = $router->runWith($parsed, $command);

        expect($called)->toBe(true);
        expect($result)->toBe(0);
        expect($receivedParsed->wantsJson())->toBe(true);
    });

    it('calls unknown handler for missing routes', function () {
        $router = new Router();
        $unknownCalled = false;
        $unknownCommand = null;

        $router
            ->routes(['existing' => fn () => 0])
            ->unknown(function (?string $cmd) use (&$unknownCalled, &$unknownCommand) {
                $unknownCalled = true;
                $unknownCommand = $cmd;

                return 1;
            });

        $command = new class extends \Illuminate\Console\Command {
            protected $name = 'test';
        };
        $parsed = $this->createParsedInput(['nonexistent']);

        $result = $router->runWith($parsed, $command);

        expect($unknownCalled)->toBe(true);
        expect($unknownCommand)->toBe('nonexistent');
        expect($result)->toBe(1);
    });

    it('calls help handler when help requested', function () {
        $router = new Router();
        $helpCalled = false;

        $router
            ->routes(['test' => fn () => 0])
            ->help(function () use (&$helpCalled) {
                $helpCalled = true;

                return 0;
            });

        $command = new class extends \Illuminate\Console\Command {
            protected $name = 'test';
        };
        $parsed = $this->createParsedInput(['--help']);

        $router->runWith($parsed, $command);

        expect($helpCalled)->toBe(true);
    });
});
