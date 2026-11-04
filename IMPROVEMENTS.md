# Consolidated improvements in the perwriter fork

This is the unified overview of improvements made to the OpenEMR Treatment Planner fork. The original project's authorship and GPL license are retained. The existing root [README](README.md) remains separate.

| Area | Improvements implemented |
| --- | --- |
| Access control | Medical-record ACL checks; full write permission for edits; signing permission for approval/finalization; selected-patient and encounter-sensitivity checks; administrator-only configuration; patient-scoped dashboard and history. |
| Record integrity | Finalized plans are immutable; status transitions are validated server-side; content changes return to draft; stale browser submissions are rejected. |
| Revision history | Prior states are archived transactionally, with original provider attribution and the archiving actor; versions advance on content and status updates; read-only history and side-by-side comparison are available with pagination. |
| Installation | No Weno module dependency or Weno background-task side effects; consistent namespace; repeatable automatic schema upgrades and clear readiness errors. |
| Data retention | Unregistering preserves clinical records and shared OpenEMR layouts/lists. Permanent deletion remains an administrator-managed retention operation. |
| Assessment data | List codes resolve to their exact labels, including zero and multiple selections; unmapped values are explicit. |
| Interface | Status/CSRF wiring, Cancel navigation, server-side AI revision, safe editor initialization, history navigation, and browser Print/Save as PDF. |
| AI handling | Connection/response timeouts; response validation; rejection of incomplete output; response-reported token usage when available; credentials excluded from template context; bounded observation input. |
| Prompt and sections | More conservative explicit risk-label routing; clinician-review instructions; section extraction handles full headings, numbered items, Markdown, HTML, and success criteria. |
| Usage reporting | Correct provider queries, strict SQL grouping, configurable exact-model pricing, explicit unavailable costs, and unpriced-call counts. |
| Verification | Isolated regression checks, Twig rendering/escaping checks, MySQL integration tests, and automated multi-version PHP checks in GitHub Actions. |

## Configure cost estimates

No model rates are assumed. An administrator can set an array in the site's server-side configuration, using verified USD rates **per million tokens**:

```php
$GLOBALS['treatment_planner_token_pricing'] = [
    'your-exact-model-name' => [
        'prompt' => 0.0,      // Replace with the verified input rate.
        'completion' => 0.0,  // Replace with the verified output rate.
    ],
];
```

The zero values above are placeholders, not actual model prices. Leave the model unconfigured when its rates are unknown. Unpriced calls have a null estimate; groups containing them report null cost plus `unpriced_calls`. Historical estimates retain their old values and should not be treated as newly verified prices.

## Read the details

- [Version 1.0.2 edit log](EDITS-1.0.2.md)
- [Testing and staging checklist](tests/README.md)
- [Initial improvements in PR #1](https://github.com/perwriter/oe-module-treatment-planner/pull/1)
- [Displayed repository README](.github/README.md)

## Scope and remaining validation

Clinical output always needs clinician review. Keyword routing is not a clinical risk assessment. History cannot reconstruct content overwritten before tracking was installed. Comparison is read-only and does not restore or alter finalized records. PDF output uses the browser print dialog.

Automated tests use synthetic data and isolated OpenEMR adapters. Before clinical deployment, test against the actual OpenEMR version, site permissions, browser workflows, database, and external AI configuration. Do not infer full production validation from a passing automated suite.
