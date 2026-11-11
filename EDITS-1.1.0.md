# Edits in version 1.1.0

## Assessment evidence and completeness

- Added an administrator-configurable active LBF assessment form and validated canonical-to-local field aliases. Default LBF_MHINTAKE remains supported; no form is installed automatically.
- Select only the latest nondeleted assessment instance in the selected encounter. Preserve local field attribution and resolved list labels, allowed demographics, chart problem-list entries and submitted observations in an immutable generation-time snapshot.
- Show missing or uncertain key inputs before generation. A presenting concern is required; other key gaps require acknowledgement. Bind the reviewed preview to actual input with a fingerprint so stale input must be reviewed again.
- Provide source identifiers to the prompt and request citations and explicit AI-suggestion labels. Show frozen sources separately from AI-assisted plan text. References are not automatically verified factual claims; clinician checking remains necessary. Older plans state that original evidence is unavailable.

## Clinician review and ongoing progress

- Require three explicit review acknowledgements server-side before approval or finalization. Record the clinician, resulting plan version, status, optional note and timestamp in the same transaction as the update.
- Add append-only goal/measurement follow-up records with actor, encounter and plan-version attribution. Enforce selected chart, write permission and encounter sensitivity; preserve finalized plan text/status/version. Retries are idempotent and stale-version errors preserve notes without silently rebasing.
- Add readiness, source, review and progress UI and documentation. Clinical text is escaped; only the existing restricted plan renderer introduces display markup.

## Migration and tests

- Database version 6 adds evidence, review and progress tables. Existing records, finalized plans and earlier revision history remain unchanged. Run the module upgrade when deploying; sql/upgrade-1.1.0.sql contains the additive table definitions.
- Added source/profile, review, progress/permissions, stale-preview, escaping and real database tests. Database suite exercises actual latest-form selection and alternate layouts.
- Tested the actual core session/CSRF APIs and bundled Twig runtime from official OpenEMR 7.0.3 and local 8.5.0 images. Added pinned-image compatibility CI alongside the PHP matrix.
- Browser checks verified that missing-gap acknowledgement blocks generation and preserves notes. One acknowledged synthetic draft saved with its frozen evidence. Added visible warnings for missing/unknown source references and code-shaped values absent from recorded inputs after the model omitted citations and suggested an unmatched code. The transition to Pending Review was confirmed. Full approval/finalization checklist and progress browser checks remain pending after a shared chart-context change caused access to be refused. No password or permissions were changed. See docs/COMPATIBILITY.md for exact verification limits.
- One live AI request was made using synthetic assessment data for this release. The result demonstrates that citation compliance and clinical correctness are not guaranteed by prompting; clinician evaluation remains necessary. No API key, credentials, database dump, synthetic fixture or PDF is included. Original root README is unchanged.

More detail: docs/ASSESSMENT-PROFILES.md and docs/CLINICIAN-REVIEW-AND-PROGRESS.md.
