<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation;

use Fgilio\AgentSkillFoundation\Analytics\Analytics;
use Fgilio\AgentSkillFoundation\Analytics\AnalyticsInterface;
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
        // Auto-enable analytics for all consumers using their app name
        $this->app->singleton(AnalyticsInterface::class, function () {
            /** @var string $appName */
            $appName = config('app.name');

            return new Analytics($appName);
        });

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
        // Register shared BuildCommand unless consumer overrides it
        if (! class_exists(\App\Commands\BuildCommand::class, false)) {
            $this->commands([Console\BuildCommand::class]);
        }

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
