# Edit log: version 1.0.4

This release fixes the four issues identified in the fourth review. The original root `README.md` remains unchanged.

## Preserve generated output when usage logging fails

Generation and observation revision now isolate usage-recording failures from successful AI responses. Failed usage inserts no longer prevent the generated output from reaching plan storage or the revised text from reaching the editor.

A usage-only database queue stores provider, model, token counts, original timestamp, duration, and estimated cost; it stores no clinical notes, prompts, responses, or API keys. Queue consumption inserts the usage row and deletes its queue event in one transaction. Row locking makes repeated/concurrent retries safe from duplicate usage inserts. Prices and timestamps remain those captured for the original event.

Administrators can use **Configuration → Retry Usage Recording** to process the next 25 queued records. The endpoint requires module-management permission and a scoped CSRF-protected POST. Retrying usage metadata never invokes AI or regenerates a plan.

If even the queue is unavailable, generation still continues. The event is explicitly marked unrecorded and usage metadata is written to the server log for operator reconciliation; the code does not pretend that such an event was durably queued. Recorded usage reports do not include pending or unrecorded events until they are recovered.

## Preserve unsaved edits after save errors

After a save, validation, CSRF, or version-conflict error, the authorized edit screen returns the exact submitted text to its escaped textarea. The submitted version token stays unchanged, so clicking Save again cannot silently overwrite someone else's newer revision. The screen shows the submitted and saved versions and provides a link to open the saved plan in another tab.

If another user finalized the record, submitted text remains available for copying in a read-only recovery screen; there is no save/finalize action. Unauthorized charts still receive an access error instead of a recovery screen.

A successful save followed by a failed refresh is distinguished from an unsuccessful save, and the submitted text is preserved in either case.

## Require encounter linking for successful creation

`addForm()` is required to return a valid encounter-form identifier. A false result or exception aborts the surrounding plan/request transaction instead of being logged and ignored. Successful creation therefore includes the plan, encounter link, and completed request state together. This follows the return contract in OpenEMR's `library/forms.inc.php`.

On linking failure, the user receives a clear message and the plan is not saved. The generation request remains consumed to prevent an automatic repeat of a possibly billable AI call. An already-incurred external charge cannot be undone by rolling back the database transaction.

## Correct displayed patient age

Plan views and edit forms use `PatientAgeService` on the server. Full date arithmetic accounts for whether the birthday has occurred, including age zero and leap-year dates. Missing, invalid, or future birth dates display as unspecified rather than producing a fabricated age.

## Upgrade and validation

The module is **1.0.4**, database version **4**. Back up the site database and run the module upgrade or disable/re-enable it after deploying. The additive `sql/upgrade-1.0.4.sql` migration creates the usage queue; the module now expects seven tables. No existing clinical records are deleted.

Expanded checks cover output preservation during usage and queue failures, retry idempotency and retained timestamps/prices, encounter-link failure rollback, exact edit recovery with stale tokens, finalized-record recovery, post-save refresh failures, and date-based age calculation. Tests use synthetic data and AI/OpenEMR adapters; live OpenEMR staging verification remains necessary.
