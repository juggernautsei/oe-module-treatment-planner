# Testing the Treatment Planner

## Isolated checks

```sh
php tests/review3.php
php tests/create-flow.php
```

The review suite also runs `regression.php` and `followup.php`. It needs no OpenEMR instance, database, API key, or vendor dependencies. PHP 7.4 and later are exercised by CI.

## Template rendering

Use PHP 8.1+ for the locked development dependencies:

```sh
composer install
php tests/template.php
```

The real Twig engine renders history comparisons, pagination, and empty history, and checks HTML escaping. OpenEMR filters and headers are replaced by test adapters. Production installs can use `composer install --no-dev`.

## Database integration

Use MySQL 8.0 with strict SQL mode and PDO MySQL. Supply an **empty disposable database whose name ends in `_test`**. The test refuses nonempty databases or other names, creates module and synthetic OpenEMR tables, and leaves its data for inspection. Use a new empty database for every run.

```sh
export TP_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=treatment_planner_test;charset=utf8mb4'
export TP_TEST_USER='your_test_user'
export TP_TEST_PASSWORD='your_test_password'
php tests/database.php
```

The integration suite executes actual SQL and transactions through thin OpenEMR adapters. It tests repeated migrations, upgrades from the previous cost schema, record preservation, snapshots, archive-failure rollback, stale edits, the approval workflow, history authorization, and provider/model cost reports. It does not call the external AI service.

GitHub Actions runs isolated checks on PHP 7.4/8.3/8.5 and database/template integration on PHP 8.3 with a fresh MySQL 8.0 service. Dependency versions are recorded in `composer.lock`.

## Staging acceptance in OpenEMR

Automated adapters cannot establish complete OpenEMR/browser compatibility. On a backed-up staging installation:

1. Upgrade or disable/re-enable the module and verify the six module tables, nullable cost column, and unchanged existing records. Repeat the lifecycle action to confirm repeatability. Verify Weno settings, if installed, remain unchanged.
2. Check view-only, writer, and signer roles, selected-chart restrictions, and restricted encounter sensitivity.
3. Generate a plan from an assessment containing coded list fields and verify readable labels reach the prompt. Confirm unmapped fields are explicit.
4. Exercise draft → review → approved → finalized; attempt edits after finalization and stale saves from two tabs.
5. Open History from a finalized plan, compare different versions, page through more than 25 revisions, and verify other patients' versions cannot be read.
6. Test AI revision, API errors/timeouts, unknown cost rates, configured rates, and browser Print/Save as PDF.
7. Confirm all generated or revised clinical text is reviewed by a clinician before approval.


Version 1.0.3 adds tests for complete assessment-to-prompt propagation, secure initialization, temperature validation, request-key matching/ownership, replay without another AI call, concurrent claims, uncertain failures, plan/request completion rollback, initializer rollback, preserved form notes, and POST-to-GET redirects. The database suite uses a second MySQL connection and test-only failure triggers to exercise races and rollback. In staging, also test refreshing a successful result, submitting the same form twice, and deliberately retrying from a new form after inspecting the plan list.
