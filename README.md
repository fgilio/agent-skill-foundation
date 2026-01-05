# Agent Skill Foundation

[![Tests](https://github.com/fgilio/agent-skill-foundation/actions/workflows/tests.yml/badge.svg)](https://github.com/fgilio/agent-skill-foundation/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/fgilio/agent-skill-foundation)](https://packagist.org/packages/fgilio/agent-skill-foundation)

Foundation package for PHP CLI skills used by AI agents (Claude Code).

## Installation

```bash
composer require fgilio/agent-skill-foundation
```

## Features

- **Native Artisan Routing** - Use Laravel Zero's built-in command routing (recommended)
- **Global `--json` Option** - Register `--json` as an application-level option
- **JSON Exception Renderer** - Output JSON errors for parse-time failures
- **Analytics** - Local usage tracking via console events
- **AgentCommand** - Trait with JSON output helpers for commands
- **OutputsJson** - Standardized JSON output helpers

## Quick Start (Native Routing)

Use Laravel Zero's native command routing. Commands self-describe via `$signature`:

```php
// app/Commands/SearchCommand.php
namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Fgilio\AgentSkillFoundation\Console\AgentCommand;

class SearchCommand extends Command
{
    use AgentCommand;

    protected $signature = 'search {query* : Search terms} {--limit=10 : Max results}';
    protected $description = 'Search the index';

    public function handle(): int
    {
        $query = implode(' ', $this->argument('query'));
        $limit = (int) $this->option('limit');

        $results = $this->performSearch($query, $limit);

        if ($this->wantsJson()) {
            return $this->outputJson(['results' => $results]);
        }

        $this->table(['Title', 'URL'], $results);
        return self::SUCCESS;
    }
}
```

### Application Setup

Register global `--json` option in your Application class:

```php
// app/Application.php
namespace App;

use Fgilio\AgentSkillFoundation\Console\RegistersGlobalJsonOption;
use LaravelZero\Framework\Application as BaseApplication;

class Application extends BaseApplication
{
    use RegistersGlobalJsonOption;
}
```

### JSON Exception Handling

Add JSON error rendering to your entrypoint for parse-time failures:

```php
// my-skill (entrypoint)
use Fgilio\AgentSkillFoundation\Console\JsonExceptionRenderer;

try {
    $status = $kernel->handle($input, $output);
} catch (Throwable $e) {
    if (JsonExceptionRenderer::render($e)) {
        exit(JsonExceptionRenderer::exitCode($e));
    }
    throw $e;
}
```

### Analytics via Console Events

Analytics are automatically tracked via console events when you register the service provider. Track additional metadata by injecting Analytics into your commands:

```php
use Fgilio\AgentSkillFoundation\Analytics\Analytics;

public function handle(Analytics $analytics): int
{
    $startTime = microtime(true);

    // ... command logic

    $analytics->track('search', self::SUCCESS, [
        'query' => $query,
        'results' => count($results),
    ], $startTime);

    return self::SUCCESS;
}
```

## AgentCommand Trait

The `AgentCommand` trait provides JSON output helpers:

```php
use Fgilio\AgentSkillFoundation\Console\AgentCommand;

class MyCommand extends Command
{
    use AgentCommand;

    public function handle(): int
    {
        // Check if --json flag is set
        if ($this->wantsJson()) {
            // Output JSON to stdout
            return $this->outputJson(['data' => $results]);
        }

        // Output JSON error to stderr
        return $this->jsonError('Not found', ['id' => $id]);
    }
}
```

## Command Naming

Use Artisan conventions:

| Pattern | Example | Usage |
|---------|---------|-------|
| Single word | `today`, `search` | Common commands |
| Namespaced | `accounts:list`, `gmail:send` | Grouped commands |

```php
// config/commands.php
return [
    'default' => NunoMaduro\LaravelConsoleSummary\SummaryCommand::class,
    'paths' => [app_path('Commands')],
    'hidden' => [
        // Hide internal/dev commands
        App\Commands\BuildCommand::class,
    ],
];
```

## Analytics

Track command usage locally:

```php
use Fgilio\AgentSkillFoundation\Analytics\Analytics;

$analytics = new Analytics('my-skill');
$analytics->track('search', Command::SUCCESS, ['query' => 'foo', 'results' => 42], $startTime);
```

Disabled automatically in CI or when `SKILL_ANALYTICS=off`.

## JSON Output (Static Helpers)

Standardized JSON responses for agent consumption:

```php
use Fgilio\AgentSkillFoundation\Output\OutputsJson;

// Success to stdout
return OutputsJson::jsonOkPretty($ctx, ['results' => $data]);

// Error to stderr
return OutputsJson::jsonError($ctx, 'Not found', ['id' => $id]);

// Conditional based on --json flag
return OutputsJson::maybeJson($ctx, $this->option('json'), $data, fn($ctx, $d) =>
    $ctx->table(['Name', 'Value'], $d)
);
```

## Testing

Contract tests with snapshot comparison:

```php
use Fgilio\AgentSkillFoundation\Testing\CliOutputSnapshot;

$snapshot = new CliOutputSnapshot('tests/snapshots');
$snapshot->assertMatchesSnapshot('help-output', $output);
```

For acceptance tests, use subprocess-based testing with Symfony Process:

```php
use Symfony\Component\Process\Process;

function mySkill(string ...$args): array
{
    $process = new Process(['php', 'my-skill', ...$args], __DIR__.'/../../');
    $process->run();
    return [$process->getExitCode(), $process->getOutput(), $process->getErrorOutput()];
}

it('shows help', function () {
    [$exitCode, $stdout] = mySkill('--help');
    expect($exitCode)->toBe(0);
    expect($stdout)->toContain('USAGE');
});
```

## Development

```bash
composer install
lefthook install  # optional, requires https://github.com/evilmartians/lefthook
```

Git hooks (via lefthook) run PHPStan on pre-push. Install lefthook with `brew install lefthook` (macOS) or see [installation docs](https://github.com/evilmartians/lefthook#install).

## License

MIT
