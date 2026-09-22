---
paths:
  - 'config/**'
  - 'app/Services/**'
---
# Environment & Config Conventions

## Read config(), never env(), outside config files
`env()` calls belong only inside `config/*.php` files. Services, Jobs, and any other application code must read values via `config('services.groq.key')`, `config('embeddings.huggingface.token')`, etc. — never `env('GROQ_API_KEY')` directly in a constructor. This is a Laravel-standard requirement for `php artisan config:cache` to work correctly in production (an `env()` call outside config files silently returns `null` once config is cached), and it centralizes third-party credentials in `config/services.php` (or a domain-specific config file, e.g. `config/embeddings.php`, `config/rag.php`) as the single source of truth.

## New third-party integrations
When adding a new external provider (AI, embeddings, or otherwise), add its credentials/config to `config/services.php` if it's a simple key/secret, or a new dedicated `config/{concern}.php` file if it needs multiple structured settings (see `config/embeddings.php`, `config/rag.php` as examples).
