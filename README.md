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
