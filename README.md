# Agent Skill Foundation

[![Tests](https://github.com/fgilio/agent-skill-foundation/actions/workflows/tests.yml/badge.svg)](https://github.com/fgilio/agent-skill-foundation/actions/workflows/tests.yml)

Foundation package for PHP CLI skills used by AI agents (Claude Code).

## Installation

```bash
composer require fgilio/agent-skill-foundation
```

## Features

- **Router** - Command routing with lenient parsing
- **ParsedInput** - Type-safe CLI argument/option access
- **Analytics** - Local usage tracking (JSONL)
- **OutputsJson** - Standardized JSON output helpers
- **CliOutputSnapshot** - Contract test helper with ANSI stripping

## Quick Start

```php
use Fgilio\AgentSkillFoundation\Router\Router;
use Fgilio\AgentSkillFoundation\Router\ParsedInput;
use Illuminate\Console\Command;

class DefaultCommand extends Command
{
    protected $signature = 'default {args?*}';

    public function __construct(private Router $router)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->router
            ->routes([
                'search' => fn(ParsedInput $p, Command $ctx) => $this->search($p),
                'show' => fn(ParsedInput $p, Command $ctx) => $this->show($p),
            ])
            ->help(fn(Command $ctx) => $this->showHelp())
            ->unknown(fn(ParsedInput $p, Command $ctx) => $this->unknownSubcommand($p))
            ->run($this);
    }

    private function search(ParsedInput $p): int
    {
        $query = implode(' ', $p->remainingArgs());
        $limit = $p->scanOption('limit', 'l', 10);
        $json = $p->wantsJson();

        // ... search logic
        return self::SUCCESS;
    }

    private function unknownSubcommand(ParsedInput $p): int
    {
        $subcommand = $p->subcommand();
        if ($p->wantsJson()) {
            fwrite(STDERR, json_encode(['error' => "Unknown: {$subcommand}"]));
        } else {
            $this->error("Unknown subcommand: {$subcommand}");
        }
        return self::FAILURE;
    }
}
```

## CLI Contract

ParsedInput enforces `<subcommand> [args...] [options...]`:

- **Subcommand** comes first (e.g., `search`)
- **Args** follow the subcommand (e.g., `search foo bar`)
- **Options** come last (e.g., `search foo --limit=10 --json`)

Args stop at the first option token. This prevents option values from leaking into args.

## Lenient Parsing

ParsedInput scans raw argv directly, supporting options that aren't pre-declared:

```php
$p->subcommand();                  // First positional: "search"
$p->args();                        // Remaining positionals before options
$p->arg(0);                        // First arg after subcommand
$p->scanOption('limit', 'l');      // --limit=10, --limit 10, -l 10, -l=10
$p->wantsJson();                   // --json flag
$p->collectOption('attach', 'a');  // Multiple: --attach f1 --attach f2
```

## Nested Routing

For skills with subcommands (e.g., `gccli accounts list`):

```php
private function routeAccounts(ParsedInput $p): int
{
    $shifted = $p->shift(1); // Removes "accounts", now "list" is subcommand

    return app(Router::class)
        ->routes([
            'list' => fn(ParsedInput $p) => $this->accountsList(),
            'add' => fn(ParsedInput $p) => $this->accountsAdd($p),
        ])
        ->runWith($shifted, $this);
}
```

## Analytics

Track command usage locally:

```php
use Fgilio\AgentSkillFoundation\Analytics\Analytics;

$analytics = new Analytics('my-skill');
$analytics->track('search', ['query' => 'foo', 'results' => 42]);
```

Disabled automatically in CI or when `SKILL_ANALYTICS=off`.

## JSON Output

Standardized JSON responses for agent consumption:

```php
use Fgilio\AgentSkillFoundation\Output\OutputsJson;

// Success to stdout
return OutputsJson::jsonOkPretty($ctx, ['results' => $data]);

// Error to stderr
return OutputsJson::jsonError($ctx, 'Not found', ['id' => $id]);

// Conditional based on --json flag
return OutputsJson::maybeJson($ctx, $p->wantsJson(), $data, fn($ctx, $d) =>
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

## License

MIT
