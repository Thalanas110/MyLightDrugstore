# Lovable System Prompt: MyLightDrugstore Frontend Prototype

Build a complete, interactive React prototype for the MyLightDrugstore pharmacy inventory and sales system. The prototype must preserve the workflows in the existing PHP application and follow the target API contract below. This is a frontend-only prototype: use deterministic mock data and a local mock API adapter for every operation. Do not create, call, or require a Laravel server, PHP server, database, Supabase project, external API, or real authentication service.

## Product goal

Create one cohesive pharmacy operations application for administrators and staff. Bring together the overlapping `MyLightDrugstore/` and `pharmacy/` PHP copies in one React app. Keep the current work recognizable: staff sign in, look up medicines by item code, add quantities to an unpaid cart, print the order, and mark it paid; staff also manage stock and view reports. Administrators additionally manage staff accounts and backup jobs.

Preserve the function of every current user-facing PHP page and action listed in **Legacy behavior and screen coverage**. Replace old page loads, popups, and HTML DataTables fragments with React routes, modals, tables, and print views. Do not drop an action because it is awkward or old. Do not reproduce unsafe data handling where the target API specifies a safer equivalent; the one known sales transition difference is called out below.

## Hard implementation boundaries

- Generate a React frontend only. Use the existing Lovable React/TypeScript setup when available; do not generate Laravel, PHP, SQL migrations, or backend services.
- This frontend is for the planned React Feature-Sliced Design (FSD) client and future Laravel modular monolith. Keep the client/API boundary ready for that backend, but build no backend now.
- Put every data read and write behind a typed `ApiClient` interface. Implement a `MockApiClient` whose routes, parameters, request shapes, response envelopes, permissions, state transitions, and errors follow this prompt. Do not call `fetch`, `axios`, Supabase, or any live network service.
- Keep mock API behavior replaceable with Laravel later. Components must not read or mutate seed arrays or local storage directly.
- Make every visible navigation item, form, filter, table action, print button, and confirmation dialog work. No dead links, fake controls, or decorative buttons.
- Use invented demo records only. Do not include live account, patient, supplier, or transaction data from any database dump.
- Do not add suppliers, purchase orders, branches, customers, partial payments, payment ledgers, refunds, cash drawers, invoices, reconciliation, or accounting. These are later phases. `purchaseOrderMaintenance.php` is only a stock list with a quantity-add link; it is not a supplier purchase-order workflow.
- Do not add prescribing, diagnosis, dose recommendations, or patient records. The app manages medicine inventory and store sales only.

## Source behavior to preserve

The current applications are server-rendered PHP pages that call MySQL directly. They have no JSON REST API. `MyLightDrugstore/` has the login page plus duplicated administrator and staff pages. `pharmacy/` is a standalone copy; its `index.php` is a dashboard, not a login page.

Keep these legacy behaviors recognizable:

- The medicine record includes generic name, brand name, description, suspension/form, dosage, price, quantity, and storage location. In the target API, map suspension/form to `dosageForm`, dosage to `strength`, price to `unitPrice`, and location to `storageLocation`.
- Creating a medicine accepts an initial quantity. The legacy edit form also lets the user change the displayed current quantity and enter a separate **new quantity to add**. The stock-maintenance page's quantity field is an amount to add. In React, preserve both capabilities without writing aggregate stock directly: save catalog fields through `POST /medicines` or `PATCH /medicines/{medicineId}`, record a count correction through lot-level `POST /inventory/adjustments`, and record initial/additional stock through `POST /inventory/receipts`. Ask for an expiry date for every received lot.
- The legacy low-stock report includes stock totals strictly below 30 units. Use a configurable mock threshold defaulting to 30 and apply the same rule consistently: `stockOnHand < 30`.
- Sales entry looks up the numeric item code, shows the medicine details and remaining quantity, and lets the user enter a requested quantity. Reject zero, negative, or above-stock quantities. Price and line total are computed from the current medicine price, not typed by the cashier.
- The cart shows medicine, brand, item cost, quantity, line amount, and grand total. A line can be removed; stock is returned. Printing an order prints the unpaid sale details.
- The old `updateOrder.php` marks every unpaid transaction line in the database as paid. Preserve the ability to mark an order paid, but follow the target contract: mark **one selected sale** paid and completed. Never implement a global action that pays unrelated sales. The action records only a paid/unpaid marker; it does not collect tender details or create a payment ledger.
- The old transaction report selects a date and shows transaction detail grouped by brand with total quantities; it can be printed. Keep date selection, item-level detail, totals, and a print view.
- The old “Item for Purchase” report is the under-30-unit low-stock list. The purchase-order maintenance page lists stock and links to add stock; it does not create purchase orders.
- Account management creates staff accounts using a full name and password and can delete them. The new interface uses the target `username`, `fullName`, `password`, and `role` fields; deactivate an account instead of deleting it. Never show or return a password in account lists.
- The admin account page links to staff account management and changing the signed-in administrator password. The target app lets either role change their own password after entering the current password.
- The old backup page writes a database dump to a local file. The prototype only simulates a backup job; it never reads or downloads a real database dump.
- The legacy clock displays server date/time. Show the mock `/system/time` value in the signed-in app shell.

## Roles and permissions

Implement `admin` and `staff` roles. Enforce them in route guards, navigation, and the mock API adapter. Hiding a button is not enough: the mock API must return a `403` for a forbidden operation.

| Capability | Admin | Staff |
| --- | --- | --- |
| Read, create, update, and archive medicines | Yes | Yes |
| Read inventory, receive stock, and record adjustments | Yes | Yes |
| Create, read, update, remove lines from, complete, and cancel sales | Yes | Yes |
| Read inventory and sales reports | Yes | Yes |
| Change own password | Yes | Yes |
| List, create, update, and deactivate staff accounts | Yes | No |
| Request a backup job | Yes | Yes |
| List backup jobs and download a completed backup | Yes | No |
| Read the system clock | Yes | Yes |

Provide demo sign-in buttons for **Admin demo** and **Staff demo** that call the mock `POST /auth/login` operation. Also provide a normal username/password form. Use clearly invented demo identities; never copy credentials from the old database. Since authentication is mock-only, accept a non-empty password for active demo usernames and do not persist the password. The session is simulated in memory. Do not persist passwords, session tokens, or CSRF tokens in local storage. Business mock data may persist locally so changes survive a page refresh.

## Routes and navigation

Use a shared authenticated app shell with a role-aware sidebar, page title/breadcrumb, current user, live mock clock, and logout action. Suggested client routes:

| React route | Screen |
| --- | --- |
| `/login` | Login and demo-role entry |
| `/dashboard` | Operational overview and medicine table |
| `/operations` | Shortcuts to sales and inventory work, replacing the old order-processing hub |
| `/catalog/medicines` | Searchable medicine catalog; create, edit, archive, and add opening stock |
| `/inventory` | On-hand totals, low-stock and expiry summaries, stock table |
| `/inventory/receipts` | Receive stock into dated lots; this is the modern stock-maintenance/add-quantity flow |
| `/inventory/adjustments` | Correct lot quantities with a reason |
| `/inventory/movements` | Read-only stock movement history |
| `/sales/new` | Item-code lookup, medicine picker, and add-to-cart flow |
| `/sales/cart` | Open unpaid sale, line removal, total, print, complete, and cancel actions |
| `/sales` | Searchable sale history with open/completed/cancelled and unpaid/paid filters |
| `/sales/print` | Print layout for the selected unpaid or completed sale |
| `/reports` | Links to inventory and sales reports |
| `/reports/inventory` | Inventory status, low-stock list, and expiring lots |
| `/reports/sales` | Date-filtered transaction detail and totals with print action |
| `/admin/users` | Admin-only staff account management |
| `/settings/security` | Change the signed-in user's password |
| `/backups` | Request a mock backup; admin can also view and download mock job artifacts |

Staff may use all staff-allowed v1 routes. Admin routes must redirect or show a clear permission message for staff. The original staff navigation hid some existing direct routes even though those workflows exist; expose the v1 staff capabilities from the permission table. Do not create separate copies of the same page for admin and staff.

## Screen requirements

### Dashboard

- Show useful values derived from mock API data: medicine count, total stock units, low-stock items, expiring lots, unpaid sales, and sales totals for the selected day or date range.
- Provide a clear path to start a sale, receive stock, review low stock, and open reports.
- Include a compact medicine table with search, stock, price, and location.
- Do not show made-up charts or values that do not reconcile with the underlying mock records.

### Medicine catalog

- Search by generic name, brand, description, item ID, and location. Support pagination and active/archive filtering.
- Table columns: item code, generic name, brand, dosage form, strength, stock on hand, unit price, location, and status/actions.
- Create/edit fields: `genericName`, `brandName`, `description`, `dosageForm`, `strength`, `unitPrice`, `storageLocation`.
- Keep legacy terminology recognizable where helpful (for example “Suspension / dosage form” and “Dosage / strength”).
- Create/edit may include an “Opening stock” or “Add stock” section with quantity and expiry. Save catalog fields through the medicine endpoint and stock through the receipt endpoint. If either call fails, show which step failed and keep the form recoverable.
- Preserve the legacy edit form's ability to correct the current quantity and add another quantity. Make those separate actions: a lot-by-lot stock count correction with reason `stock_count` or `correction` uses `POST /inventory/adjustments`; newly received stock uses `POST /inventory/receipts`. Do not send `stockOnHand` in a medicine create/update request.
- Archive instead of hard-delete. Archived medicines stay visible when the user enables the archived filter and never disappear from sale history.

### Inventory and receiving

- The inventory overview shows stock on hand calculated from unexpired, available lots, low-stock count, expiring-soon lots, and expired lots. Do not let users edit the computed total directly.
- Make `stockOnHand < 30` the default low-stock rule, with the threshold stored in one mock configuration value.
- Receiving form supports a receipt time and one or more lines containing medicine, positive integer quantity, and expiry date. On submit call `POST /inventory/receipts`; add lots and movements atomically.
- The “Add quantity” action on a medicine opens the same receiving form with that medicine selected. The entered quantity is additive.
- Adjustment form supports one or more lot deltas and reasons `stock_count`, `damage`, `expiry`, and `correction`. Reject any adjustment that would put a lot below zero. Save an immutable movement with actor, reason, and time.
- Display lot quantities and dates, and consume sale stock by first-expiring-first-out (FEFO), excluding expired and depleted lots.
- Movement history is read-only and records receipts, sales, cancellations, and adjustments.

### Sales and cart

- Sales entry supports direct item-code entry and a searchable medicine-picker modal. Show generic/brand names, description, form, strength, price, available quantity, and location before adding.
- A cart belongs to one open sale. When the first item is added, call `POST /sales` with its initial item; subsequent items use `POST /sales/{saleId}/items`. Require a mock idempotency key for both operations.
- Each add action creates its own sale line, even when the same medicine is already in the cart, matching the legacy transaction-detail rows. Do not silently merge duplicate medicine lines.
- Do not accept client-supplied prices or totals. Calculate displayed totals from price snapshots returned by the mock API.
- The open cart shows active line items, quantities, unit prices, line totals, and grand total. Each line can be removed with confirmation. Removal marks the line removed and restores its stock through a compensating movement; retain the removed line in sale detail/audit views.
- Provide **Print order**, **Mark paid / Complete sale**, and **Cancel sale** actions. Mark paid completes only that sale. Cancellation is allowed only for an open unpaid sale, requires a reason, keeps its history, and restores active item stock once.
- Completed sales are paid and cannot be edited or cancelled. Repeating mark-paid or cancellation must not duplicate state transitions or stock movements.
- A `409 insufficient_stock` response leaves the sale and all stock unchanged and gives a useful inline message.
- Include sale list filters for payment status, sale state, date range, and creator. Clicking a row opens the sale detail.
- Printing uses a dedicated print stylesheet and the browser print dialog; it does not create a backend print job.

### Reports

- Inventory report includes all medicine stock rows, low-stock items under the 30-unit threshold, and lots filtered by expiry. Keep filters and a printable layout.
- Sales report supports `from` and `to`, displays sale rows and item-level details grouped by medicine/brand, units sold, sale count, and gross totals. Preserve the old date-selection and print-transaction workflow.
- Show empty states when a date range has no results. All report totals must be derived from filtered mock sales and reconcile with the sale list.
- Reports are read-only.

### Staff accounts and password

- Admin user page lists username, full name, role, active status, and creation date. Never display passwords.
- Admin can create and edit account details and deactivate accounts. Do not hard-delete them. Prevent deactivating the signed-in admin if that would lock the demo out; explain the reason.
- Own-password form asks for current password, new password, and confirmation. Require the current password, require the new password to differ from it, and require confirmation to match. Show field-level errors and a success toast. The API request sends only `currentPassword` and `newPassword`.

### Backups and clock

- Staff and admin can request a mock backup job. Display its ID, queued/running/completed/failed state, creation time, and safe status messages.
- Only admin can list job metadata or use a mock download. A mock download may provide a small metadata text file; never generate a real SQL dump or expose database contents.
- Show time from `GET /system/time`, formatted for the configured store timezone. Keep timestamps in UTC in the API model.

## Target API contract

Base path is `/api/v1`. These routes describe the future Laravel API. In this prototype, implement them as local mock operations with matching names and behavior; do not send HTTP requests. All endpoints require a signed-in session unless marked public.

| Method and path | Access | Purpose |
| --- | --- | --- |
| `GET /auth/csrf` | Public | Simulate starting an anonymous session and returning a CSRF token |
| `POST /auth/login` | Public, CSRF | Sign in a mock user by username and password |
| `POST /auth/logout` | Authenticated | End the simulated session |
| `GET /auth/me` | Authenticated | Return the current mock user |
| `POST /auth/change-password` | Authenticated | Change the current user's password after checking the current value |
| `GET /medicines` | Admin, staff | Search and page active medicines |
| `POST /medicines` | Admin, staff | Create a medicine catalog record |
| `GET /medicines/{medicineId}` | Admin, staff | Read a medicine and computed stock total |
| `PATCH /medicines/{medicineId}` | Admin, staff | Update catalog fields |
| `POST /medicines/{medicineId}/archive` | Admin, staff | Archive without deleting history |
| `GET /inventory` | Admin, staff | Read stock totals, low-stock, and expiry summaries |
| `GET /inventory/lots` | Admin, staff | Search lots by medicine, expiry, and availability |
| `POST /inventory/receipts` | Admin, staff | Receive stock and create lots |
| `POST /inventory/adjustments` | Admin, staff | Record a reasoned lot-level correction |
| `GET /inventory/movements` | Admin, staff | Read immutable stock movement history |
| `GET /sales` | Admin, staff | Search sales by date, status, or creator |
| `POST /sales` | Admin, staff | Create an open unpaid sale with initial items |
| `POST /sales/{saleId}/items` | Admin, staff | Add one item to an open unpaid sale |
| `DELETE /sales/{saleId}/items/{saleItemId}` | Admin, staff | Remove one line from an open unpaid sale |
| `GET /sales/{saleId}` | Admin, staff | Read one sale and its lines |
| `POST /sales/{saleId}/mark-paid` | Admin, staff | Mark one sale paid and completed; no payment record |
| `POST /sales/{saleId}/cancel` | Admin, staff | Cancel an open unpaid sale and restore stock |
| `GET /users` | Admin | List accounts |
| `POST /users` | Admin | Create an account |
| `GET /users/{userId}` | Admin | Read an account |
| `PATCH /users/{userId}` | Admin | Update name, role, or active state |
| `GET /reports/inventory` | Admin, staff | Read stock, low-stock, and expiring-lot report |
| `GET /reports/sales` | Admin, staff | Read date-filtered sales counts and gross totals |
| `POST /backups` | Admin, staff | Request an asynchronous backup job; return `202` and job ID |
| `GET /backups/{backupId}` | Admin | Read backup job status and safe metadata |
| `GET /backups/{backupId}/download` | Admin | Download a completed mock artifact |
| `GET /system/time` | Admin, staff | Return current mock server time |

### Request details and query parameters

- Login body: `{ "username": "staff.demo", "password": "demo-only" }` (demo values only).
- `GET /auth/csrf` returns `{ "data": { "csrfToken": "..." } }`. For the future Laravel client send it as `X-CSRF-TOKEN` on state-changing requests. The mock client simulates this contract without persisting a token or sending a network request.
- Change-password body: `{ "currentPassword": "…", "newPassword": "…" }`.
- Medicine create/update fields: `genericName`, `brandName`, `description`, `dosageForm`, `strength`, `unitPrice`, and `storageLocation`. `stockOnHand` is computed and read-only.
- `GET /medicines` supports `q`, `active`, `lowStock`, `expiresBefore`, `page`, and `perPage`.
- Receipt body: `{ "receivedAt": "<ISO-8601 UTC>", "items": [{ "medicineId": 42, "quantity": 24, "expiresAt": "YYYY-MM-DD" }] }`.
- Adjustment body: `{ "reason": "stock_count", "items": [{ "lotId": 301, "quantityDelta": -2 }] }`. Allowed reasons: `stock_count`, `damage`, `expiry`, `correction`.
- Sale create body: `{ "items": [{ "medicineId": 42, "quantity": 2 }] }`. Add-item body uses one `{ "medicineId": 42, "quantity": 2 }` object. The client never submits a price or total.
- Cancellation body: `{ "reason": "entered_in_error" }`.
- `GET /sales` supports `paymentStatus=unpaid|paid`, `state=open|completed|cancelled`, `from`, `to`, `createdBy`, `page`, and `perPage`.
- Account create fields: `username`, `fullName`, `password`, and `role` (`admin` or `staff`). Account patch fields: `fullName`, `role`, `active`.
- Report endpoints accept `from` and `to`; inventory reports also accept `lowStock` and `expiresBefore`.
- Lists return `{ "data": [], "meta": { "page": 1, "perPage": 25, "total": 0 } }`. Single resources return `{ "data": { ... } }`.
- Errors return `{ "error": { "code": "validation_failed", "message": "The request is invalid.", "details": {}, "requestId": "req_demo" } }`. Use field details where relevant.
- Use decimal strings for money, UTC ISO timestamps, `YYYY-MM-DD` dates, `camelCase` fields, default `perPage=25`, maximum `perPage=100`, and reject invalid fields/enums.
- Model normal successful reads and updates as `200`, creates as `201`, completed no-body actions as `204`, and backup requests as `202` with a job ID.
- Simulate `401`, `403`, `404`, `409`, `422`, and `429` where appropriate. Use `409 insufficient_stock` for unavailable stock and `409 idempotency_key_reused` when a key is reused with a different request.

## Mock data and state rules

- Seed a useful demo dataset on first run: at least 12 medicines, 20 lots, 8 sales across open/unpaid, completed/paid, and cancelled states, plus one admin and several staff users. Include in-stock, low-stock (`<30`), expiring-soon, expired, and archived examples so filters and empty/error states can be exercised.
- Generate dates relative to the current date so expiry and report examples do not become stale. Use fictional account names and transaction values. Never seed real database records.
- Keep `stockOnHand` derived from eligible lots. A medicine's total must update after receipt, sale, removal, cancellation, adjustment, archive, and page reload as appropriate.
- Sale stock allocation uses FEFO. Do not sell expired or depleted lots. If the selected quantity cannot be fully fulfilled, return an error and change nothing.
- Receipt, adjustment, sale creation, line addition/removal, and cancellation must update inventory and movements consistently. Use atomic mock operations: either all affected rows update or none do.
- Sale prices are snapshots. Later changes to medicine price do not alter an existing sale.
- Preserve removed sale lines and movement history. Archive medicines and deactivate users instead of deleting referenced records.
- Idempotency keys are required for sale creation, sale-item addition, and receipts. Repeating the same key and body returns the first result; a different body with the same key returns `409`.
- Persist only mock business data and configuration in local storage. Never persist entered passwords, mock CSRF tokens, or auth secrets. Keep the active mock session in memory; logging out returns to `/login`.

## Legacy behavior and screen coverage

Implement these workflows from both PHP application copies using shared React screens and the API operations above:

| Legacy PHP route/action | Required React behavior |
| --- | --- |
| `MyLightDrugstore/index.php`, `logout.php` | Login, demo role sign-in, protected routes, logout |
| `administrator/index.php`, `staff/index.php`, `pharmacy/index.php` | Shared dashboard; `pharmacy/index.php` was a dashboard, not a login |
| `inventory.php` | Inventory entry and links |
| `inventoryItems.php`, `editInventory.php`, `deleteInventory.php`, `updateInventoryItems.php` | Catalog list/create/edit/archive and initial/additional stock receipt |
| `inventoryStatus.php` | Full inventory status report |
| `itemForPurchase.php` | Low-stock report for medicines below 30 units |
| `updateQuantity.php` | Add an amount to existing stock using a receipt lot; never replace the total |
| `purchaseOrderMaintenance.php` | Stock-maintenance list and add-stock link only; no supplier PO feature |
| `orderProcessing.php` | Operations hub linking stock and sales |
| `salesOrderEntry.php`, `popUpSalesOrderEntry.php` | Item-code lookup, medicine picker, quantity entry, add to sale |
| `cart.php`, `cancelOrder.php` | Open unpaid cart, remove individual line, restore stock, update total |
| `updateOrder.php` | Mark a selected sale paid/completed; do not affect other sales |
| `printOrder.php` | Printable sale/order view |
| `transactionDetails.php`, `printTransaction.php`, `reports.php` | Date-filtered sales detail, inventory/sales reports, print views |
| `accounts.php`, `userAccounts.php`, `deleteUserAccount.php` | Admin account hub, staff CRUD, deactivate instead of delete |
| `changeAdminAccount.php` | Signed-in user changes their own password |
| `backup.php` | Request a mock backup; admin sees/downloads safe job metadata |
| `refresh-me.php` | Mock system-time display |
| `dataTables/dataTables.php` | Dashboard medicine table |
| `dataTables/purchaseOrderMaintenanceDatatables.php` | Stock-maintenance table and add-stock action |
| `dataTables/SalesOrderEntryDataTables.php` | Medicine-picker results |
| `dataTables/updateDatatables.php` | Catalog table and edit/archive actions |
| `conf.php`, `header.php`, `footer.php`, `navigation.php`, `dataTables/conf.php` | Replace with React configuration, app shell, routing, and shared UI; do not expose these as API endpoints |

There are duplicate admin/staff/pharmacy copies of many routes. Build each workflow once and show it according to the target permission matrix. Do not create duplicate pages just to mirror duplicated PHP files.

## React structure and code quality

Organize the frontend using Feature-Sliced Design principles. Keep the exact folder setup compatible with the existing Lovable project, but keep these responsibilities distinct:

```text
src/
  app/       providers, router, session setup, global styles
  pages/     route-level screens
  widgets/   sidebar, header, dashboard panels, tables, report layouts
  features/  login, add medicine, receive stock, record sale, mark paid, etc.
  entities/  medicine, inventory lot/movement, sale, user models and UI
  shared/    typed API interface, MockApiClient, mock seed, UI primitives, utilities
```

Use typed resources and form validation. Keep business rules in mock API/services, not React components. Use one shared table/filter/form/dialog/toast pattern across modules. Include loading, success, empty, and error states for each data operation. Respect keyboard navigation, visible focus, labels, semantic headings, contrast, and responsive layouts.

Use Philippine peso (`PHP`, `₱`) as the mock store currency, format amounts to two decimals, and centralize currency/timezone configuration. Keep timestamps in UTC in mock API objects and display them in `Asia/Manila` by default. Do not hardcode a date that becomes stale.

## Visual direction

Design a calm, modern pharmacy operations console for daily use at a counter. Favor a clear information hierarchy, compact but readable tables, warm white/soft gray content surfaces, deep navy navigation, and restrained teal action color. Use amber for low/expiring status and red for expired, error, or destructive actions. Use consistent status badges for `Open`, `Completed`, `Cancelled`, `Unpaid`, `Paid`, `Low stock`, and `Expired`.

Use a strong desktop layout that also works on tablets and narrow screens: collapsible sidebar, clear page headings, primary action near the heading, filters above tables, row actions in a menu or button group, right-sized modals/drawers, and confirmation for destructive or state-changing actions. Avoid marketing-page patterns, oversized hero sections, decorative gradients, vague labels, and generic filler content. The first screen after sign-in should be the working dashboard, not a landing page.

## Completion checklist

Before considering the prototype complete, verify these flows in the browser:

1. Sign in separately as admin and staff. Confirm role-aware navigation, protected routes, and API-level forbidden actions.
2. Search by numeric item code and by medicine name. Create a medicine with opening stock and expiry; confirm both catalog and lot records are created.
3. Edit medicine details and add more stock. Confirm the entered amount is added to the existing quantity, never used as a replacement total.
4. Receive a lot, record a positive and negative adjustment, and confirm an adjustment cannot make a lot negative.
5. Add more than one item to an open sale, confirm price/total/stock calculations, remove one line, and verify stock is returned once.
6. Mark one sale paid and completed. Confirm unrelated unpaid sales remain unpaid. Cancel an open unpaid sale and verify stock restoration is not duplicated on retry.
7. Search/filter sales; run inventory and date-filtered sales reports; print an order and report.
8. Create/edit/deactivate staff as admin. Confirm staff cannot access user administration or backup downloads, but can request backups and change their own password.
9. Request a mock backup, show its job state, and keep the mock download free of database contents.
10. Refresh the browser and confirm mock business data persists while the session returns to login. Confirm logout clears the session.
11. Search the source to confirm there are no live network calls, Supabase calls, real credentials, or SQL/database connections.
12. Check that every endpoint in the contract is implemented in the mock adapter and that every visible control works.

Deliver the working React prototype in the Lovable project. Do not stop after producing a static mockup or this specification.
