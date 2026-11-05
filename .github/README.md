# OpenEMR Treatment Planner

AI-assisted treatment planning module for OpenEMR. Clinicians can generate draft treatment plans with ChatGPT, edit them in a rich text editor, associate them with encounters, and track approval status and API usage.

## Features

- **AI-generated treatment plans** using the OpenAI ChatGPT API
- **Encounter integration** so plans can be created and opened from the patient encounter context
- **Rich text editing** with CKEditor for clinical review and refinement
- **Voice-to-text dictation** via the browser Web Speech API
- **Plan workflow statuses**: draft, pending review, approved, and finalized
- **Version tracking** for treatment plan revisions
- **Configurable OpenAI settings** (API key, model, temperature)
- **Encrypted API key storage** using OpenEMR `CryptoGen`
- **Usage tracking** for tokens, duration, and estimated cost by provider
- **Twig-based UI** with list, create, edit, and view screens
- **Module setup page** for configuration and installation checks

## Requirements

- OpenEMR 7.0+
- PHP 7.4+
- Valid OpenAI API key
- Network access from the OpenEMR server to `api.openai.com`

## Installation

1. Navigate to your OpenEMR custom modules directory:

```bash
cd /var/www/html/openemr/interface/modules/custom_modules/
```

2. Clone this module:

```bash
git clone https://github.com/juggernautsei/oe-module-treatment-planner.git
```

Or extract a release archive:

```bash
unzip oe-module-treatment-planner.zip -d oe-module-treatment-planner
```

3. Install Composer autoload files if needed:

```bash
cd oe-module-treatment-planner
composer install
```

4. Enable the module in OpenEMR:

- Go to **Administration → Modules**
- Open the **Unregistered** tab
- Find **Treatment Planner** and click **Register**
- Open the **Registered** tab and enable the module

On install, `table.sql` creates:

- `module_treatment_planner_config`
- `module_treatment_planner_plans`
- `module_treatment_planner_templates`
- `module_treatment_planner_usage`

## Configuration

1. Open the module setup / configuration page from Modules → Treatment Planner.
2. Enter your OpenAI API key.
3. Choose the model (default: `gpt-4`) and temperature (default: `0.7`).
4. Save settings.

The API key is stored encrypted in `module_treatment_planner_config`.

## Usage

1. Open a patient chart and encounter (or use the Treatment Planner menu entry).
2. Create a new treatment plan.
3. Generate an AI draft, dictate notes, and edit the content.
4. Move the plan through review statuses as needed (`draft` → `pending_review` → `approved` → `finalized`).
5. Review prior plans from the list / dashboard views.

## Security Considerations

- OpenAI API keys are encrypted at rest with OpenEMR cryptography helpers
- Access is gated by module ACL / access checks in the public entry point
- CSRF protection should be used on module forms consistent with OpenEMR patterns
- Do not send unnecessary identifiable patient data to external AI services; review prompts and payloads for your compliance requirements

## Troubleshooting

1. Check OpenEMR and PHP error logs for module exceptions.
2. Confirm the OpenAI API key is valid and has model access.
3. Verify outbound HTTPS from the server to OpenAI is allowed.
4. Confirm the four `module_treatment_planner_*` tables exist after registration.
5. Re-run `composer install` if class autoloading fails.

## Uninstall

Unregistering preserves plan records, revision history, settings, and shared OpenEMR layouts. Permanent deletion must be performed separately by an administrator under the organization's retention policy.

## Contributing

Contributions are welcome. Please open an issue or pull request on GitHub.

## License

This module is licensed under the GNU General Public License, version 3 or any later version (`GPL-3.0-or-later`), matching OpenEMR. See the `LICENSE` file for details.

## Author

Sherwin Gaddis / Juggernaut Systems Express  
https://github.com/juggernautsei


## Upgrading to 1.0.1

Back up the database, then run `sql/upgrade-1.0.1.sql` against the selected OpenEMR site's database before deploying these files. The migration is additive and can be rerun. Existing overwritten content cannot be recovered; snapshots begin with the first update after upgrade. Do not deploy without the revision table.

Medical-record ACL access and a selected patient chart are required. Edits require full `patients/med` write permission; approval and finalization also require `patients/sign` write permission. Encounter sensitivity is checked. Status transitions are draft → pending review → approved → finalized, with returns to draft/review. Finalized plans are immutable. Changed content must be saved as draft and reviewed again.

Every update archives the previous state and its author, records the updating user, and increments the version. Stale browser submissions are rejected. Revision history is retained in the database; a history comparison interface is not yet included. PDF export uses the browser print dialog's Save as PDF option.

AI drafts and revised observations require clinician verification. Risk prompt routing only recognizes explicit high/immediate assessment labels and does not determine clinical risk. Free-text assessments can contain identifying data: review the information sent to the external AI service under your organization's policies.


## Verification

Run `php tests/regression.php` for isolated authorization, workflow, revision failure, response validation, and risk-routing checks. These use OpenEMR test doubles and do not replace testing against a running OpenEMR site. Before deployment, apply the migration on a test database, verify ACL roles and encounter sensitivity, exercise the review workflow in two browser tabs, and verify AI generation, revision, and print/PDF behavior. Dashboard counts are scoped to the selected patient's accessible encounters.

---

# Contributions added in the perwriter fork

These improvements were added in [pull request #1](https://github.com/perwriter/oe-module-treatment-planner/pull/1) and merged into this fork's `main` branch. The original project and its authors retain their existing attribution and license.

## 1. Patient access and configuration permissions

- Require medical-record ACL access and the selected patient chart when accessing treatment plans.
- Check that encounters belong to the patient and respect encounter sensitivity permissions.
- Require full medical-record write permission for edits, plus signing permission for approval and finalization.
- Restrict the setup page to users with module-management permission.
- Scope dashboard information to accessible encounters in the selected patient chart.
- Bind generation and revision forms to their original patient to protect against accidental chart changes across browser tabs.

## 2. Protected treatment-plan workflow

- Validate status transitions on the server.
- Prevent edits to finalized plans, including direct requests.
- Require changed content to return to draft for review.
- Reject stale submissions when another update has changed the plan version.
- Hide editing actions for finalized plans.

## 3. Revision history and attribution

- Archive the previous content, sections, status, version, and original provider before an update.
- Record the updating user and archive timestamp.
- Save revisions and updates in one transaction so an archive failure prevents the update.
- Increment the version for content and status changes.
- Re-extract sections after content edits instead of deliberately clearing them.

Revision history is stored in the database. A revision browsing or comparison screen is not included yet. Previously overwritten content cannot be recovered.

## 4. Safer module removal

Unregistering now preserves clinical records, configuration, usage data, and shared OpenEMR mental-health layouts and lists. Permanent deletion is a separate administrator-managed operation under the organization's retention policy.

## 5. Repaired interface actions

- Connect status changes to the registered controller route and supply its CSRF token.
- Add a server-side endpoint for “Revise with AI,” using configured credentials and model settings.
- Correct the edit screen's Cancel link.
- Replace the missing PDF endpoint with browser printing and **Save as PDF**.
- Avoid initializing the observation editor when its textarea is absent.

## 6. More reliable AI requests

- Add explicit connection and response timeouts.
- Reject invalid JSON, empty content, and incomplete or blocked responses.
- Use response-reported token counts when provided, with estimates as the generation fallback.
- Remove the decrypted API key from the create-page template context.
- Limit observation input length and request fact-preserving, clinician-reviewed text revision.

## 7. More conservative risk prompt routing

Broad keyword matching was replaced with explicit high/immediate assessment-label matching for risk prompt routing. This reduces accidental matches from unrelated or negated narrative text. Routing is not a clinical risk assessment; all generated plans still require clinician verification.

## Verification completed

- **28 PHP files** passed syntax checks with PHP 8.5.12.
- **20 isolated regression checks** passed for access controls, finalized-record protection, stale updates, reviewer permissions, revision attribution and rollback, incomplete AI responses, and negated risk descriptions.
- Git whitespace checks passed.

These checks use OpenEMR test doubles. Full testing in a running OpenEMR site, a real database, browser workflows, and live OpenAI integration remains necessary before deployment.

## Upgrade before deployment

1. Back up the selected OpenEMR site's database.
2. Apply [the additive 1.0.1 migration](../sql/upgrade-1.0.1.sql) before deploying the updated module files.
3. Verify ACL roles, encounter sensitivity, the review workflow in two browser tabs, AI generation and revision, and printing on a staging site.

The revision table is required for plan updates after this upgrade. The migration can be rerun; history snapshots begin with the first update after the upgrade.

## Documentation and source

- [Existing project README](../README.md) — retained without modification by this documentation addition.
- [Regression checks](../tests/regression.php)
- [Merged contribution PR](https://github.com/perwriter/oe-module-treatment-planner/pull/1)
- [Original upstream project](https://github.com/juggernautsei/oe-module-treatment-planner)

This additional `.github/README.md` displays a snapshot of the existing project README above, followed by the fork's contribution notes. GitHub gives this file priority in the repository README panel; the root README remains a separate file.


---

## Latest improvements: version 1.0.2

See [the consolidated improvements overview](../IMPROVEMENTS.md), [the detailed edit log](../EDITS-1.0.2.md), and [testing instructions](../tests/README.md).

The new release removes the Weno dependency, automates schema upgrades, resolves assessment labels, repairs usage reporting, improves section extraction, and adds revision history browsing and side-by-side comparison. Earlier limitations described above about manual migration and the absence of a history screen are superseded by version 1.0.2. The root README remains unchanged.


## Latest corrections: version 1.0.3

Read [the detailed edit log](../EDITS-1.0.3.md) and [the consolidated improvements](../IMPROVEMENTS.md). This release completes assessment prompt data, secures template initialization, prevents duplicate generation requests, and repairs setup navigation and validation. Upgrade the database through Module Manager before use.
