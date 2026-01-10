# Matching Service API

A Symfony 6 backend that provides a matching system powered by OpenAI embeddings. The stack runs entirely in Docker with PostgreSQL for persistence and uses NelmioApiDocBundle to expose interactive Swagger documentation.

## Table of Contents
- [Getting Started](#getting-started)
- [Environment](#environment)
- [API Documentation](#api-documentation)
- [Code Quality](#code-quality)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [Embedding & Matching](#embedding--matching)
- [Contributing](#contributing)
- [License](#license)

## Getting Started

### Clone the repository
```bash
git clone <repository-url>
cd matching-service
```

### Start Docker services
```bash
docker compose up -d
```

### Access the PHP container
```bash
docker compose exec php bash
```

## Environment

- Copy `.env` to `.env.local` for local overrides. Real environment variables always take precedence. The Symfony defaults live in `.env` inside the `app/` directory.
- `DATABASE_URL` controls the PostgreSQL connection string used by Doctrine. Update it in your `.env.local` if you change credentials or host.
- Set `OPENAI_EMBEDDING_MODEL` to `text-embedding-3-large` to align with the 3,072-dimension vectors expected by the backend.

### Run database migrations
From inside the PHP container (or with the local PHP binary):
```bash
php bin/console doctrine:migrations:migrate
```

## API Documentation
- Open Swagger UI at: `http://localhost:8080/api/docs`
- Open the raw OpenAPI JSON at: `http://localhost:8080/api/docs.json`

## Code Quality

### PHPStan
- Run analysis locally:
```bash
vendor/bin/phpstan analyse
```
- Run analysis in Docker:
```bash
docker compose exec php vendor/bin/phpstan analyse
```

### PHP-CS-Fixer
- Check for issues (dry run):
```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```
- Automatically fix:
```bash
vendor/bin/php-cs-fixer fix
```
- Run inside Docker:
```bash
docker compose exec php vendor/bin/php-cs-fixer fix
```

## Testing
Run the (placeholder) test suite:
```bash
php bin/phpunit
```

## Project Structure
- `/app/src` — Symfony application code.
  - `/app/src/Controller` — HTTP controllers for the API.
  - `/app/src/Service` — Domain services including embedding and matching logic.
- `/app/migrations` — Doctrine migrations.
- `/app/config` — Symfony configuration (routes, packages, services).
- `/docker` — Docker build context and configuration for PHP, Nginx, and supporting services.

## Embedding & Matching
- When a request is created, the raw text is embedded via the configured OpenAI embedding model and stored alongside the request.
- The matching engine performs cosine similarity search over stored embeddings. Use `GET /api/requests/{id}/matches` to retrieve similar requests.
- Run `php bin/console app:embeddings:rebuild-users` after changing embedding models to re-embed all user profiles with the current 3,072-dimension vectors.

## Contributing
1. Create a feature branch from `main`.
2. Commit your changes with clear messages.
3. Open a Pull Request describing your updates and testing.

## License
//