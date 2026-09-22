---
paths:
  - 'app/Jobs/**'
---
# Jobs

## Queue slow work
External API calls (LLM chat completions, embedding generation) and heavy processing go through a queued Job, not inline in a request or a Service method called synchronously from a controller. Use the `database` queue connection (this app's configured default — no Redis queue is set up here).

## Job responsibilities and status
A Job that represents a long-running domain process (e.g. `GenerateDocumentEmbeddingsJob`) should own the owning model's status transitions (e.g. `Document.status`: `pending` -> `processing` -> `indexed`/`failed`) via its `handle()` and `failed()` methods, and should be idempotent/retry-safe (e.g. skip already-processed child records) since `$tries > 1` means `handle()` may run more than once for the same input.

## Dispatch from Services, not Controllers
Controllers never dispatch Jobs directly; the Service orchestrating the operation dispatches them (e.g. `DocumentService::indexDocument()` dispatches `GenerateDocumentEmbeddingsJob`, not `ChatController::upload()`).
