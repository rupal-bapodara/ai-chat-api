# ai-chat-api

A Laravel demo of a real LLM + vector search + RAG pipeline: upload a PDF, it's chunked and embedded, and questions are answered by retrieving the most similar chunks (via PostgreSQL + pgvector cosine similarity) and passing them to an LLM as context.

## Stack

- Laravel 13 / PHP 8.3+
- **PostgreSQL 16 + [pgvector](https://github.com/pgvector/pgvector)** — chunk embeddings are stored as real `vector(384)` columns with an HNSW index, queried via cosine distance (`<=>`), not loaded into PHP and scored there.
- **Groq** (OpenAI-compatible chat completions) for the LLM.
- **Hugging Face Inference Providers** (`router.huggingface.co`, `hf-inference` provider, feature-extraction pipeline) for embeddings — default model `sentence-transformers/all-MiniLM-L6-v2` (384 dimensions).

## Local setup

1. Install PostgreSQL 16, the `pgvector` extension, and PHP's `pgsql`/`pdo_pgsql` extensions. On Ubuntu:
   ```bash
   sudo apt-get install -y postgresql postgresql-16-pgvector php8.4-pgsql
   sudo systemctl enable --now postgresql
   ```
2. Create the app and test databases and enable the extension in both:
   ```bash
   sudo -u postgres createdb ai_chat_api
   sudo -u postgres createdb ai_chat_api_testing
   sudo -u postgres psql -d ai_chat_api -c "CREATE EXTENSION vector;"
   sudo -u postgres psql -d ai_chat_api_testing -c "CREATE EXTENSION vector;"
   ```
3. Copy `.env.example` to `.env`, fill in `DB_*`, `GROQ_API_KEY`, and `HF_API_TOKEN` (a Hugging Face token with "Inference Providers" permission — see [Inference Providers docs](https://huggingface.co/docs/inference-providers)).
4. `composer install`, `php artisan key:generate`, `php artisan migrate`.
5. **Run a queue worker** — embedding generation happens in a queued job (`GenerateDocumentEmbeddingsJob`), not inline in the upload request:
   ```bash
   php artisan queue:work
   ```
   Without this running, uploaded documents stay stuck at `status = processing` and are never indexed.
6. `php artisan serve` and visit the app. Upload a PDF, wait for it to reach `indexed` status, then ask it a question.

## Tests

```bash
vendor/bin/phpunit
```

Tests run against the `ai_chat_api_testing` Postgres database (vector columns aren't supported on SQLite, so the whole suite requires Postgres).

## Coding standards

See [`.ai/rules/`](.ai/rules/) for this repo's architecture and coding conventions (layered monolith: Controller → FormRequest → Service → Repository → Model).
