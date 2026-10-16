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

Unregistering the module through Module Manager runs cleanup that drops the module tables. Export any needed plan data before uninstalling.

## Contributing

Contributions are welcome. Please open an issue or pull request on GitHub.

## License

This module is licensed under the GNU General Public License, version 3 or any later version (`GPL-3.0-or-later`), matching OpenEMR. See the `LICENSE` file for details.

## Author

Sherwin Gaddis / Juggernaut Systems Express  
https://github.com/juggernautsei
