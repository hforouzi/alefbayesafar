# AGENTS.md — AlefBayeSafar Agent Rules

This file contains repository-wide instructions for coding agents working on AlefBayeSafar.

Read `PROJECT_CONTEXT.md` before making architectural or product decisions.

---

# 1. Repository scope

Primary repository:

`hforouzi/alefbayesafar`

This project is derived from:

`hforouzi/SymfonyAppTemplate`

The template repository is reference/base infrastructure only.

Do not make changes to the original SymfonyAppTemplate repository when working on AlefBayeSafar.

---

# 2. Inspect before editing

Before implementing a phase:

1. inspect the current repository;
2. read existing relevant files;
3. understand existing module conventions;
4. identify reusable services/components;
5. inspect current migrations;
6. inspect current tests;
7. inspect existing security/menu/settings patterns.

Do not recreate existing functionality merely because it is easier than understanding the template.

---

# 3. Preserve the template architecture

The project uses a module-based Symfony architecture.

Follow the existing conventions under:

`src/Modules/`

Do not introduce a competing global architecture without explicit approval.

Prefer module-local organization for:

- Entity
- Repository
- Form
- Controller
- Service
- Command
- Message
- MessageHandler
- translations
- templates/resources

when relevant and consistent with the existing project.

---

# 4. Existing systems must be reused

The Symfony template already contains systems for:

- Authentication
- User management
- Roles
- Permissions
- Dynamic menus
- Settings
- Admin layout
- Localization

Do not rebuild or replace these systems.

Do not rewire `security.yaml`, menu architecture or permission architecture without explicit approval unless the current task specifically requires it.

If a change is genuinely needed, explain why before making a broad architectural rewrite.

---

# 5. Work phase-by-phase

Only implement the phase explicitly requested in the current task.

Do not start future phases automatically.

If the current task is Phase 1, do not opportunistically add:

- Hotel entities
- Tour entities
- Firecrawl
- OpenAI
- SearchSource
- Review system
- Sync engine
- Airline
- Flight
- Activity business logic

unless the user explicitly expands scope.

Future architecture may be considered, but future business logic must not be prematurely implemented.

---

# 6. Small changes and small PRs

Prefer focused branches and focused pull requests.

Avoid giant refactors.

Do not mix unrelated changes.

A good change should have:

- clear purpose;
- limited scope;
- relevant tests;
- clean migration boundaries;
- understandable commit history.

---

# 7. Canonical data rules

Canonical entities and commercial offers must remain separate.

Never store one permanent price directly on a canonical entity when the price depends on date/provider/package.

Examples:

```text
Hotel != HotelOffer
Flight != FlightOffer
Activity != ActivityOffer
Airline != Flight
TourPackage != Hotel
```

Tour packages reference reusable canonical entities.

They do not own duplicate copies of those entities.

---

# 8. Provider rules

Do not hardcode business logic directly around one external provider.

Firecrawl, APIs, scrapers and supplier integrations must be behind provider/service abstractions when they are introduced.

Do not let domain entities know about Firecrawl.

Do not let controllers become provider integration classes.

---

# 9. Search source rules

Search sources must eventually be admin-configurable.

Avoid permanent hardcoded domain lists such as:

```text
booking.com
agoda.com
tripadvisor.com
...
```

Hardcoded values may only be used temporarily in fixtures/tests/reference configuration when clearly isolated.

Production provider selection belongs in configurable source/provider infrastructure.

---

# 10. AI safety/data rules

AI is an intelligence layer, not a factual inventory database.

Never implement behavior where AI silently fabricates:

- hotel prices;
- flight prices;
- availability;
- hotel facts;
- airline facts;
- activities;
- user reviews.

If no trustworthy price is available, represent it as unavailable.

AI may:

- summarize;
- translate;
- classify;
- rank;
- explain;
- generate itineraries;
- analyze real reviews;
- generate search queries.

AI-generated summaries must be distinguishable from source records.

---

# 11. Review rules

Never generate fake customer reviews.

Reviews shown as real reviews must originate from real configured sources.

Preserve useful attribution such as:

- source;
- source URL;
- external ID;
- review date.

AI can summarize or analyze those reviews, but must not impersonate reviewers.

---

# 12. Data freshness rules

When dynamic external data is introduced, design for:

- `lastSyncedAt`
- freshness/TTL
- source attribution
- retry/error handling
- provider failures
- stale data

Do not assume one global cache duration for all travel data.

Live prices and availability must use shorter lifetimes than descriptive catalog data.

---

# 13. Background work

Use Symfony Messenger for long-running synchronization/import tasks when appropriate.

Avoid blocking web requests with large crawls or expensive batch processing.

Small interactive lookups may remain synchronous if intentional.

---

# 14. Database and migrations

Use Doctrine migrations.

Keep migrations phase-scoped.

Do not manually alter production schema assumptions outside migrations.

Before finishing DB-related work, run schema validation.

Do not add large speculative schemas for future phases.

---

# 15. Frontend rules

Initial frontend stack is:

- Twig
- Stimulus
- Tailwind
- Symfony UX

Preserve the Lovable public UI/UX as a visual reference where practical.

Do not introduce a separate SPA framework unless explicitly approved.

Keep RTL/Persian UX in mind.

Public UI and Admin UI may have different layouts, but should both remain consistent with the existing application architecture.

---

# 16. Inline admin workflow principle

For reusable catalog objects, prefer:

```text
Search
→ Select
→ Import/Create if missing
→ Continue
```

Do not force the admin through unnecessary page changes during Tour Builder workflows.

This principle becomes important later for:

- hotels;
- activities;
- airlines;
- suppliers;
- destinations.

---

# 17. Naming and code quality

Use clear domain naming.

Avoid generic dumping-ground services such as:

```text
HelperService
CommonService
TravelManager
```

when a narrower responsibility exists.

Prefer explicit services such as:

```text
HotelImportService
HotelDuplicateResolver
TravelDataProviderInterface
ReviewSummaryService
```

Use typed PHP.

Follow Symfony/Doctrine conventions.

Keep controllers thin.

Place business rules in services/domain code.

---

# 18. Tests

Add or update tests for meaningful behavior.

At minimum, protect:

- routing;
- access control when changed;
- service behavior;
- entity/domain rules;
- import/normalization behavior;
- duplicate detection;
- provider error behavior;
- relevant UI smoke tests where practical.

Do not delete tests simply to make a build pass.

---

# 19. Required quality checks

Before declaring work complete, run the relevant project checks.

Baseline commands:

```bash
composer validate
php bin/console cache:clear
php bin/console lint:yaml config
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
php bin/phpunit
vendor/bin/phpstan analyse
```

On Windows, use the platform-appropriate vendor binary path if needed.

If a command cannot run because of the local environment, report the exact limitation rather than pretending it passed.

---

# 20. Do not hide failures

If an external provider, migration, test or command fails:

- report the actual failure;
- preserve useful diagnostics;
- do not silently convert errors into successful empty results.

An empty valid result and an upstream failure are different states.

---

# 21. Secrets

Never commit:

- API keys;
- Firecrawl keys;
- OpenAI keys;
- database passwords;
- tokens;
- credentials.

Use environment variables and project-specific local overrides.

When adding a new required environment variable, document it in the appropriate example/env documentation without real credentials.

---

# 22. Git rules

Do not commit directly to `main` unless the user explicitly requests it.

Use a focused feature branch.

Do not perform destructive Git actions such as:

- reset --hard
- force push
- rewriting unrelated history

without explicit approval.

Do not revert unrelated user changes.

---

# 23. Scope reporting

Before a substantial implementation, provide a concise audit/plan covering:

- existing components that will be reused;
- new files/modules expected;
- migrations expected;
- tests expected;
- explicit out-of-scope items.

At completion, report:

- files/modules changed;
- migrations added;
- tests added/updated;
- commands run;
- failures/warnings;
- what remains for the next phase.

---

# 24. Current first-task boundary

For the first implementation task, the intended scope is:

## Phase 0
Project Foundation

## Phase 1
Lovable UI/UX Migration

Do not implement future travel domain logic during this first task.

Specifically out of scope for the first implementation:

```text
Hotel domain
Tour domain
Activity domain
Airline domain
Flight domain
SearchSource implementation
Firecrawl integration
OpenAI integration
Review ingestion
Sync engine
Booking engine
Payment
```

The first phase should create a clean foundation and public Symfony UI that can later host those modules without requiring a rewrite.

---

# 25. Default decision rule

When the current code, `PROJECT_CONTEXT.md`, this file and the current user task appear to conflict:

1. the current explicit user instruction wins;
2. then `AGENTS.md`;
3. then `PROJECT_CONTEXT.md`;
4. then existing repository conventions.

If a conflict would cause a major architectural change, surface it before performing the broad rewrite.
