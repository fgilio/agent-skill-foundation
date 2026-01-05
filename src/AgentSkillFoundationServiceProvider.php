<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation;

use Fgilio\AgentSkillFoundation\Analytics\AnalyticsInterface;
use Fgilio\AgentSkillFoundation\Analytics\NullAnalytics;
use Fgilio\AgentSkillFoundation\Router\Router;
use Illuminate\Support\ServiceProvider;

final class AgentSkillFoundationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Router as transient (not singleton)
        // This is critical for nested routers - singleton would leak state
        $this->app->bind(Router::class);

        // Default to NullAnalytics - skills override with real Analytics if needed
        $this->app->bind(AnalyticsInterface::class, NullAnalytics::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
