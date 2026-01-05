# Agent Skill Foundation

## Code Style

### Type Safety
- Prefer native PHP types over PHPDoc annotations
- Use typed properties, parameters, and return types
- For arrays, use PHPDoc only when native types are insufficient (e.g., `array<string, mixed>`)

### PHPStan Ignores
Any `ignoreErrors` in `phpstan.neon.dist` must include a comment explaining why.
