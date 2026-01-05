<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Analytics;

/**
 * Contract for analytics tracking in CLI skills.
 */
interface AnalyticsInterface
{
    /**
     * Track a command invocation.
     *
     * @param array<string, mixed> $metadata
     */
    public function track(string $command, array $metadata = []): void;

    /**
     * Check if analytics is enabled.
     */
    public function isEnabled(): bool;
}
