# MyLightDrugstore API

**Status:** Proposed contract for the refactor. These API endpoints are not implemented yet.

This document describes the target API and maps it to the current PHP application. The first release is a single-store pharmacy system for medicine catalog, inventory and receiving, sales, staff accounts, and reports.

## Scope

### Version 1

- Authentication and the `admin` and `staff` roles
- Medicine catalog
- Inventory on hand, stock receipts, lots, expiry dates, and adjustments
- Sales and sale cancellation
- Staff account administration
- Inventory and sales reports

### Later phases

Supplier management, purchase orders, multiple branches, customer records, payments, and finance are outside this contract. The v1 model is single-store; adding branches later will require an explicit data migration and store-level authorization design.

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
- Transport: HTTPS in deployed environments; JSON request and response bodies
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

| HTTP status | Use |
| --- | --- |
| `200` | Successful read or update |
| `201` | Resource created |
| `204` | Successful action with no response body |
| `401` | Missing or invalid authentication |
| `403` | Authenticated user lacks permission |
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
| Create or update medicines; archive a medicine | Yes | No |
| Record stock receipts | Yes | Yes |
| Record stock adjustments | Yes | No |
| Create, read, and cancel sales | Yes | Yes |
| Read inventory and sales reports | Yes | Yes |
| Create, update, or deactivate staff accounts | Yes | No |

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

A sale has a stable ID, creation time, status (`completed` or `cancelled`), creator, line items, and totals. Each line item records the medicine ID, quantity, unit-price snapshot, and line total. Payment status and payment records are not part of v1.

## Endpoints

All paths below are relative to `/api/v1`. Unless marked public, endpoints require an authenticated session. The documented target endpoints do not exist in the current code yet.

### Authentication

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /auth/csrf` | Public | Start an anonymous session and return a CSRF token |
| `POST /auth/login` | Public, CSRF protected | Start an authenticated session |
| `POST /auth/logout` | Authenticated | End the current session |
| `GET /auth/me` | Authenticated | Return the current user's profile and role |

### Medicines

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /medicines` | Admin, staff | Search and page through active medicines |
| `POST /medicines` | Admin | Create a medicine |
| `GET /medicines/{medicineId}` | Admin, staff | Read a medicine and current stock total |
| `PATCH /medicines/{medicineId}` | Admin | Update catalog fields |
| `POST /medicines/{medicineId}/archive` | Admin | Stop new use without deleting sales history |

`GET /medicines` supports `q`, `active`, `lowStock`, `expiresBefore`, `page`, and `perPage`. Do not hard-delete a medicine referenced by stock or sales history.

Create/update fields are `genericName`, `brandName`, `description`, `dosageForm`, `strength`, `unitPrice`, and `storageLocation`. The server validates lengths and numeric ranges and computes stock totals.

### Inventory

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /inventory` | Admin, staff | List on-hand totals, low-stock items, and expiry summaries |
| `GET /inventory/lots` | Admin, staff | Search lots by medicine, expiry, and availability |
| `POST /inventory/receipts` | Admin, staff | Receive stock and create lots |
| `POST /inventory/adjustments` | Admin | Record a reasoned correction against one or more lots |
| `GET /inventory/movements` | Admin, staff | Read the append-only stock movement history |

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

Adjustment reasons are `stock_count`, `damage`, `expiry`, and `correction`. Every adjustment records the actor, timestamp, reason, and affected lot. A correction is a new movement; movement history is never edited or deleted.

### Sales

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /sales` | Admin, staff | Search sales by date, status, or creator |
| `POST /sales` | Admin, staff | Create and complete a sale |
| `GET /sales/{saleId}` | Admin, staff | Read a sale and its line items |
| `POST /sales/{saleId}/cancel` | Admin, staff | Cancel a sale and record compensating stock movements |

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

The client does not submit prices or totals. The server loads current prices, validates quantities, chooses eligible lots using FEFO, computes totals, and commits the sale and stock movements atomically. Require an `Idempotency-Key` header on sale creation so retrying a timed-out request cannot create a duplicate sale.

Cancel request:

```json
{
  "reason": "entered_in_error"
}
```

Cancellation preserves the sale and its original line items, records who cancelled it and why, and restores stock through new movements. Repeating a cancellation does not restore stock a second time. A completed sale is never removed with `DELETE`.

### Staff accounts

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /users` | Admin | List accounts |
| `POST /users` | Admin | Create an account |
| `GET /users/{userId}` | Admin | Read an account |
| `PATCH /users/{userId}` | Admin | Update name, role, or active state |
| `POST /auth/change-password` | Authenticated | Change the current user's password |

Create request fields are `username`, `fullName`, `password`, and `role`. Roles are `admin` or `staff`. The password-change request contains `currentPassword` and `newPassword`. Deactivate accounts instead of deleting users referenced by sales or inventory movements. Password reset for another user requires a separate audited workflow and is not part of this contract.

### Reports

| Method and path | Permission | Purpose |
| --- | --- | --- |
| `GET /reports/inventory` | Admin, staff | Current stock, low-stock items, and expiring lots |
| `GET /reports/sales` | Admin, staff | Sales count and gross sales totals for a date range |

Report endpoints accept `from` and `to` dates and return aggregate data, not payment reconciliation. Inventory reports accept `lowStock` and `expiresBefore`. Reports are read-only and use the same pagination conventions when returning row-level results.

## Inventory and sales invariants

- Quantities are positive integers for receipts and sale lines. Adjustment deltas may be positive or negative but must leave a lot at zero or above.
- Expired and depleted lots cannot fulfill sales.
- A sale fails with `409 insufficient_stock` if eligible stock is not available; no partial sale or stock change is committed.
- Receipt, adjustment, sale, and cancellation writes are transactional and create immutable movements.
- Sale creation and stock receipt accept idempotency keys. Repeating a key with the same request returns the original result; reusing it with a different request returns `409 idempotency_key_reused`.
- Store all timestamps in UTC and convert for display in the frontend.
- Keep an audit record for account changes, inventory adjustments, and sale cancellation.

## Current PHP behavior and target mapping

The repository currently contains server-rendered PHP applications with direct `mysql_*` database calls. It has no JSON REST API. `MyLightDrugstore/` contains the login page plus duplicated administrator and staff areas; `pharmacy/` is another application copy.

| Existing pages or behavior | Target API area | Notes for the refactor |
| --- | --- | --- |
| `MyLightDrugstore/index.php`, `logout.php` | Authentication | Current login uses PHP sessions and role-specific legacy account fields. |
| `administrator/` and `staff/` `inventoryItems.php`, `editInventory.php`, `updateQuantity.php`, `deleteInventory.php` | Medicines and Inventory | The same pages are duplicated. Target authorization is enforced by Laravel policies. |
| `itemForPurchase.php`, `inventoryStatus.php` | Inventory and Reports | Current low-stock and inventory views become filters and report resources. |
| `tbldrugporeceipt` table; `purchaseOrderMaintenance.php` | Inventory receipts | Receipt data exists in the schema, but the maintenance page is only a placeholder. Supplier purchase orders remain deferred. |
| `salesOrderEntry.php`, `cart.php`, `orderProcessing.php`, `cancelOrder.php` | Sales | Current records are sale lines without a proper sale header. Cancellation currently deletes lines and restores aggregate stock. |
| `updateOrder.php` | Later finance/payment work | Current code marks all `UNPAID` lines as `PAID`; this global status update is not part of the v1 API. |
| `accounts.php`, `userAccounts.php`, `deleteUserAccount.php`, `changeAdminAccount.php` | Staff accounts | Current account handling is legacy and must be migrated to per-user identities with hashed passwords. |
| `reports.php`, `transactionDetails.php`, `printTransaction.php` | Reports | Target reports are read-only API resources with explicit date filters. |

The legacy schema uses a string for medicine price, stores aggregate stock on the medicine row, and has no sale header. The refactor must use decimal money, lot-based stock, and first-class sale records. Historical transaction lines cannot be grouped into reliable sale headers without auditing the source data. Revalidate and hash credentials; do not copy legacy password values into the new user table.

## Deferred modules

The v1 API does not define resources or endpoints for suppliers, purchase orders, branches, customers, payments, cash drawers, invoices, or accounting. Add each only with its own data model, permissions, and workflows. Branch support must scope every medicine, lot, sale, user permission, and report to a branch before multi-store use is enabled.
