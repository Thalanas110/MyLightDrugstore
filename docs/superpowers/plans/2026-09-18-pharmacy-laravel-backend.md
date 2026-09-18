# Pharmacy Laravel Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved pharmacy `/api/v1` API in `backend/` as a Laravel 13 modular monolith following MeatLens module boundaries, with authenticated AES-256-GCM transport, at-rest sensitive-data encryption, comprehensive behavior/architecture tests, and exactly 100 meaningful commits.

**Architecture:** Each module has `Presentation`, `Application`, `Domain`, `Infrastructure`, and a public `index.php` composition surface. Laravel HTTP adapters call final one-operation use cases; domain/application layers depend on ports, and infrastructure implements them. A central module registry and route table compose the application. Sales calls Inventory application ports and never writes Inventory tables directly.

**Tech Stack:** PHP 8.5 CLI, Laravel 13, PHPUnit, Larastan/PHPStan level 9, SQLite for unit/fast feature tests, MySQL for transaction integration tests, OpenSSL AES-256-GCM and RSA-OAEP-SHA-256.

## Global Constraints

- Preserve every action in `API-DOCS.md`'s PHP compatibility table; do not expose old include files as routes.
- Use `/api/v1`; the documented logical JSON is inside the encrypted transport envelope, except `GET /api/v1/transport/public-key`.
- Use strict types, final classes, typed properties/returns, constructor DI, PSR-12, and readonly DTO/value objects when suitable. Use OpenSSL for AES-256-GCM and phpseclib 3 for RSA-OAEP-SHA-256 across PHP 8.3+.
- Write a meaningful test first for each behavior change, run it and observe the expected red result, implement the minimum, verify green, and commit only the scoped green slice.
- Keep passwords Argon2id-hashed, encrypt usernames/full names and backup artifacts with a rotatable AES-256-GCM data key ring, and keep data keys separate from transport keys.
- Require 80% line coverage for `backend/app/Modules`, PHPStan level 9, architecture tests, and full API feature tests in required CI; enable PCOV in CI and a compatible coverage driver locally.
- Store all times in UTC; use decimal money strings; keep movements append-only; make inventory, sales, and cancellation writes atomic and idempotent.
- Do not import legacy SQL or credentials without an audited source dump. Do not change the existing PHP apps or React prototype in this implementation.
- Deliver 100 non-empty commits from this plan's commit 1 through commit 100; do not manufacture empty or unrelated commits to meet the number.

---

## Baseline and recurring TDD/commit protocol

For every behavior or architecture row below: create the named test before production code; run `php artisan test --testsuite=<suite> --filter=<test>` (or the exact path) and confirm the expected failure; implement the minimum; rerun that test and the affected suite; run `composer analyse` when production code changes; commit only after all affected checks pass. Use `composer test` for the full suite, `composer analyse` for PHPStan, and `composer quality` for the full local gate. The Laravel framework scaffold and tool bootstrap are generated/configuration work; verify their baseline before introducing business behavior.

## File and layer map

- `backend/app/Bootstrap/`: module registry, route registration, and dependency composition.
- `backend/app/Modules/Identity/`: session authentication, roles, CSRF bootstrap, and password change.
- `backend/app/Modules/Catalog/`: medicines, catalog policies, and medicine resources.
- `backend/app/Modules/Inventory/`: lots, FEFO allocation, receipts, adjustments, movements, and stock queries.
- `backend/app/Modules/Sales/`: sale state, sale lines, idempotency, and inventory application ports.
- `backend/app/Modules/Users/`: admin staff-account actions and policies.
- `backend/app/Modules/Reporting/`: read-only inventory and sales reporting.
- `backend/app/Modules/Operations/`: backups and server time.
- `backend/app/Modules/Transport/`: key metadata, cryptographic primitives, request decrypt/response encrypt middleware.
- `backend/tests/Architecture/`: module public surfaces, dependency rules, route registration, final/use-case rules.
- `backend/tests/Unit/`: domain and application behavior.
- `backend/tests/Feature/`: Laravel HTTP, session, encryption, role, and database behavior.
- `backend/tests/Support/`: test-only encrypted client and fixture factories; no test-only production methods.
- `.github/workflows/`: required PHPStan, coverage, architecture, and MySQL integration lanes.

## Numbered commit ledger

### Planning, framework, and architecture: commits 1-16

1. **Document design and transport contract.** Add this design/plan and the transport/at-rest encryption rules to `API-DOCS.md`. Documentation-only review: `git diff --check`, link/path review, and compare every endpoint table with the current contract.
2. **Create Laravel 13 skeleton.** Generate the Laravel application in `backend/`, commit its framework bootstrap and lockfile, configure PHP `^8.3`, and verify the untouched framework smoke test.
3. **Establish test scripts.** Add PHPUnit suites, test bootstrap, and Composer scripts. Test: a real application boot test and `composer test` baseline.
4. **Configure static analysis.** Add Larastan and PHPStan level 9 configuration. Test: `composer analyse` on the baseline application.
5. **Add required CI.** Add a PHP 8.5 workflow, lockfile-keyed Composer cache, MySQL test service, coverage, and PHPStan jobs. Bound each test lane to 110 seconds. Validate YAML and run the same commands locally.
6. **Compose module registry.** Add the typed central registry and module-provider contract. Test: an application boot test proves each registered module registers once.
7. **Require module entry points.** Add one public `index.php` composition surface per module. Architecture test: every registered module exposes exactly one entry point.
8. **Enforce domain dependency direction.** Architecture test: Domain files cannot import Laravel, Eloquent, HTTP, or another module's Infrastructure.
9. **Enforce presentation/application boundaries.** Architecture test: Presentation and Application cannot import Eloquent, query builders, DB facades, or module Infrastructure implementations.
10. **Enforce use-case and final-class shape.** Architecture tests: application actions expose one public `execute()` operation; module production classes are final except explicitly documented framework-required extension points.
11. **Add success response resources.** Unit/feature test: one-resource and paginated list responses use the documented `data` and `meta` shapes and camelCase fields.
12. **Add stable API error mapping.** Feature tests: validation, auth, conflict, not-found, and unexpected errors map to the documented codes/statuses/details without SQL or stack details.
13. **Add request IDs.** Feature test: every error contains a generated or accepted safe request ID and the same ID is attached to structured logs.
14. **Reject unknown fields.** Feature tests: extra request fields produce `422 validation_failed` rather than being silently mass-assigned.
15. **Register versioned module routes.** Architecture/feature test: every route file is mounted below `/api/v1`; no unversioned business route is registered.
16. **Lock legacy route parity.** Architecture contract test: every route/action in the compatibility map points to an API behavior or explicit React-only behavior; all documented API paths appear in route registration.

### Encrypted transport: commits 17-33

17. **Canonical base64url codec.** Unit tests: valid round trips pass; padding, illegal characters, and non-canonical encodings are rejected.
18. **AES-256-GCM encryption primitive.** Unit test: output uses 12-byte nonce and 16-byte tag and decrypts to the exact plaintext.
19. **AES-GCM authentication failures.** Unit tests: changed ciphertext, tag, nonce, or AAD fails with one generic transport exception.
20. **Bind requests with AAD.** Unit tests: method is uppercased, query is omitted, and path changes invalidate authentication.
21. **Validate transport envelopes.** Unit tests: version, algorithm, key ID, nonce, ciphertext, and tag constraints reject malformed input.
22. **Wrap AES keys with RSA-OAEP.** Add phpseclib 3 through Composer. Unit tests: a 32-byte key round-trips with RSA-OAEP-SHA-256/MGF1-SHA-256 and a browser WebCrypto-compatible fixture; wrong keys and invalid sizes fail closed.
23. **Load and rotate transport keys.** Unit tests: current public key is published; current and retiring IDs decrypt during overlap; unknown IDs fail.
24. **Expose public-key endpoint.** Feature test: `GET /api/v1/transport/public-key` is plaintext, public, and returns the documented algorithm/key metadata; only failures before AES key establishment may also be plaintext.
25. **Parse JSON transport descriptors.** Unit tests: JSON payload kind/content type/value decode correctly; malformed nested JSON fails safely.
26. **Decrypt authenticated JSON requests.** Feature test: a real RSA-wrapped AES key and GCM envelope becomes the expected logical Laravel request body before validation.
27. **Reject missing and invalid key headers.** Feature tests: absent header, mismatched key ID, invalid wrapped key, invalid tag, and wrong AAD return generic failures without calling controllers.
28. **Support bodyless authenticated requests.** Feature test: a GET/DELETE with a wrapped response key reaches the route and returns an encrypted body.
29. **Encrypt JSON responses.** Feature test: successful and error logical JSON are decryptable by the client test utility and retain their outer HTTP status.
30. **Preserve cookie and safe headers.** Feature test: login `Set-Cookie` remains an HTTP cookie, sensitive headers are excluded, and permitted logical headers survive decryption.
31. **Encrypt binary responses.** Feature test: backup download bytes round-trip through base64 body encoding without corruption.
32. **Bound transport input.** Unit/feature tests: oversized body, envelope, and descriptor are rejected before controller execution; no plaintext is returned.
33. **Verify end-to-end transport contract.** Feature test: test client boots from the public key, encrypts a request, decrypts status/body/headers, and rejects response tampering.

### At-rest encryption and audit: commits 34-40

34. **Configure independent data-key ring.** Unit tests: missing, short, malformed, duplicate, and valid key IDs are handled deterministically; transport keys cannot satisfy data-key config.
35. **Add versioned encrypted-value object.** Unit tests: serialized record includes format version, key ID, nonce, tag, and ciphertext and has no plaintext.
36. **Encrypt/decrypt sensitive fields with AES-GCM.** Unit tests: round-trip, tampering, wrong key, and blank nullable value behavior.
37. **Rotate encrypted fields.** Unit tests: records encrypted with retiring keys decrypt and re-encrypt under the current key while preserving their value.
38. **Add keyed username lookup digest.** Unit tests: normalized username equality, case/whitespace policy, stable uniqueness digest, and rejection of invalid usernames.
39. **Add encrypted username/full-name cast.** Eloquent test: database values contain no plaintext; hydration returns values; passwords remain one-way hashes.
40. **Redact sensitive logs.** Feature test: username, full name, password, keys, and decrypted payload do not appear in API/access/error log context.

### Identity and authorization: commits 41-50

41. **Define roles.** Domain tests: `admin` and `staff` map to stable values and invalid roles cannot be constructed.
42. **Create identity schema.** Migration tests: users include encrypted identity columns, username blind index uniqueness, role, active flag, and password hash.
43. **Hash passwords.** Unit test: new credentials use Argon2id and verification succeeds only for the submitted password.
44. **Seed development admin safely.** Seeder test: credentials must come from environment; no default secret or plaintext credential is committed.
45. **Start CSRF session.** Feature test: public `GET /api/v1/auth/csrf` starts a session and returns the logical token in an encrypted response.
46. **Authenticate valid credentials.** Feature test: `POST /api/v1/auth/login` logs in an active user and sets the documented HTTP-only cookie attributes for the configured TLS context.
47. **Reject invalid/inactive login.** Feature tests: bad password and inactive account return generic `401` and never issue a session cookie.
48. **End current session.** Feature test: logout invalidates the session and subsequent protected requests return `401`.
49. **Return current profile.** Feature test: `/auth/me` returns ID, username, full name, role, and active state without hash/index/ciphertext.
50. **Change own password.** Feature tests: current password is required, new password policy is enforced, old password fails after success, and existing unrelated sessions follow the documented Laravel session policy.

### Medicine catalog: commits 51-59

51. **Define dosage-form domain value.** Unit tests: accepted forms serialize to contract values; invalid form is rejected.
52. **Validate medicine creation DTO.** Unit/Request tests: required fields, lengths, decimal precision/range, and exact unknown-field rejection.
53. **Create medicine.** Feature test: authenticated admin/staff creates medicine with decimal string price and correct `201` resource envelope.
54. **List medicines with pagination.** Feature test: default 25/max 100 page metadata and stable ordering.
55. **Search and filter medicines.** Feature tests: `q`, `active`, `lowStock`, `expiresBefore` filters compose without leaking inactive items by default.
56. **Read medicine with stock projection.** Use-case test: Catalog requests stock total through an Inventory application query; no cross-module table access.
57. **Update catalog fields.** Feature test: allowed catalog fields update, stock is never mass-assigned, and historical sale prices remain unchanged.
58. **Archive medicine.** Feature tests: archived medicine remains in historical relationships, is excluded from active search, and repeated archive does not delete data.
59. **Enforce catalog permissions.** Policy tests: authenticated staff/admin can perform the documented catalog actions; guests cannot.

### Inventory: commits 60-73

60. **Define lot and movement domain types.** Unit tests: positive quantities, valid expiry, movement kind, and immutable movement DTO rules.
61. **Validate receipts.** Request tests: nonempty item list, positive integer quantities, valid dates, and existing medicines.
62. **Receive stock transactionally.** MySQL feature test: receipt creates lots and append-only movements atomically with actor/time.
63. **Make receipts idempotent.** MySQL feature test: same actor/key/body returns the original receipt and creates no duplicate lots/movements.
64. **Reject receipt key reuse.** Feature test: same key with a different canonical request hash returns `409 idempotency_key_reused` and changes no rows.
65. **Search lots.** Feature test: medicine, expiry, and availability filters paginate using remaining quantity.
66. **Validate adjustment reasons.** Unit/Request tests: only stock-count, damage, expiry, and correction values are accepted.
67. **Apply adjustments atomically.** MySQL feature tests: valid signed deltas update remaining quantities and movements; negative final quantity rolls back everything.
68. **Keep movement history append-only.** Architecture/feature tests: application actions only insert movements; no update/delete route or repository method exists.
69. **Calculate available stock.** Unit/feature tests: only unexpired lots with positive remaining quantity contribute to stock on hand.
70. **Plan FEFO allocations.** Unit tests: eligible lots sort by expiry then stable received/id ordering.
71. **Allocate across multiple lots.** Unit tests: requested quantity is fully planned over FEFO lots or returns insufficient stock without a partial plan.
72. **Expose low-stock totals.** Feature tests: threshold is configurable with legacy default 30 and comparison is below threshold.
73. **Expose expiry summaries.** Feature tests: expiry cutoff is inclusive per contract and expired stock is separated from available stock.

### Sales: commits 74-90

74. **Define sale state transitions.** Domain tests: only open/unpaid sales accept changes; paid/completed/cancelled transitions reject invalid moves.
75. **Validate sale creation.** Request tests: nonempty line list, valid medicine IDs, positive integer quantities, and required idempotency header.
76. **Snapshot money on sale lines.** Unit tests: server price and decimal string total are snapshotted; client-supplied price/total is rejected.
77. **Create sale and consume FEFO stock atomically.** MySQL integration test: sale, lines, stock lots, and movements commit together across Inventory's public application port.
78. **Rollback insufficient sale.** MySQL integration test: insufficient eligible stock returns `409` and leaves sale, lot quantities, movements, and idempotency record unchanged.
79. **Make sale creation idempotent.** Feature test: same actor/key/body returns the same sale ID and makes no second stock change.
80. **Reject changed sale retry.** Feature test: same key/different body returns `409` and leaves stock unchanged.
81. **Validate sale-item addition.** Request/domain tests: one item shape and idempotency key are required.
82. **Add items only to open unpaid sales.** MySQL feature test: add uses current server price, FEFO stock, transaction boundary, and idempotency.
83. **Filter sales.** Feature tests: payment status, sale state, date range, creator, pagination, and stable ordering.
84. **Read sale detail safely.** Feature tests: sale resource includes immutable line snapshots and never exposes database columns or another protected resource.
85. **Remove a line with compensation.** MySQL feature test: open unpaid line becomes removed and returned stock creates a compensating movement.
86. **Make line removal non-repeatable.** Feature test: repeated removal cannot restore stock twice and invalid state returns `409`.
87. **Mark one sale paid.** Feature test: only requested open sale becomes paid/completed; other unpaid sales remain unchanged.
88. **Make paid transition idempotent.** Feature tests: repeated mark-paid returns current resource and does not create another transition or movement.
89. **Validate cancellation.** Request/domain tests: cancellation reason is required and only open/unpaid sales can be cancelled.
90. **Cancel sale atomically.** MySQL feature tests: state/audit update and compensating stock movements commit together; repeated cancellation never restores twice.

### Staff, reports, and operations: commits 91-100

91. **List staff accounts.** Feature test: admin sees paginated profile fields; password hashes, blind indexes, and ciphertext are never returned.
92. **Create staff account.** Feature test: username uniqueness uses blind index, full name/username are encrypted, password is Argon2id-hashed, and audit actor is recorded.
93. **Read one staff account.** Feature tests: admin-only resource lookup, safe `404` visibility, and no credential leakage.
94. **Update/deactivate staff.** Feature tests: admin can update name/role/active state; referenced users are deactivated, not hard-deleted.
95. **Report sales aggregates.** Feature tests: UTC date boundaries, gross totals as decimal strings, and count calculations match sale state/payment status rules.
96. **Report inventory.** Feature tests: current stock, low-stock threshold, and expiring lots match Inventory query projections.
97. **Enforce read-only reporting boundary.** Architecture tests: Reporting may use read ports only and cannot mutate catalog, lots, sales, or movements.
98. **Expose system time.** Feature test: `GET /api/v1/system/time` returns a UTC ISO-8601 `now` inside the encrypted normal success envelope.
99. **Request encrypted backup job.** Feature tests: admin/staff receive `202`, audit event, and job ID; DB dump executes through an argument-safe process adapter and artifacts are encrypted outside `public/`.
100. **Complete backup access and final API gate.** Feature tests: only admins can inspect/download, completed artifact decrypts only in authorized delivery flow, and every documented endpoint/legacy action passes the parity route contract; run `composer quality` and the complete MySQL CI-equivalent suite before commit.

## Completion checklist

- [ ] 100 commits exist after the pre-backend `HEAD`, each non-empty and in the numbered ledger order.
- [ ] Full route/parity contract covers every documented API endpoint and compatibility action.
- [ ] Unit, feature, architecture, MySQL integration, PHPStan, and 80% module-coverage gates pass locally.
- [ ] GitHub Actions workflow syntax and all required job commands are verified; remote CI is reported as unverified unless a run is available.
- [ ] `.env` keys and credentials are absent from git; transport and at-rest keys are distinct; no plaintext sensitive values appear in persistence/logs.
- [ ] No user changes in the existing PHP applications or the MeatLens repository were altered.
