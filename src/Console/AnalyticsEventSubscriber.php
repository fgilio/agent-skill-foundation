<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Console;

use Fgilio\AgentSkillFoundation\Analytics\AnalyticsInterface;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Events\Dispatcher;

/**
 * Tracks command execution via console events.
 *
 * Register this subscriber in your service provider to automatically
 * track all command invocations without manual instrumentation.
 *
 * @example
 * public function boot(): void
 * {
 *     $this->app->make(AnalyticsEventSubscriber::class)->subscribe(
 *         $this->app->make(Dispatcher::class)
 *     );
 * }
 */
final class AnalyticsEventSubscriber
{
    private AnalyticsInterface $analytics;

    /** @var array<string, float> */
    private array $startTimes = [];

    /** @var list<string> */
    private array $excludedCommands = [
        'list',
        'help',
        'env',
        'about',
    ];

    public function __construct(AnalyticsInterface $analytics)
    {
        $this->analytics = $analytics;
    }

    /**
     * Handle command starting event.
     */
    public function handleCommandStarting(CommandStarting $event): void
    {
        if (! $this->shouldTrack($event->command)) {
            return;
        }

        $this->startTimes[$event->command ?? 'unknown'] = microtime(true);
    }

    /**
     * Handle command finished event.
     */
    public function handleCommandFinished(CommandFinished $event): void
    {
        $command = $event->command ?? 'unknown';

        if (! $this->shouldTrack($command)) {
            return;
        }

        $startTime = $this->startTimes[$command] ?? null;
        $duration = $startTime !== null ? round((microtime(true) - $startTime) * 1000, 2) : null;

        unset($this->startTimes[$command]);

        $metadata = [
            'exit_code' => $event->exitCode,
            'success' => $event->exitCode === 0,
        ];

        if ($duration !== null) {
            $metadata['duration_ms'] = $duration;
        }

        $this->analytics->track($command, $metadata);
    }

    /**
     * Register event listeners.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(CommandStarting::class, [$this, 'handleCommandStarting']);
        $events->listen(CommandFinished::class, [$this, 'handleCommandFinished']);
    }

    /**
     * Check if command should be tracked.
     */
    private function shouldTrack(?string $command): bool
    {
        if ($command === null || $command === '') {
            return false;
        }

        return ! in_array($command, $this->excludedCommands, true);
    }
}
