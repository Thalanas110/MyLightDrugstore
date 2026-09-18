# Pharmacy Laravel Backend Design

## Goal

Build a Laravel backend in `backend/` that implements the approved `/api/v1` contract and preserves every mapped legacy PHP action, using the MeatLens modular backend architecture and test-driven development.

## Decisions

- Use Laravel 13 with PHP 8.3 or newer. The current XAMPP PHP 8.2 installation remains unchanged; use a separate PHP CLI runtime for Laravel development and tests. Use PHP's OpenSSL extension for AES-256-GCM and phpseclib 3 for RSA-OAEP-SHA-256 so the protocol works across the full PHP 8.3+ range.
- Keep one deployable Laravel application and one relational database. Modules live under `backend/app/Modules/{Identity,Catalog,Inventory,Sales,Users,Reporting,Operations,Transport}`.
- Mirror MeatLens module structure: each module has `Presentation`, `Application`, `Domain`, `Infrastructure`, and one public `index.php` composition surface. Module composition is explicit through a central registry; do not make modules call internal classes from other modules.
- Use final PHP classes, strict types, readonly DTOs/value objects where suitable, constructor injection, and one public `execute()` operation for each application use case.
- Keep controllers limited to HTTP translation. Form Requests validate and authorize input; API Resources serialize logical response data; policies enforce role and ownership rules; repositories implement module ports.
- Keep the existing API contract as the behavior source of truth. The `/api/v1` routes cover authentication, medicines, inventory, sales, staff, reports, backups, and server time. Preserve the documented compatibility mapping for every current PHP page, form action, print action, and DataTables fragment.
- Use Laravel cookie sessions and CSRF protection for the first-party React client. Do not store credentials in browser storage.

## Request transport

All API requests and response bodies except the successful `GET /api/v1/transport/public-key` bootstrap response use the MeatLens-style encrypted envelope. A generic plaintext transport error may be returned only before a request AES key has been established. HTTPS remains mandatory in deployed environments. A client generates a fresh 32-byte AES key per request, wraps it using RSA-OAEP-SHA-256, and sends it in `X-Transport-Key`. AES-256-GCM uses 12-byte random nonces and 16-byte tags. AAD binds the uppercase method and normalized request path without the query string. The server validates envelope size and key ID, decrypts before Laravel validation/authentication, and encrypts every logical response after application processing. Cookie headers and HTTP status remain outer HTTP metadata.

Transport RSA keys and at-rest encryption keys are separate. Configuration supports current and retiring key IDs during rotation. Transport errors are generic and never include plaintext, keys, stack traces, or database details. AES-GCM is defense in depth and does not replace TLS or Laravel's CSRF, session, policy, or validation controls.

## Sensitive data

Encrypt staff usernames and full names at rest with AES-256-GCM. Store normalized username lookup HMACs under a separate key so login and uniqueness checks do not need plaintext queries. Hash passwords with Argon2id. Encrypt generated database backup artifacts with AES-256-GCM and store them outside the public directory. Keep sensitive fields out of logs. Version encrypted records with a key ID and format version to support re-encryption during rotation.

## Module boundaries

- `Identity` owns session login/logout/CSRF bootstrap, current-user profile, password changes, and authentication policy.
- `Catalog` owns medicine definitions and archival. Stock totals are read through an Inventory application query.
- `Inventory` owns lots, receipts, adjustments, movements, FEFO selection, and inventory summaries.
- `Sales` owns sale aggregates and item state. It calls Inventory application ports; it never writes Inventory tables directly.
- `Users` owns admin-only staff account listing and lifecycle changes.
- `Reporting` exposes read-only queries and aggregates.
- `Operations` owns backup job requests/downloads and system time.
- `Transport` owns public-key metadata, envelope crypto, request middleware, and response encryption.

Every module's `index.php` is its supported composition/export surface. Architecture tests enforce module entry points, dependency direction, final classes, use-case shape, route registration, no persistence access from Presentation/Application, and no cross-module Infrastructure imports. Shared code is limited to HTTP response/error primitives, crypto ports, transaction abstractions, and framework-neutral utilities.

## Data and transaction rules

Use explicit Laravel migrations and seed only a development admin account from environment configuration; never copy legacy plaintext passwords. Use integer quantities, decimal money, medicine records, stock lots, append-only inventory movements, sale headers/items, idempotency records, sessions, audit events, and encrypted backup metadata. An unavailable legacy SQL dump means historical imports are out of scope until the source schema/data is audited.

Receiving, adjustment, sale creation/item addition/removal, payment-status transition, and cancellation are transactional. Sales consume eligible unexpired lots by FEFO. Insufficient stock commits no changes. Movement history is append-only. Every idempotency key binds to a canonical request hash and actor; same-key/same-body returns the prior result, while same-key/different-body returns a stable `409` error. A sale transition cannot restore or consume stock twice.

## API behavior and errors

Keep the API's camelCase fields, list pagination, money strings, UTC timestamps, stable error codes, and request IDs. The HTTP status remains visible outside the encrypted envelope. Unauthorized and nonexistent resources return safe `401`, `403`, or `404` responses without leaking protected data. Validation errors return `422`; state conflicts return `409`; unhandled errors return a generic `500`.

## Testing and quality gates

- Write each behavior/architecture test first, run it and observe the expected failure, implement the smallest passing change, then refactor with tests green.
- Use PHPUnit unit tests for domain rules and use cases, Laravel feature tests for encrypted HTTP/session/policy/validation behavior, MySQL-backed integration tests for transaction/locking behavior, and a dedicated `tests/Architecture` suite for module boundaries.
- Add property-style/data-provider coverage for envelope parsing, tampering, key rotation, money, idempotency, and inventory transition edge cases.
- Require PHPStan level 9 (Larastan) and at least 80% line coverage for `app/Modules` in CI. Run the full local CI-equivalent suite before marking a phase complete.
- CI must run on PHP 8.5, install from the lockfile, run architecture and feature tests, coverage, and PHPStan. No skipped/focused suites or advisory quality lanes.

## Commit policy

Deliver exactly 100 non-empty commits for this backend effort. Each code commit is a complete green TDD slice: the test is written and observed failing before implementation, the relevant test lane is rerun successfully, and only scoped files are committed. Use architecture, test-support, transport, identity, catalog, inventory, sales, staff, reporting, operations, documentation, and integration slices; do not create empty commits or unrelated edits to reach the count. Track the numbered commit ledger in the backend plan.

## Out of scope

Do not implement the React application, supplier/purchase-order workflows, branches, customer records, payment ledgers, accounting, or undocumented routes in v1. Do not change the existing PHP copies or migrate legacy records/passwords without an audited source dump.
