<?php

declare(strict_types=1);

use Fgilio\AgentSkillFoundation\Analytics\Analytics;
use Fgilio\AgentSkillFoundation\Analytics\JsonlStorage;
use Fgilio\AgentSkillFoundation\Analytics\NullAnalytics;

describe('NullAnalytics', function () {
    it('does nothing on track', function () {
        $analytics = new NullAnalytics();

        // Should not throw
        $analytics->track('test', ['key' => 'value']);

        expect($analytics->isEnabled())->toBe(false);
    });
});

describe('JsonlStorage', function () {
    beforeEach(function () {
        $this->tempFile = sys_get_temp_dir() . '/test-' . uniqid() . '.jsonl';
        $this->storage = new JsonlStorage($this->tempFile);
    });

    afterEach(function () {
        if (file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
    });

    it('appends records', function () {
        $this->storage->append(['command' => 'test1']);
        $this->storage->append(['command' => 'test2']);

        $records = $this->storage->read();

        expect($records)->toHaveCount(2);
        expect($records[0]['command'])->toBe('test1');
        expect($records[1]['command'])->toBe('test2');
    });

    it('counts records', function () {
        $this->storage->append(['a' => 1]);
        $this->storage->append(['b' => 2]);
        $this->storage->append(['c' => 3]);

        expect($this->storage->count())->toBe(3);
    });

    it('clears storage', function () {
        $this->storage->append(['test' => true]);
        expect($this->storage->count())->toBe(1);

        $this->storage->clear();
        expect($this->storage->count())->toBe(0);
    });

    it('returns empty array for missing file', function () {
        $storage = new JsonlStorage('/nonexistent/path.jsonl');

        expect($storage->read())->toBe([]);
        expect($storage->count())->toBe(0);
    });

    it('exposes path', function () {
        expect($this->storage->path())->toBe($this->tempFile);
    });
});

describe('Analytics', function () {
    beforeEach(function () {
        $this->tempFile = sys_get_temp_dir() . '/analytics-test-' . uniqid() . '.jsonl';
    });

    afterEach(function () {
        if (file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
    });

    it('tracks commands with metadata', function () {
        $analytics = new Analytics('test-skill', $this->tempFile, true);

        $analytics->track('search', ['query' => 'foo']);

        $records = $analytics->storage()->read();

        expect($records)->toHaveCount(1);
        expect($records[0]['skill'])->toBe('test-skill');
        expect($records[0]['command'])->toBe('search');
        expect($records[0]['query'])->toBe('foo');
        expect($records[0])->toHaveKey('timestamp');
    });

    it('does nothing when disabled', function () {
        $analytics = new Analytics('test-skill', $this->tempFile, false);

        $analytics->track('search');

        expect($analytics->storage()->count())->toBe(0);
        expect($analytics->isEnabled())->toBe(false);
    });

    it('creates disabled instance via factory', function () {
        $analytics = Analytics::disabled('my-skill');

        expect($analytics->isEnabled())->toBe(false);
    });
});
