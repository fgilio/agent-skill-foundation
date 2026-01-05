<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Concerns;

use Fgilio\AgentSkillFoundation\Analytics\AnalyticsInterface;

/**
 * Convenience trait for commands that track analytics.
 */
trait TracksAnalytics
{
    /**
     * Get the analytics instance.
     */
    protected function analytics(): AnalyticsInterface
    {
        return app(AnalyticsInterface::class);
    }

    /**
     * Track a command invocation.
     */
    protected function trackCommand(string $command, array $metadata = []): void
    {
        $this->analytics()->track($command, $metadata);
    }
}
