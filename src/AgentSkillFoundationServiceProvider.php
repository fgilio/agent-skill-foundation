<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation;

use Fgilio\AgentSkillFoundation\Analytics\AnalyticsInterface;
use Fgilio\AgentSkillFoundation\Analytics\NullAnalytics;
use Fgilio\AgentSkillFoundation\Console\AnalyticsEventSubscriber;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

final class AgentSkillFoundationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Default to NullAnalytics - skills override with real Analytics if needed
        $this->app->bind(AnalyticsInterface::class, NullAnalytics::class);

        // Register analytics event subscriber
        $this->app->singleton(AnalyticsEventSubscriber::class, function (Application $app) {
            /** @var AnalyticsInterface $analytics */
            $analytics = $app->make(AnalyticsInterface::class);

            return new AnalyticsEventSubscriber($analytics);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Subscribe to console events for automatic analytics tracking
        if ($this->app->bound(Dispatcher::class)) {
            /** @var AnalyticsEventSubscriber $subscriber */
            $subscriber = $this->app->make(AnalyticsEventSubscriber::class);

            /** @var Dispatcher $events */
            $events = $this->app->make(Dispatcher::class);

            $subscriber->subscribe($events);
        }
    }
}
