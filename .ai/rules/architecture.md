---
paths:
  - 'app/Http/Controllers/**'
  - 'app/Http/Requests/**'
  - 'app/Services/**'
  - 'app/Repositories/**'
  - 'app/Models/**'
  - 'app/Casts/**'
---
# Layered Monolith Architecture

## Request flow: Controller -> FormRequest -> Service -> Repository -> Model
This app is a layered monolith. New feature code must follow this flow, not bypass it:

- **Controller** (`app/Http/Controllers`): HTTP only. Validate via a Form Request, delegate to a Service, return a Resource/view/json response. No business logic, no direct Eloquent queries, no manually-instantiated dependencies.
- **Form Request** (`app/Http/Requests/{Action}Request.php`, flat — see naming note below): validation rules and authorization for that action.
- **Service** (`app/Services/{Domain}/{Name}Service.php`, e.g. `app/Services/AI/GroqService.php`, `app/Services/RAG/ConversationRagService.php`): business logic and orchestration, domain-subnamespaced. Wraps multi-step writes in DB transactions, dispatches Jobs for slow work, calls repositories (or the model directly for trivial CRUD). No HTTP-specific logic/responses.
- **Repository** (`app/Repositories/Contracts/{Name}RepositoryInterface.php` + `app/Repositories/Eloquent/{Name}Repository.php`, extending `App\Repositories\Eloquent\BaseRepository`): only add one when a model has non-trivial/reused query logic (e.g. the pgvector cosine-similarity search in `DocumentChunkRepository`) or needs independent mocking. Trivial CRUD models don't need a repository — the Service can use the Eloquent model directly.
- **Model** (`app/Models`): relations, casts, scopes only.

## Naming convention: domain-subnamespaced Services, not flat
Unlike a flat `app/Services/{Domain}Service.php` layout, this app groups services by domain subdirectory: `app/Services/AI/*` for LLM/embedding provider integrations, `app/Services/RAG/*` for retrieval/indexing/conversation orchestration. New domains follow the same pattern: `app/Services/{Domain}/{Name}Service.php`. Do not add new flat `app/Services/{X}Service.php` files once a domain subdirectory exists for that concern.

## Naming convention: Form Requests stay flat, not Store/Update
Keep `app/Http/Requests/{Action}Request.php` (e.g. `ChatRequest`, `DocumentUploadRequest`) rather than `app/Http/Requests/{Domain}/Store{X}Request.php`. This app's actions are custom verbs (chat, upload, delete), not CRUD Store/Update pairs per resource — a Store/Update convention doesn't fit and would force artificial names. Revisit only if a single domain accumulates 3+ requests where a subdirectory would meaningfully reduce clutter.

## Wiring new repositories
Bind every new repository interface -> implementation in `App\Providers\RepositoryServiceProvider::$bindings`, not in ad-hoc service providers or inline `app()->bind()` calls.

## Controllers stay thin
No business logic, no direct `Model::where()`/`Model::create()` calls, no manually-instantiated dependencies (use DI). Delegate to a Service and validate via a Form Request. Return only Resources/views/json responses.

## Services own business logic and transactions
No HTTP-specific logic and no HTTP responses returned from a Service. Wrap multi-step DB writes in a transaction. External API calls and heavy processing belong behind a queued Job — see jobs.md — not inline in a Service method called synchronously from a request.

## Repositories are Eloquent-only, no business rules
Repositories do data access only — never business rules. Inject repository interfaces into services (never concrete classes). Don't create one for a model with only trivial CRUD.

## Form Requests own validation; extract complex rules
No validation logic in controllers. Create reusable custom Rule classes (`app/Rules`) for complex or repeated validation. A Form Request never calls a Service.

## General coding principles
Prefer dependency injection over manual instantiation. Follow SOLID only where it adds real value. Never duplicate business logic across layers. Don't modify unrelated files for a feature. Don't add a Service/Repository/Interface/DTO/Action/Event/Listener just because it's theoretically possible — pick the simplest architecture that satisfies the requirement. (Example of this rule applied correctly: `DocumentChunkRepository` exists because the pgvector similarity query is genuinely non-trivial; a hypothetical `UserRepository` for plain `User::find()` calls would not be justified.)
