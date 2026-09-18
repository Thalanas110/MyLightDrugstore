# Bounded Backend Test Suites Implementation Plan

> **For agentic workers:** Execute this plan inline in the current session, respecting repository instructions and the user's authorization to continue the backend work.

**Goal:** Run every backend test exactly once in named, coverage-enabled PHPUnit suites that each fit within the repository's 110-second command limit.

**Architecture:** Keep PHPUnit as the test runner and define non-overlapping suites for unit, architecture, feature foundation, feature business modules, and feature transport. Make an architecture test compare those suite definitions with every `*Test.php` file so additions cannot silently fall outside CI. Run all five suites in the existing CI job, emit one Clover report per suite, and upload all reports together.

**Tech Stack:** PHP 8.5, PHPUnit 12, Laravel 13, GitHub Actions, Clover coverage.

## Global Constraints

- Run the full relevant CI gate after test, configuration, dependency, database, or workflow edits.
- Preserve every existing test and assertion; do not use focused tests, skips, or weakened assertions to obtain a green result.
- Keep each PHPUnit execution lane below 110 seconds.
- Validate workflow YAML and command forwarding after workflow edits.
- Preserve the MySQL migration smoke test and upload all per-suite coverage reports.

---

### Task 1: Add suite coverage registration test

**Files:**
- Create: `backend/tests/Architecture/PhpunitSuiteRegistrationTest.php`
- Test: `backend/tests/Architecture/PhpunitSuiteRegistrationTest.php`

**Interfaces:**
- The test reads `backend/phpunit.xml` and verifies suites named `Unit`, `Architecture`, `FeatureFoundation`, `FeatureBusiness`, and `FeatureTransport` cover each `backend/tests/**/*Test.php` path exactly once.

- [x] **Step 1: Write the failing registration test**

Parse the PHPUnit XML test suite nodes, expand each `directory` using its `suffix`, add explicit `file` entries, apply directory exclusions, and compare normalized paths with the complete recursive set of `*Test.php` files. Assert that the five suite names are present and that there are no duplicate or missing paths.

- [x] **Step 2: Run the focused architecture test**

Run: `php artisan test --compact tests/Architecture/PhpunitSuiteRegistrationTest.php`

Expected: FAIL because the existing configuration still has one broad `Feature` suite and no complete named partition.

---

### Task 2: Define disjoint PHPUnit suites and Composer scripts

**Files:**
- Modify: `backend/phpunit.xml`
- Modify: `backend/composer.json`
- Test: `backend/tests/Architecture/PhpunitSuiteRegistrationTest.php`

**Interfaces:**
- `Unit` contains `tests/Unit`.
- `Architecture` contains `tests/Architecture`.
- `FeatureFoundation` contains `tests/Feature/Http` and `tests/Feature/ExampleTest.php`.
- `FeatureBusiness` contains `tests/Feature/Modules/Catalog`, `Identity`, `Inventory`, and `Operations`.
- `FeatureTransport` contains the remaining tests below `tests/Feature/Modules`, excluding those four business directories.

- [x] **Step 1: Replace the broad Feature suite with the three disjoint feature suites**

Retain the existing `Unit` and `Architecture` suites. Define the exact feature paths above; use PHPUnit directory exclusions for business-module paths in `FeatureTransport`.

- [x] **Step 2: Keep Composer feature scripts aligned**

Change `test:feature` to run `FeatureFoundation`, `FeatureBusiness`, and `FeatureTransport` in order. Keep `test:unit`, `test:architecture`, and the complete `test` script available.

- [x] **Step 3: Verify the registration test and list each suite**

Run: `php artisan test --compact tests/Architecture/PhpunitSuiteRegistrationTest.php`

Then run `php artisan test --list-tests --testsuite=<suite>` for all five suite names. Expected: every suite resolves, and the registration test confirms the union is complete and disjoint.

---

### Task 3: Bound CI test commands and preserve per-suite coverage

**Files:**
- Modify: `.github/workflows/backend.yml`

**Interfaces:**
- The workflow runs each of `Unit`, `Architecture`, `FeatureFoundation`, `FeatureBusiness`, and `FeatureTransport` through its own `timeout 110s php artisan test --compact --coverage-clover=... --testsuite=...` command.
- Reports are written to `storage/framework/cache/coverage/<suite>.xml` and uploaded together.

- [x] **Step 1: Keep setup and MySQL migration verification unchanged**

Retain the current locked dependency install, test environment setup, and `timeout 110s php artisan migrate:fresh --force` MySQL smoke test.

- [x] **Step 2: Add one named coverage step per suite**

Each step creates the coverage directory and runs the matching named suite with a 110-second bound. The step name includes the suite name so CI logs identify failures directly.

- [x] **Step 3: Upload all Clover reports**

Set the artifact path to `backend/storage/framework/cache/coverage/*.xml` and retain `if-no-files-found: ignore`.

- [x] **Step 4: Validate workflow syntax and command forwarding**

Parse `.github/workflows/backend.yml` with an available YAML parser, inspect all five exact `--testsuite` and coverage output arguments, then run `git diff --check`.

---

### Task 4: Run the complete local gate by suite

**Files:**
- Verify: `backend/tests`, `backend/phpunit.xml`, `backend/composer.json`, `.github/workflows/backend.yml`

- [x] Run all five suite commands with coverage, sequentially, each bounded at 110 seconds. Confirm every suite passes and produces a non-empty Clover report.
- [x] Run `php vendor/bin/pint --test`, `php vendor/bin/phpstan analyse --level=9`, `composer validate --strict --no-check-publish`, and `git diff --check`.
- [x] Confirm the suite registration test detects every test file exactly once and the worktree contains only this CI partition plus the active catalog endpoint work.
