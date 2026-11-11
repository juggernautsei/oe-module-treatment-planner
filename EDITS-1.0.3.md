# Edit log: version 1.0.3

This release addresses the four issues from the third review. It preserves the original root `README.md` and links this log from the additional displayed README and consolidated improvements document.

## Complete assessment information in prompts

`AssessmentService::formatForAI()` now formats every assessment section the module extracts: diagnoses, presenting concerns, the mental status examination, risk details, clinical history, functional assessment, diagnostic impressions, treatment history, psychosocial factors, strengths, and resources.

Medication and allergy information, risk plans/means/history/protective factors, and documented negative or zero values are retained instead of being silently omitted. Nested field labels preserve context. The basic patient section remains restricted to age, gender, and race; unexpected top-level fields are not included. Free-text clinical notes may still contain identifying information and require the site's normal review before external processing.

Explicit risk prompt routing recognizes the new structured field path without adding broad narrative keyword matching. This remains prompt routing, not a clinical risk assessment.

## Secure, non-billable template initialization

The old initializer's broken live AI test has been removed. Opening the initializer performs no writes or external AI calls. Creating defaults requires a logged-in administrator with module-management permission and a POST containing a valid scoped CSRF token.

Initialization uses a database advisory lock and a transaction. It creates all three defaults, attributes them to the administrator, or rolls back an incomplete attempt. Existing templates are preserved and repeated submissions do not duplicate them. The page escapes output, provides a configuration link, and logs failures without displaying stack traces.

## Duplicate generation prevention

Every create form receives a fresh random request key. Before starting assessment extraction or an external AI call, the server records the key with the author, patient, encounter, and a hash of the submitted observations in the new `module_treatment_planner_requests` table.

- Racing claims are serialized with an advisory lock.
- Completed duplicate submissions return the existing plan without another AI call.
- A reused key with different observations or a different author/patient/encounter is rejected.
- Requests already in progress or failed are not automatically retried: a network timeout or worker interruption may already have incurred a charge.
- Saving the plan and marking the request completed happen in one transaction.
- Successful creation responds with a **303 redirect** to the plan's GET view, so refreshing the result does not resubmit the form.
- Errors preserve the submitted notes and provide a fresh form key. A deliberate submission from a new form is a new generation and may incur another charge. Users should check the plan list first after an uncertain failure.

This protects repeated submissions of the same form. Separately opened forms intentionally have different keys. The ledger does not store the observation text itself, but its hashes and patient linkage still require the same database access and retention protections as the rest of the module.

## Setup corrections

- Replaced the missing test-page link with the protected default-template initializer.
- Corrected the Create Plan link to the registered route.
- Aligned the temperature field, help text, and server validation to **0–1**. Invalid, nonnumeric, nonfinite, and out-of-range values are rejected instead of silently clamped.
- Validate temperature before writing settings; missing CSRF input fails cleanly.
- Clarified that cost estimates require administrator-configured model rates.
- Removed generation stack-trace logging to avoid recording clinical arguments in logs.

## Upgrade

The module is version **1.0.3**, database version **3**. Back up the site database, deploy the files, and run Upgrade or disable/re-enable the module to create the request ledger automatically. The module checks for all six tables before allowing generation. `sql/upgrade-1.0.3.sql` is an additive, repeatable fallback for sites already upgraded to 1.0.2.

Do not delete request records casually: removing a completed key can remove replay protection for that old form. No migration here deletes existing clinical records.

## Validation

Tests cover assessment fields reaching the final prompt, retained negatives, initialization ACL/CSRF/method checks and atomicity, temperature boundaries, create-form notes/escaping, request ownership and input matching, repeated/concurrent/failed submissions, and the successful 303 redirect.

The database suite uses real MySQL transactions and separate connections for a racing claim. AI responses, CSRF checks, and OpenEMR hooks are test adapters; no live AI requests or real patient data are used. See [testing instructions](tests/README.md). Full staging verification in the deployed OpenEMR site is still required.
