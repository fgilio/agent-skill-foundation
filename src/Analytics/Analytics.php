<?php

declare(strict_types=1);

namespace Fgilio\AgentSkillFoundation\Analytics;

/**
 * Local analytics tracking for CLI skills.
 *
 * Stores usage data in a JSONL file. Disabled automatically in CI
 * environments or when SKILL_ANALYTICS=off.
 */
class Analytics implements AnalyticsInterface
{
    private JsonlStorage $storage;

    private bool $enabled;

    private string $skillName;

    public function __construct(
        string $skillName,
        ?string $storagePath = null,
        ?bool $enabled = null
    ) {
        $this->skillName = $skillName;

        // Determine storage path
        $path = $storagePath ?? $this->defaultStoragePath();
        $this->storage = new JsonlStorage($path);

        // Determine if enabled
        $this->enabled = $enabled ?? $this->shouldBeEnabled();
    }

    /**
     * Track a command invocation.
     *
     * @param array<string, mixed> $metadata
     */
    public function track(string $command, array $metadata = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $record = [
            'skill' => $this->skillName,
            'command' => $command,
            'timestamp' => date('c'),
            ...$metadata,
        ];

        // Silently fail - never break the skill
        $this->storage->append($record);
    }

    /**
     * Check if analytics is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the underlying storage.
     */
    public function storage(): JsonlStorage
    {
        return $this->storage;
    }

    /**
     * Create a disabled analytics instance.
     */
    public static function disabled(string $skillName = 'unknown'): self
    {
        return new self($skillName, null, false);
    }

    /**
     * Determine if analytics should be enabled by default.
     */
    private function shouldBeEnabled(): bool
    {
        // Disabled in CI
        if (getenv('CI') !== false) {
            return false;
        }

        // Disabled via environment variable
        $setting = getenv('SKILL_ANALYTICS');
        if ($setting === 'off' || $setting === 'false' || $setting === '0') {
            return false;
        }

        return true;
    }

    /**
     * Get the default storage path.
     */
    private function defaultStoragePath(): string
    {
        $xdgDataHome = getenv('XDG_DATA_HOME');
        $home = is_string($_SERVER['HOME'] ?? null) ? $_SERVER['HOME'] : '';

        // Try common writable locations
        $candidates = [
            $xdgDataHome !== false ? $xdgDataHome : $home . '/.local/share',
            $home,
            sys_get_temp_dir(),
        ];

        foreach ($candidates as $base) {
            if ($base !== '' && is_dir($base) && is_writable($base)) {
                return $base . '/.agent-skills/analytics.jsonl';
            }
        }

        // Fallback to temp
        return sys_get_temp_dir() . '/.agent-skills/analytics.jsonl';
    }
}
