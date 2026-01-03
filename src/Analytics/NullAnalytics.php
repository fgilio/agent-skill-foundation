<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Analytics;

/**
 * No-op analytics for tests and CI environments.
 */
class NullAnalytics implements AnalyticsInterface
{
    public function track(string $command, array $metadata = []): void
    {
        // Intentionally empty
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
