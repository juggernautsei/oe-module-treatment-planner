# Edits in version 1.0.8

- Render the plan view using a limited, escaped Markdown renderer: headings, bold, emphasis, nested bullet and numbered lists, paragraphs and separators. Raw HTML, links and images are not enabled.
- Keep saved plan text and revision records unchanged, including finalized records. Only the display is formatted.
- Improve print typography, A4 margins, line wrapping, section-heading page breaks and list spacing. Hide interactive controls and expandable generation metadata when printing; retain patient, status, version, provider and dates.
- Add seven isolated formatting and HTML-escaping checks to the PHP matrix and Composer test script.
- Browser verification: the existing finalized synthetic plan displayed semantic headings and nested lists without Markdown markers. Full PDF export verification was interrupted because Computer Use could not reliably identify the Windows print-preview URL.
- No API keys, local configuration, database exports or patient documents are included. No schema migration required; database version remains 5. Original root README unchanged.
