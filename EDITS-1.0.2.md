# Edit log: version 1.0.2

This follow-up fixes the six areas identified in the second code review. It builds on the access-control and record-protection changes merged in PR #1. The root `README.md` is unchanged by this release.

## Installation and lifecycle

- Removed the dependency on Weno's `ModuleService`. Installation and disabling now use OpenEMR's inherited `setModuleActiveState()` for this module only; Weno background services are never changed.
- Corrected the module namespace to match the source and Composer autoload mapping.
- Limited lifecycle dispatch to supported action names and converted lifecycle failures into a visible failure result plus a server-log entry.

## Automatic schema upgrades

- Added `SchemaService` to create missing module tables and upgrade the usage-cost column under a database-specific advisory lock.
- Run the repeatable migration on install, enable, install-SQL, and upgrade-SQL events. Existing plan records, revisions, configuration, and estimates are retained.
- Added a readiness check before public routes and plan writes. Missing/outdated schema produces an upgrade instruction instead of proceeding to an unexplained SQL error or a billable generation request.
- Added `sql/upgrade-1.0.2.sql` as a manual fallback for the nullable cost column. Sites missing the revision table also need `sql/upgrade-1.0.1.sql`, or can run the automatic lifecycle migration.
- Updated the module to 1.0.2 and database version 2.

## Assessment labels

- Resolve selected option codes using the exact `list_id` and `option_id`, including code `0` and pipe-separated multiple selections.
- Pass each field's layout list ID into the resolver, cache lookups per list/code, and explicitly mark unmapped options rather than guessing their meaning.
- Leave free text unchanged and retain historical labels even when an option is no longer active.

## Usage reporting

- Fixed provider aggregation to select `t.provider_id` from the usage table and group provider names explicitly for strict SQL mode.
- Removed dated built-in prices and the fallback that priced unknown models as another model.
- Accept optional administrator-supplied USD rates per million tokens, keyed by exact model name. Missing or invalid rates produce `NULL` costs.
- Return `unpriced_calls` and leave aggregate cost unavailable when a group contains unpriced calls. Callers must display null as “Unavailable,” not zero.
- Added timeouts to the setup connection test as well as the existing generation timeouts.

## Section extraction

- Recognize complete plain-text, Markdown, numbered, and HTML headings.
- Preserve sentences and numbered objectives inside a section.
- Recognize parenthetical heading descriptions and “Criteria for Success,” mapped to the existing `progress` key.

## Revision browsing

- Added a patient-authorized, read-only History route linked from the plan view, including finalized plans.
- Show archived versions, archive timestamps and actors, and page through snapshots in groups of 25.
- Compare any two available versions side by side with escaped content. Missing or foreign-plan versions are rejected.
- The `archived_by` actor identifies who made the update that archived a snapshot; it is not presented as that snapshot's original author.

## Tests

- Retained the original isolated regression checks and added lifecycle, label, parser, pricing, migration-readiness, and history-access checks.
- Added real Twig rendering checks for comparison links, pagination, empty states, and HTML escaping.
- Added real MySQL integration checks for repeatable migrations, preservation of records, atomic revision writes/rollback, stale edits, finalization, patient-scoped history, and strict provider/cost aggregation.
- Added a GitHub Actions workflow for PHP 7.4/8.3/8.5 isolated checks and PHP 8.3/MySQL 8.0 database/template integration. Locked test dependencies are included.

See [test instructions](tests/README.md) for exact commands and environment requirements. Database/template integration uses adapters for OpenEMR dependencies; it does not certify an installed OpenEMR site or live AI service.

## Deployment

Back up the site database. Deploy the updated files, then run the module's Upgrade action or disable/re-enable it to invoke migrations. Confirm readiness, ACL roles, encounter sensitivity, revision comparison, generation/revision, and printing in staging before clinical use. No live site or database is changed by pushing this release to GitHub.
