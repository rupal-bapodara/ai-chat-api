# ai-chat-api

**In plain terms:** upload a PDF and chat with it. Ask a question, and the app finds the exact passages in the document that actually answer it, then has an AI model write a reply grounded in that text (with the source passages cited) — instead of the AI guessing from general knowledge or the app doing a dumb keyword search.

**Under the hood:** a Laravel demo of a real LLM + vector search + RAG (Retrieval-Augmented Generation) pipeline: upload a PDF, it's parsed, chunked and embedded, and questions about it are answered by retrieving the most semantically similar chunks — via genuine PostgreSQL + pgvector cosine-similarity search, not keyword matching — and passing them to an LLM as context.

## How it works

```
Upload                                    Chat
──────                                    ────
PDF ──▶ smalot/pdfparser ──▶ pages           question ──▶ HuggingFaceEmbeddingService
    ──▶ DocumentIndexingService::chunkText()                  │ (embed question, 384-dim)
    ──▶ document_chunks rows                                  ▼
    ──▶ GenerateDocumentEmbeddingsJob (queued)        DocumentChunkRepository
            │ HuggingFaceEmbeddingService (embed         (pgvector `<=>` cosine
            │  each chunk, 384-dim)                        distance, HNSW index,
            ▼                                              top-K nearest chunks)
       document_embeddings.embedding                          │
       (real `vector(384)` column)                            ▼
                                                    chunk text injected into
                                                    system prompt ──▶ GroqService
                                                    ──▶ reply + cited sources
```

1. **Upload** (`ChatController::upload` → `DocumentService`): a PDF is stored, text is extracted (`smalot/pdfparser`), split into pages, and chunked (`DocumentIndexingService::chunkText()`, ~800 chars/chunk by default). Chunks are saved immediately; the document's `status` becomes `processing`.
2. **Indexing** (`GenerateDocumentEmbeddingsJob`, queued): for each chunk, `HuggingFaceEmbeddingService` calls the Hugging Face Inference API to generate a real 384-dimension embedding vector, stored in `document_embeddings.embedding` (a Postgres `vector(384)` column with an HNSW index). This runs as a **queued job**, not inline in the upload request, because it's slow external API work — see "Running the app" below, this is the most common thing to trip over locally. Once every chunk is embedded, `status` becomes `indexed`.
3. **Chat** (`ChatController::chat` → `ConversationRagService`): the question is embedded the same way, then `DocumentChunkRepository::findNearestByDocument()` runs an actual pgvector cosine-distance query (`embedding <=> ?::vector`, ordered by the HNSW index) to find the top-K most similar chunks for the selected document. Those chunks are injected into the system prompt sent to Groq, and the reply is returned along with the source chunks it was grounded in.

## Tech stack

| Concern | Choice |
| --- | --- |
| Framework | Laravel 13, PHP 8.3+ |
| Database | PostgreSQL 16 |
| Vector search | [pgvector](https://github.com/pgvector/pgvector) — `vector(384)` column + HNSW index, queried via the `<=>` cosine-distance operator directly in Postgres (not loaded into PHP and scored there) |
| Embeddings | Hugging Face Inference Providers, `hf-inference` provider, feature-extraction pipeline — default model `sentence-transformers/all-MiniLM-L6-v2` (384 dimensions), via `router.huggingface.co` |
| LLM (chat) | Groq, OpenAI-compatible chat completions API |
| PDF parsing | `smalot/pdfparser` |
| Queue | Laravel's database queue driver |

Two other AI providers (`GeminiService`, `HuggingFaceService` for chat) exist in `app/Services/AI/` but are unrouted — kept only for reference (see their docblocks for why).

## Project structure

```
app/
├── Casts/Vector.php                    # pgvector <-> float[] Eloquent cast
├── Exceptions/EmbeddingGenerationException.php
├── Http/
│   ├── Controllers/ChatController.php  # index, upload, delete, chat
│   └── Requests/                       # ChatRequest, DocumentUploadRequest
├── Jobs/GenerateDocumentEmbeddingsJob.php
├── Models/                             # Document, DocumentChunk, DocumentContent,
│                                        # DocumentEmbedding, Conversation, Chat
├── Providers/RepositoryServiceProvider.php
├── Repositories/
│   ├── Contracts/DocumentChunkRepositoryInterface.php
│   └── Eloquent/{BaseRepository,DocumentChunkRepository}.php
└── Services/
    ├── AI/       # GroqService (active LLM), EmbeddingProviderInterface +
    │             # HuggingFaceEmbeddingService (active embeddings),
    │             # GeminiService/HuggingFaceService (reference only, unrouted)
    └── RAG/      # DocumentService (upload/parse), DocumentIndexingService
                  # (chunk/embed/retrieve), ConversationRagService (chat orchestration)
```

See [`.ai/rules/`](.ai/rules/) for the full architecture/coding-standards documentation (layered monolith: Controller → FormRequest → Service → Repository → Model, and when each layer is/isn't warranted).

## Prerequisites

- PHP 8.3+ with the `pgsql`/`pdo_pgsql` extensions
- PostgreSQL 16 with the `pgvector` extension available
- Composer
- A [Groq API key](https://console.groq.com) (free tier available)
- A [Hugging Face token](https://huggingface.co/settings/tokens) with "Inference Providers" permission

## Local setup

1. Install PostgreSQL, `pgvector`, and PHP's Postgres extension. On Ubuntu:
   ```bash
   sudo apt-get install -y postgresql postgresql-16-pgvector php8.4-pgsql
   sudo systemctl enable --now postgresql
   ```
2. Create the app and test databases and enable the extension in both (extensions are per-database):
   ```bash
   sudo -u postgres createdb ai_chat_api
   sudo -u postgres createdb ai_chat_api_testing
   sudo -u postgres psql -d ai_chat_api -c "CREATE EXTENSION vector;"
   sudo -u postgres psql -d ai_chat_api_testing -c "CREATE EXTENSION vector;"
   ```
3. `composer install`
4. `cp .env.example .env` and fill in `DB_PASSWORD`, `GROQ_API_KEY`, `HF_API_TOKEN` (see [Environment variables](#environment-variables) below).
5. `php artisan key:generate`
6. `php artisan migrate` — this also runs the migration that enables the `vector` extension and creates the `document_embeddings.embedding vector(384)` column + HNSW index.

## Running the app

You need **two processes** running at once — this is the single most common thing to trip over:

```bash
php artisan serve       # the web app
php artisan queue:work  # processes GenerateDocumentEmbeddingsJob
```

If `queue:work` isn't running, uploaded documents get stuck at `status = processing` forever (they're parsed and chunked, but never embedded), and any chat question against them will come back with "could not find it in the uploaded document" — not because retrieval is broken, but because there's nothing indexed yet to retrieve. Check `documents.status` in the DB, or `php artisan queue:work --once` to drain one job manually, if a document seems stuck.

Then visit `http://localhost:8000`, upload a PDF, wait for it to show as indexed, and ask it a question.

## Environment variables

| Variable | Purpose | Default |
| --- | --- | --- |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Postgres connection | `pgsql` / `127.0.0.1` / `5432` / `ai_chat_api` / `postgres` |
| `QUEUE_CONNECTION` | Must stay queue-backed (`database`) for `GenerateDocumentEmbeddingsJob` to run async | `database` |
| `GROQ_API_KEY`, `GROQ_MODEL` | Chat completion provider (see `config/services.php`) | model: `openai/gpt-oss-120b` |
| `HF_API_TOKEN` | Hugging Face token, used for both embeddings and the reference-only `HuggingFaceService` chat class | — |
| `EMBEDDING_PROVIDER`, `EMBEDDING_MODEL`, `EMBEDDING_DIMENSIONS`, `HF_INFERENCE_ENDPOINT` | Embedding provider config (see `config/embeddings.php`) | `huggingface` / `sentence-transformers/all-MiniLM-L6-v2` / `384` / `https://router.huggingface.co/hf-inference/models` |
| `RAG_MAX_UPLOAD_SIZE` | Max PDF upload size (KB) | `2048` |
| `RAG_CHUNK_SIZE` | Max characters per chunk | `800` |
| `RAG_TOP_K` | Chunks retrieved per question | `5` |
| `RAG_DOCUMENTS_PATH` | Storage disk subpath for uploaded PDFs | `documents` |

`EMBEDDING_DIMENSIONS` must match the `vector(384)` column size in the migration if you ever change the embedding model — they're not automatically kept in sync.

## Routes

| Method | Path | Controller action | Purpose |
| --- | --- | --- | --- |
| GET | `/` | `ChatController::index` | Chat UI + document list |
| POST | `/upload` | `ChatController::upload` | Upload one or more PDFs |
| DELETE | `/documents/{document}` | `ChatController::delete` | Remove a document and its file |
| POST | `/chat` | `ChatController::chat` | Ask a question (`message`, optional `conversation_id`, optional `document_id` to scope retrieval to one document) |

## Tests

```bash
vendor/bin/phpunit
```

Tests run against a real `ai_chat_api_testing` Postgres database (vector columns aren't supported on SQLite, so the suite requires Postgres — see `phpunit.xml`). Coverage includes the embedding service (HTTP-faked), the pgvector repository query (hand-crafted vectors, real DB), and a full upload → embed → retrieve → chat feature test.

## Coding standards

See [`.ai/rules/`](.ai/rules/) for this repo's architecture and coding conventions (layered monolith: Controller → FormRequest → Service → Repository → Model, when to add a Repository, job/config/resource conventions).
