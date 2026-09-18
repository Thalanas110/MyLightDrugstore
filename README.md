# MyLightDrugstore

Legacy pharmacy inventory and sales application. The original code dates from
2009-2012 and was written by my aunt and Mr. March Jig Tala; I have been
refactoring it.

## Project layout

- `MyLightDrugstore/` is the main copy. It has separate `administrator/` and
  `staff/` areas, with `index.php` as the login page.
- `pharmacy/` is a separate copy with its operational pages directly in that
  folder.

Each folder is a standalone PHP application. To serve one locally, point a PHP
web server at that folder and open its `index.php` page.

## Requirements

- A web server with PHP support, such as Apache.
- A legacy PHP runtime that provides the `mysql_*` functions. These functions
  were removed in PHP 7, so this code does not run on current PHP versions
  without database-layer changes.
- MySQL or a compatible database configured with the expected `dbmedicine`
  schema.

## Database setup

The application connects to the database through `conf.php` files. Connection
settings are repeated in several locations under each app folder, so update the
copies used by the selected app to match your local database.

Database dumps are excluded from this repository because they contain account
or transaction data. A fresh checkout therefore does not include an importable
schema or seed data. Obtain or create a sanitized schema locally before trying
to run the application; do not commit live database dumps.

## Current status

This is a legacy application being refactored. It has no Composer or npm
manifest, automated test suite, or CI workflow at present. The two app folders
also contain overlapping pages and assets.
