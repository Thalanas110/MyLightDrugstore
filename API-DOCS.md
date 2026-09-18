# MyLightDrugstore API

**Status:** Target contract for the refactor; Laravel implementation is underway. Registered paths without implemented behavior return `501 not_implemented` until their TDD slices replace the placeholders.

This document describes the target API and maps it to the current PHP application. The first release is a single-store pharmacy system for medicine catalog, inventory and receiving, sales, staff accounts, and reports.

## Scope

### Version 1

- Authentication and the `admin` and `staff` roles
- Medicine catalog
- Inventory on hand, stock receipts, lots, expiry dates, and adjustments
- Sales carts, unpaid/paid status, line removal, and sale cancellation
- Staff account administration
- Inventory and sales reports
- Secure, role-protected database backup operations
- The server-time value used by the legacy clock widget

### Later phases

Supplier management, actual purchase-order workflows, multiple branches, customer records, payment records, reconciliation, and accounting are outside this contract. The simple legacy unpaid/paid flag remains in v1 sales; it does not create a payment or accounting ledger. The v1 model is single-store; adding branches later will require an explicit data migration and store-level authorization design.

## Architecture

The frontend will be a React application organized with Feature-Sliced Design (FSD). The backend will be one Laravel modular monolith. The API is the boundary between them; the current PHP pages are not REST endpoints.

### React FSD layers

```text
frontend/src/
  app/       application setup, providers, router, global styles
  pages/     route-level screens
  widgets/   larger page sections composed from features and entities
  features/  user actions such as recording a sale or receiving stock
  entities/  medicine, inventory, sale, and user models and UI
  shared/    API client, common UI, utilities, and configuration
```

Dependencies point down the layer list: `app` can compose any layer; `pages` can use `widgets`, `features`, `entities`, and `shared`; lower layers must not import from higher layers. Cross-slice imports should use each slice's public API.

### Laravel modular monolith

Keep one deployable Laravel application and one relational database. Organize business logic into modules with clear ownership:

```text
backend/app/Modules/
  Identity/     authentication and authorization
  Catalog/      medicine definitions and catalog data
  Inventory/    stock lots, receipts, adjustments, and movements
  Sales/        sales and sale items
  Users/        staff account administration
  Reporting/    read-only operational reports
```

Each module owns its validation, policies, application services, and persistence rules. Controllers translate HTTP requests and responses; they do not hold business rules. Sales calls Inventory's application services to check and change stock. A sale and its stock movements are committed in one database transaction. Modules do not update each other's tables directly. This is a modular monolith, not a set of microservices.

## API conventions

- Base path: `/api/v1`
- Transport: HTTPS in deployed environments; AES-256-GCM encrypted API request and response bodies
- JSON property names: `camelCase`
- Dates: `YYYY-MM-DD`; timestamps: ISO 8601 in UTC
- Money: decimal strings in the store's configured currency, for example `"12.50"`; do not use floating-point values
- List endpoints use `page` and `perPage`; default `perPage` is 25 and maximum is 100
- Unknown fields and invalid enum values are rejected with `422 Unprocessable Content`
- Never expose database table or column names as API fields

Successful single-resource response:

```json
{
  "data": {
    "id": 42,
    "genericName": "Example medicine"
  }
}
```

Successful list response:

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "perPage": 25,
    "total": 0
  }
}
```

Error response:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The request is invalid.",
    "details": {
      "items.0.quantity": ["Must be greater than zero."]
    },
    "requestId": "req_01JEXAMPLE"
  }
}
```

Use stable machine-readable error codes. `details` may be omitted when there are no field errors. Include a request ID in every error response and server log entry.

The default error codes are `unauthenticated` (401), `forbidden` (403), `not_found` (404), `conflict` (409), `validation_failed` (422), `not_implemented` (501, during the refactor only), and `internal_error` (500). A conflict may use a more specific stable code when the caller needs to handle that state, such as `insufficient_stock` or `idempotency_key_reused`. Unexpected errors always use a generic message and never include exception, SQL, or stack-trace details.

The server accepts a client `X-Request-ID` containing 1–64 ASCII letters, digits, periods, underscores, or hyphens, beginning with a letter or digit. Missing or unsafe values are replaced with a `req_`-prefixed ULID. The selected ID is returned in the `X-Request-ID` response header and every error body's `requestId` field, and is attached as `request_id` to structured log context.

### Encrypted transport

The JSON examples in this document describe the logical API payload after transport decryption. HTTPS is still required in deployed environments. The encrypted transport adds the MeatLens-style application envelope; it does not replace TLS, authentication, CSRF protection, authorization, validation, or idempotency.

`GET /transport/public-key` is the only plaintext success endpoint. A generic plaintext transport error is also possible before a symmetric request key can be established (for example, when `X-Transport-Key` is missing or cannot be unwrapped). Once a request key is established, request and application error bodies are encrypted. The public-key endpoint returns the current transport key metadata in the normal success envelope:

```json
{
  "data": {
    "version": 1,
    "algorithm": "RSA-OAEP-256",
    "transportAlgorithm": "A256GCM",
    "keyId": "transport-2026-01",
    "publicKey": "-----BEGIN PUBLIC KEY-----..."
  }
}
```

For every other request, the client creates a fresh 32-byte AES key and wraps it with the advertised RSA public key using OAEP with SHA-256. The client sends the wrapped key in `X-Transport-Key` as `<keyId>.<base64url-wrapped-key>`. The request and response envelope has this shape:

```json
{
  "version": 1,
  "algorithm": "A256GCM",
  "keyId": "transport-2026-01",
  "iv": "<base64url-12-byte-nonce>",
  "ciphertext": "<base64url-ciphertext-and-16-byte-tag>"
}
```

AES-GCM additional authenticated data is the uppercase HTTP method, one space, and the normalized URL path, excluding the query string (for example, `POST /api/v1/sales`). For a JSON request, the encrypted plaintext is a transport payload descriptor with `kind: "json"`, `contentType: "application/json"`, and `value` containing the UTF-8 JSON text. The decrypted response plaintext is a JSON descriptor with `contentType`, safe `headers`, `body`, and `bodyEncoding` (`utf8` or `base64`). The HTTP status and `Set-Cookie` headers remain HTTP metadata; API body content stays encrypted. Empty-body requests still send `X-Transport-Key` so the response can be encrypted.

Reject unknown key IDs, malformed or non-canonical base64url, invalid nonce/tag lengths, oversized envelopes, and authentication failures with a generic error. The server limits `X-Transport-Key` to 4,096 bytes, a JSON request envelope to 1,400,000 bytes, combined ciphertext and tag to 1,048,576 bytes, and a decrypted JSON descriptor to 524,288 bytes. Reject oversized request bodies before parsing their JSON envelope. Do not log keys, decrypted bodies, or sensitive response data. Use distinct key material for transport and stored-data encryption. Support overlapping key IDs during key rotation.

### Sensitive data at rest

Encrypt staff usernames and full names in the database with AES-256-GCM using an at-rest key ring separate from the transport RSA/AES keys. Store a keyed HMAC-SHA-256 lookup digest for normalized usernames so login and uniqueness checks do not require plaintext database values. Passwords are one-way Argon2id hashes, never encrypted or returned. Encrypt database backup artifacts at rest with AES-256-GCM and keep them outside the public web root. Encrypted personal fields are decrypted only in the application layer for authorized responses and cannot be searched directly; the username digest is the only lookup index for those encrypted account fields. Include key IDs and format versions in ciphertext records so key rotation can re-encrypt stored data.

| HTTP status | Use |
| --- | --- |
| `200` | Successful read or update |
| `201` | Resource created |
| `202` | Accepted for asynchronous processing, such as a backup job |
| `204` | Successful action with no response body |
| `401` | Missing or invalid authentication |
| `403` | Authenticated user lacks permission |
| `419` | CSRF token is missing, invalid, or expired |
| `404` | Resource does not exist or is not visible to the user |
| `409` | State conflict, such as insufficient stock or an invalid transition |
| `422` | Request validation failed |
| `429` | Rate limit exceeded |
| `500` | Unexpected server error; do not return stack traces or SQL details |

## Authentication and permissions

The React application is a first-party client. Use Laravel-managed session authentication with a secure, HTTP-only session cookie and CSRF protection for state-changing requests. `GET /auth/csrf` returns `{ "data": { "csrfToken": "..." } }`; send the token in the `X-CSRF-TOKEN` header. Do not store session credentials in browser local storage. Configure CORS to allow only the deployed frontend origin. Token authentication for mobile or third-party clients is deferred.

`POST /auth/login` accepts a `username` and `password`. `POST /auth/logout` ends the session. `GET /auth/me` returns the authenticated user's `id`, `username`, `fullName`, `role`, and `active` state. Passwords are never returned. Store password hashes, not plaintext passwords.

| Capability | Admin | Staff |
| --- | --- | --- |
| Read medicines and inventory | Yes | Yes |
| Create or update medicines; archive a medicine | Yes | Yes |
| Record stock receipts | Yes | Yes |
| Record stock adjustments | Yes | Yes |
| Create and manage sales, including paid/unpaid status | Yes | Yes |
| Read inventory and sales reports | Yes | Yes |
| Change own password | Yes | Yes |
| Create, update, or deactivate staff accounts | Yes | No |
| Request a database backup | Yes | Yes |
| List and download database backups | Yes | No |
| Read server time for the clock widget | Yes | Yes |

The role is checked on every request by a backend policy. Hiding a button in React is not authorization.

## Resource model

### Medicine

```json
{
  "id": 42,
  "genericName": "Example medicine",
  "brandName": "Example brand",
  "description": "Example description",
  "dosageForm": "tablet",
  "strength": "10 mg",
  "unitPrice": "12.50",
  "storageLocation": "A-03",
  "active": true,
  "stockOnHand": 80,
  "createdAt": "2026-09-18T02:00:00Z",
  "updatedAt": "2026-09-18T02:00:00Z"
}
```

`stockOnHand` is calculated from available, unexpired stock lots. It is read-only in the medicine resource. Price is snapshotted on each sale item so later catalog price changes do not change historical sales.

### Stock lot and movement

A receipt creates one or more stock lots. Each lot records its medicine, received quantity, remaining quantity, receipt time, and expiry date. Sales consume lots by first-expiring-first-out (FEFO), excluding expired or depleted lots. Inventory movements are append-only records for receipts, sales, cancellations, and adjustments.

### Sale

A sale has a stable ID, creation time, state (`open`, `completed`, or `cancelled`), payment status (`unpaid` or `paid`), creator, line items, and totals. Marking an open sale paid completes it. Each line item records the medicine ID, quantity, unit-price snapshot, line total, and line state (`active` or `removed`). V1 carries the existing unpaid/paid marker but does not record tender type, partial payments, refunds, or accounting entries.

## Endpoints

All paths below are relative to `/api/v1`. Unless marked public, endpoints require an authenticated session. Every target path is registered during the refactor; paths whose behavior is not implemented yet return `501 not_implemented` until their TDD slice replaces the placeholder.

### Transport

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /transport/public-key` | Public, plaintext | Bootstrap the AES-256-GCM transport by returning the current RSA-OAEP-SHA-256 public key and key ID |

### Authentication

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /auth/csrf` | Public | Start an anonymous session and return a CSRF token |
| `POST /auth/login` | Public, CSRF protected | Start an authenticated session |
| `POST /auth/logout` | Authenticated | End the current session |
| `GET /auth/me` | Authenticated | Return the current user's profile and role |
| `POST /auth/change-password` | Authenticated | Change the current user's password after verifying the current password |

### Medicines

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /medicines` | Admin, staff | Search and page through active medicines |
| `POST /medicines` | Admin, staff | Create a medicine |
| `GET /medicines/{medicineId}` | Admin, staff | Read a medicine and current stock total |
| `PATCH /medicines/{medicineId}` | Admin, staff | Update catalog fields |
| `POST /medicines/{medicineId}/archive` | Admin, staff | Stop new use without deleting sales history |

`GET /medicines` supports `q`, `active`, `lowStock`, `expiresBefore`, `page`, and `perPage`. Do not hard-delete a medicine referenced by stock or sales history.

The list defaults to active medicines; `active=false` selects archived medicines. Search checks generic name, brand, dosage form, and strength. Stock totals and low-stock filtering include only non-depleted lots that have not expired as of the current UTC date. `expiresBefore` is inclusive and selects medicines with a non-depleted, unexpired lot expiring on or before that date.

`GET /medicines/{medicineId}` returns the same medicine resource for active or archived records, with stock calculated from unexpired, non-depleted lots. A missing medicine returns `404 not_found`.

Create/update fields are `genericName`, `brandName`, `description`, `dosageForm`, `strength`, `unitPrice`, and `storageLocation`. The server validates lengths and numeric ranges and computes stock totals.

`POST /medicines` returns the created active medicine resource with status `201` and `stockOnHand` of zero. `unitPrice` is a positive decimal string with exactly two fractional digits and a maximum of `99999999.99`. Opening stock is recorded separately with `POST /inventory/receipts`; `initialQuantity` is not a medicine field.

`PATCH /medicines/{medicineId}` accepts one or more of those catalog fields and returns the updated resource. Nullable fields can be cleared with `null`; an empty patch is invalid. The `active` and `stockOnHand` fields cannot be changed here: archive medicines through the archive action and change stock through inventory endpoints.

### Inventory

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /inventory` | Admin, staff | List on-hand totals, low-stock items, and expiry summaries |
| `GET /inventory/lots` | Admin, staff | Search lots by medicine, expiry, and availability |
| `POST /inventory/receipts` | Admin, staff | Receive stock and create lots |
| `POST /inventory/adjustments` | Admin, staff | Record a reasoned correction against one or more lots |
| `GET /inventory/movements` | Admin, staff | Read the append-only stock movement history |

`GET /inventory` lists active medicines with `stockOnHand`, `lowStock`, and `earliestExpiry`. Stock totals include only non-depleted lots that expire today or later in UTC. `lowStock=true` selects totals below the configurable threshold (default 30); `false` selects totals at or above it. `expiresBefore` includes medicines with a non-depleted, unexpired lot expiring on or before that date. `earliestExpiry` is the earliest expiry among unexpired, non-depleted lots, or `null` when none remain. Inventory lists use the standard `page` and `perPage` metadata.

`GET /inventory/lots` supports `medicineId`, `expiresBefore`, `available`, `page`, and `perPage`. The expiry filter is inclusive. `available=true` selects lots with remaining quantity whose expiry date is today or later in UTC; `available=false` selects depleted or expired lots. Omitting `available` returns both. Results are ordered by expiry date, receipt time, then lot ID. Each item returns `lotId`, `medicineId`, `genericName`, `brandName`, `receivedAt`, `expiresAt`, `quantityReceived`, `quantityRemaining`, and `available`.

Receipt request:

```json
{
  "receivedAt": "2026-09-18T02:00:00Z",
  "items": [
    {
      "medicineId": 42,
      "quantity": 24,
      "expiresAt": "2028-06-30"
    }
  ]
}
```

The server returns `201` with `receiptId`, `receivedAt`, `itemCount`, and `totalQuantity`. Each item creates an expiring lot and a positive receipt movement tied to the acting user. Receipt, lots, and movements are committed together.

Adjustment request:

```json
{
  "reason": "stock_count",
  "items": [
    {
      "lotId": 301,
      "quantityDelta": -2
    }
  ]
}
```

Adjustment reasons are `stock_count`, `damage`, `expiry`, and `correction`. Each request contains 1–100 unique lots and non-zero signed quantity deltas. The server locks all affected lots, rejects an adjustment that would make any lot negative, then records the actor, timestamp, reason, and affected lots in one transaction. The `201` response contains `adjustmentId`, `reason`, `itemCount`, and `totalQuantityDelta`. A correction is a new movement; movement history is never edited or deleted.

`GET /inventory/movements` supports `medicineId`, `lotId`, `movementType`, `from`, `to`, `page`, and `perPage`. Date filters are inclusive UTC calendar dates. Results are ordered newest first and include the signed delta, reason, source reference, lot, medicine, actor ID, and actor full name. Movement types are `receipt`, `sale`, `sale_item_removal`, `sale_cancellation`, and `adjustment`.

### Sales

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /sales` | Admin, staff | Search sales by date, status, or creator |
| `POST /sales` | Admin, staff | Create an open, unpaid sale/cart with its initial items |
| `POST /sales/{saleId}/items` | Admin, staff | Add items to an open, unpaid sale/cart |
| `DELETE /sales/{saleId}/items/{saleItemId}` | Admin, staff | Remove an item from an open, unpaid sale/cart |
| `GET /sales/{saleId}` | Admin, staff | Read a sale and its line items |
| `POST /sales/{saleId}/mark-paid` | Admin, staff | Preserve the current paid/unpaid transition without adding payment records |
| `POST /sales/{saleId}/cancel` | Admin, staff | Cancel an unpaid sale and record compensating stock movements |

Create request:

```json
{
  "items": [
    {
      "medicineId": 42,
      "quantity": 2
    }
  ]
}
```

The client does not submit prices or totals. The server loads current prices, validates quantities, chooses eligible lots using FEFO, computes totals, and commits the sale and stock movements atomically. A newly created sale is `open` and `unpaid`; its items reserve/decrement stock as in the current workflow. Require an `Idempotency-Key` header on sale creation and item addition so retrying a timed-out request cannot duplicate a sale or line.

`POST /sales/{saleId}/items` accepts one item using the same `{ "medicineId": 42, "quantity": 2 }` shape. The sale must still be open and unpaid.

`GET /sales` supports `paymentStatus=unpaid|paid`, `state=open|completed|cancelled`, `from`, `to`, `createdBy`, `page`, and `perPage`. Removing a cart line is allowed only while the sale is open and unpaid. The API marks the line removed and creates a compensating stock movement rather than erasing its audit history.

`POST /sales/{saleId}/mark-paid` changes that sale's payment status to `paid` and state to `completed`. It does not record how money was collected. Repeating the request for a paid sale returns the current sale without creating a second transition. This replaces the legacy action that marks every unpaid line paid at once.

Cancel request:

```json
{
  "reason": "entered_in_error"
}
```

Cancellation preserves the sale and its original line items, records who cancelled it and why, and restores stock through new movements. V1 allows cancellation only while the sale is open and unpaid. Repeating a cancellation does not restore stock a second time. A sale is never removed with `DELETE`.

### Staff accounts

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /users` | Admin | List accounts |
| `POST /users` | Admin | Create an account |
| `GET /users/{userId}` | Admin | Read an account |
| `PATCH /users/{userId}` | Admin | Update name, role, or active state |
Create request fields are `username`, `fullName`, `password`, and `role`. Roles are `admin` or `staff`. The password-change request contains `currentPassword` and `newPassword`; the new password must be at least 12 characters and differ from the current password. Deactivate accounts instead of deleting users referenced by sales or inventory movements. Password reset for another user requires a separate audited workflow and is not part of this contract.

### Reports

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /reports/inventory` | Admin, staff | Current stock, low-stock items, and expiring lots |
| `GET /reports/sales` | Admin, staff | Sales count and gross sales totals for a date range |

Report endpoints accept `from` and `to` dates and return aggregate data, not payment reconciliation. Inventory reports accept `lowStock` and `expiresBefore`. Reports are read-only and use the same pagination conventions when returning row-level results.

### Backups and server time

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `POST /backups` | Admin, staff | Request a database backup job |
| `GET /backups/{backupId}` | Admin | Read backup job status and metadata |
| `GET /backups/{backupId}/download` | Admin | Download a completed backup |
| `GET /system/time` | Admin, staff | Return current server time for the legacy clock widget |

Backup requests return `202 Accepted` with a job ID. Store backup files outside the public web root, encrypt them at rest, audit requests and downloads, and never return SQL contents in an API response. The current code creates local backup files; the target API must keep this capability private and permission-checked. The system-time response is `{"data":{"now":"2026-09-18T02:00:00Z"}}`.

## Inventory and sales invariants

- Quantities are positive integers for receipts and sale lines. Adjustment deltas may be positive or negative but must leave a lot at zero or above.
- Expired and depleted lots cannot fulfill sales.
- A sale fails with `409 insufficient_stock` if eligible stock is not available; no partial sale or stock change is committed.
- Receipt, adjustment, sale, and cancellation writes are transactional and create immutable movements.
- Sale creation, sale-item addition, and stock receipt accept idempotency keys. Repeating a key with the same request returns the original result; reusing it with a different request returns `409 idempotency_key_reused`.
- Store all timestamps in UTC and convert for display in the frontend.
- Keep an audit record for account changes, inventory adjustments, and sale cancellation.

## Current PHP behavior and target mapping

The current applications are server-rendered PHP pages that call MySQL directly; they do not expose a JSON REST API. `MyLightDrugstore/` contains the login page and duplicated administrator and staff areas. `pharmacy/` is a standalone copy. The mapping below covers every current user-facing PHP page, form action, print view, and DataTables fragment in both application copies. Each current business action is carried into the target system. Page responses become React routes/components; data reads and mutations become the Laravel API endpoints described above. This is a behavior-preservation map, not a promise to keep `.php` URLs as the permanent API paths.

Use these path prefixes in the table: `M/` = `MyLightDrugstore/`, `A/` = `M/administrator/`, `S/` = `M/staff/`, and `P/` = `pharmacy/`. A prefix such as `A+S+P/` means the same filename exists under all three paths; prefixes are combined only where that file exists. When multiple filenames follow a prefix, that prefix applies to each filename in the list.

| Existing route/action | Behavior carried forward | Target React route or API |
| --- | --- | --- |
| `M/index.php`, `M/logout.php` | Enter the MyLightDrugstore app, authenticate, and log out | React `/login`; `GET /auth/csrf`, `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| `A+S/index.php`, `P/index.php` | Admin/staff dashboard or standalone-app dashboard | React `/dashboard`; `GET /medicines` and inventory/report queries as needed |
| `A+S+P/inventory.php` | Inventory navigation and entry | React `/inventory`; `GET /inventory` |
| `A+S+P/inventoryItems.php`, `editInventory.php`, `deleteInventory.php`, `updateInventoryItems.php` | List, add, edit, and remove catalog items; create accepts initial quantity, and edit accepts a changed current quantity plus an additional quantity | React `/catalog/medicines`; `GET/POST /medicines`, `PATCH /medicines/{medicineId}`, `POST /medicines/{medicineId}/archive`, `POST /inventory/adjustments` for count corrections, and `POST /inventory/receipts` for opening or additional stock |
| `A+S+P/inventoryStatus.php` | Inventory status view | React inventory report; `GET /inventory` and `GET /reports/inventory` |
| `A+S/itemForPurchase.php` | List items below the current low-stock threshold of 30 units | React low-stock filter; `GET /inventory?lowStock=true` or `GET /reports/inventory?lowStock=true` with the configured threshold set to 30 |
| `A+S+P/updateQuantity.php` | Add the entered quantity to the current stock total from the maintenance screen | React stock-receipt form; `POST /inventory/receipts` creates a lot for the additional quantity. Record expiry and receipt time; do not treat the entered value as a replacement total. |
| `A+S+P/purchaseOrderMaintenance.php` | Show the current stock table and link to add quantity | React `/inventory`; `GET /inventory`, `GET /inventory/lots`, and `POST /inventory/receipts`. The current page does not create supplier purchase orders; that workflow remains deferred. |
| `A+S+P/orderProcessing.php` | Navigate to stock maintenance and sales entry | React `/operations`; links to the inventory and sales features above |
| `A+S+P/salesOrderEntry.php`, `popUpSalesOrderEntry.php` | Find a medicine and add a requested quantity to an order | React sale-entry form and medicine picker; `GET /medicines`, `POST /sales`, `POST /sales/{saleId}/items` |
| `A+S+P/cart.php` | Read the unpaid order/cart | React `/sales/cart`; `GET /sales?paymentStatus=unpaid` |
| `A+S+P/cancelOrder.php` | Remove one unpaid cart line and return its stock | Cart-line action; `DELETE /sales/{saleId}/items/{saleItemId}`. Keep an audit record and compensating stock movement. |
| `A+S+P/updateOrder.php` | Mark current unpaid order lines paid | `POST /sales/{saleId}/mark-paid`. The paid/unpaid capability remains; the target scopes it to one sale rather than changing every unpaid line globally. |
| `A+S+P/printOrder.php` | Print unpaid order lines | React `/sales/print`; data from `GET /sales?paymentStatus=unpaid` or `GET /sales/{saleId}`; browser print handles layout |
| `A+S+P/transactionDetails.php`, `printTransaction.php` | Select a date, view transaction details, and print the result | React `/reports/sales` and print view; `GET /reports/sales?from=...&to=...` and `GET /sales` |
| `A+S+P/reports.php` | Navigate to inventory and transaction reports | React `/reports`; `GET /reports/inventory`, `GET /reports/sales` |
| `A/accounts.php` | Navigate to user management and administrator account settings | React `/admin/users` and `/settings/security` |
| `A/userAccounts.php`, `A/deleteUserAccount.php` | View, create, and remove staff accounts | React `/admin/users`; `GET/POST /users` and `GET/PATCH /users/{userId}`. Removal becomes deactivation so history remains linked. |
| `A/changeAdminAccount.php` | Change the signed-in administrator password | `POST /auth/change-password`; applies to both roles in the target system |
| `A+S/backup.php` | Request a database dump | `POST /backups`; admin and staff may request one, while only admin can list or download backup artifacts |
| `A+S+P/refresh-me.php` | Return the server date/time for the legacy clock | `GET /system/time`; React renders the value |
| `A/dataTables/dataTables.php`, `S/dataTables/dataTables.php`, `P/dataTables/dataTables.php` | Return the dashboard medicine table fragment | `GET /medicines`; React dashboard table |
| `A/dataTables/purchaseOrderMaintenanceDatatables.php`, `S/dataTables/purchaseOrderMaintenanceDatatables.php`, `P/dataTables/purchaseOrderMaintenanceDatatables.php` | Return the stock-maintenance table and quantity-update links | `GET /inventory` and `GET /inventory/lots`; React inventory table and adjustment action |
| `A/dataTables/SalesOrderEntryDataTables.php`, `S/dataTables/SalesOrderEntryDataTables.php`, `P/dataTables/SalesOrderEntryDataTables.php` | Return medicine-search results for the sales-entry popup | `GET /medicines`; React medicine picker |
| `A/dataTables/updateDatatables.php`, `S/dataTables/updateDatatables.php`, `P/dataTables/updateDatatables.php` | Return the catalog table with edit and delete links | `GET /medicines`; React catalog table and edit/archive actions |

The standalone `P/` copy does not contain user-account, backup, or `itemForPurchase.php` pages. It has a dashboard at `P/index.php`; only `M/index.php` is the login form.

The following PHP files are implementation includes, not business endpoints: each copy's `conf.php`, `header.php`, `footer.php`, `navigation.php`, and `dataTables/conf.php`. Their responsibilities move to Laravel configuration/middleware and React FSD app/layout/shared slices. Do not expose the old configuration or HTML include files as API routes.

No legacy user action is retired until its mapped React workflow and API behavior pass feature-parity review. During cutover, keep a redirect or compatibility adapter for any old `.php` URL still used by a person or external link; retire it only after that usage has been checked. Security fixes may change unsafe mechanics while retaining the action: deletes become archive/deactivation/void records, stock writes become audited movements, and global payment marking becomes a per-sale transition.

The legacy schema uses a string for medicine price, stores aggregate stock on the medicine row, and has no sale header. The refactor must use decimal money, lot-based stock, and first-class sale records. Historical transaction lines cannot be grouped into reliable sale headers without auditing the source data. Revalidate and hash credentials; do not copy legacy password values into the new user table.

## Deferred modules

The v1 API does not define resources or endpoints for suppliers, purchase orders, branches, customers, payment records, cash drawers, invoices, or accounting. Add each only with its own data model, permissions, and workflows. Branch support must scope every medicine, lot, sale, user permission, and report to a branch before multi-store use is enabled.
