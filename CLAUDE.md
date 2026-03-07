# Papi Symfony Bridge -- Development Guidelines

## Project Vision

Papi is the best standalone AI agent library in PHP. This package provides the Symfony bridge, integrating papi-core into Symfony applications via a bundle.

## Quick Reference

```bash
composer lint          # Check code style (PHP CS Fixer, PSR-12)
composer lint:fix      # Auto-fix code style
composer analyse       # Static analysis (Psalm level 4)
composer test          # Run tests (Pest)
composer test:coverage # Run tests with 60% minimum coverage
composer ci            # Run all checks (lint + analyse + test:coverage)
```

## Code Standards

- **PHP 8.2+** with `declare(strict_types=1)` in every file
- **PSR-12** coding style, enforced by PHP CS Fixer
- **Psalm level 4** static analysis must pass with zero errors
- **60% minimum test coverage**, enforced in CI and pre-commit hook
- **Pest** for testing with describe/it syntax

## Architecture Rules

- **Symfony bundle** -- provides DI integration, configuration, and service wiring for papi-core
- **Implement core interfaces** -- DoctrineConversationStore implements ConversationStoreInterface, MessengerQueue implements QueueInterface
- **Configuration-driven** -- providers, middleware, and storage are configured via bundle config
- **Optional dependencies** -- doctrine/dbal and symfony/messenger are suggested, not required

## Testing

- Use Pest's `describe()` / `it()` syntax
- Mock Symfony and Doctrine dependencies
- Test configuration tree, extension loading, and service wiring
- Every public method needs test coverage

## Git Workflow

- All checks must pass before committing
- CI runs lint, static analysis, and tests across PHP 8.2-8.5
