# Edit log: version 1.0.5

This release implements the four follow-up improvements: safe errors, persisted generation provenance, structure checks before approval, and injectable dependencies. The original root `README.md` remains unchanged.

## Safe error boundaries

Module-authored validation messages use `UserFacingException` and stay actionable, including chart access, missing assessment, stale edits, workflow transitions, and missing sections. Unexpected database, transport, or programming failures are mapped to a short reference ID.

The same ID is logged with operation, exception type, sanitized code, source filename and line. Raw exception messages, SQL statements, arguments, full paths, stack traces, credentials, and clinical notes are deliberately omitted. Safe wrappers around unexpected causes also receive a reference. This trades some diagnostic detail for reduced exposure; reproduce difficult failures in a controlled staging environment rather than enabling sensitive logs on a live site.

Controller HTML/JSON responses, routing failures, setup operations, initialization, usage retries, and boundary logging use the shared handler. Known 404 errors retain a plain not-found message. Edit recovery continues to preserve submitted text and its original version token even when the underlying error is redacted.

## Persist generation provenance

New plans store an immutable `generation_metadata` JSON document containing available fields from an allowlist:

- Resolved model and configured/requested model.
- Prompt-template version (`PromptGenerationService::VERSION`).
- Generation time in UTC.
- Input, output, and total token counts.

Options, prompts, observations, responses, credentials and arbitrary extra fields are not copied into this metadata. Generation time and prompt version are captured at creation; clinician edits preserve the original generation details. The plan view exposes these as **Original generation details** with escaped values. Existing plans retain null/unknown metadata rather than receiving invented model or time information. Revision history still tracks clinician content separately; original metadata does not imply every later edit was AI-generated.

The prompt version must be advanced when prompt behavior changes. Provenance supports traceability; it does not guarantee reproducible AI output or establish clinical correctness.

## Validate expected structure before approval

`PlanSectionParser` is shared by extraction and validation. It recognizes supported plain-text, numbered, Markdown, and HTML headings, including common default-template variants.

Drafts and pending-review records may be incomplete and remain savable. Views and edit forms flag missing or empty sections. Server-side approval and finalization require nonempty Diagnosis, Treatment goals, Objectives, Interventions, Timeline, and Criteria for success sections. An incomplete approval attempt rolls back without altering the version or status.

This is a structural check only: it cannot assess clinical accuracy or adequacy. Existing finalized records remain read-only; warnings do not reopen them. An older approved record missing the required structure must return through draft/review before finalization.

## Dependency injection and default wiring

`TreatmentController` accepts treatment, AI, configuration, Twig, and structure dependencies. `TreatmentService` accepts assessment, request-tracking, and structure dependencies. The AI service accepts a parser. `ModuleServiceFactory` centralizes default construction, preserving OpenEMR's existing zero-argument router behavior.

New isolated tests construct the controller through its public constructor with supplied doubles, instead of relying on reflection or a live OpenEMR kernel. Older tests remain for compatibility coverage.

## Upgrade

The module is **1.0.5**, database version **5**. Back up the site database, deploy the files, then run Upgrade or disable/re-enable the module. The repeatable automatic migration adds a nullable `generation_metadata` column to the plan table and preserves existing records. There are still seven module tables.

`sql/upgrade-1.0.5.sql` is a manual fallback for a database already at 1.0.4; run that fallback once only. Prefer the automatic migration for repeatable upgrades. Apply the upgrade before reopening module routes.

## Verification

Expanded tests exercise error redaction and matching reference IDs, actionable validation, controller error recovery, explicit dependency construction, parser variants, missing/empty sections, approval/finalization gates, migration repeatability, provenance storage/immutability, and escaped metadata display. Tests use synthetic data, a real MySQL service and Twig, plus OpenEMR/AI adapters. Live OpenEMR and clinical staging verification remains necessary before deployment.
