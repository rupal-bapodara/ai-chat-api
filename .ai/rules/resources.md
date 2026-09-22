---
paths:
  - 'app/Http/Resources/**'
---
# Resources

## Current state
This app currently returns hand-built arrays from `response()->json()` in `ChatController` rather than API Resource classes. That is acceptable for the existing small, stable response shapes (`chat()`'s reply/conversation_id/sources), but is not the pattern for new JSON-returning endpoints going forward.

## New endpoints use Resources
Any new JSON-returning endpoint, or any endpoint whose response shape changes meaningfully (e.g. a future `documents.show`/status-polling endpoint), must use an `app/Http/Resources/{Name}Resource.php` class — never expose password/hash/token fields.

## Error-status conventions
- 422: validation failures (handled automatically by Form Request).
- 401/403: authentication/authorization.
- 404: not found.
- 502/503: upstream dependency failure (e.g. Groq/Hugging Face API unreachable or erroring) — do not collapse these into 400, which implies a client mistake.
- 500: unexpected server error, no stack trace/SQL/internal details leaked in the response body.
- Don't wrap controller actions in a generic catch-all try/catch — only catch what can be meaningfully handled (e.g. a specific `EmbeddingGenerationException`), and log the rest for the framework's default handler to convert into a 500.
