# Edits in version 1.0.7

## Observed failures and diagnosis

The local OpenEMR log reference fc32f821d934 identified HTTP 404 thrown at OpenAIService.php line 195. A read-only request to the OpenAI models endpoint returned HTTP 200 with the stored key. The configured gpt-4 model was absent; gpt-4o and gpt-4o-mini were listed. The local model setting was changed to gpt-4o-mini, and one synthetic browser generation succeeded and saved draft plan 1 for synthetic patient 1 / encounter 4. No API key is included in this change.

## Changes

- Added GPT-4o and GPT-4o mini to administrator model choices. Existing installations retain their chosen model until an administrator changes it; no automatic retry or model substitution is performed.
- Mapped model-not-found, invalid-key, quota and rate-limit errors to fixed actionable messages. Arbitrary upstream response text is never exposed or logged; unexpected errors retain reference IDs.
- Removed the create page dependency on the missing CKEditor 4 asset. The local OpenEMR 8.5 installation provides CKEditor 5 instead. Notes now use the native textarea, retaining voice input and server-side AI revision controls. Rich text controls are no longer supplied on this page.
- AI revision treats output as plain text, preserves original notes on failure and avoids overwriting notes changed while a request was in flight.
- Added seven isolated API error/redaction checks to Composer and the PHP CI matrix.

## Validation and limits

- The actual browser generation redirected to treatment/view?id=1, displaying a saved draft and all six plan sections. API generation is now verified for the configured test project/model.
- The local QA assessment is a separate fixture, not a validated intake instrument or a production module migration.
- The generated draft contains model-suggested diagnostic content despite input saying no diagnosis was confirmed. It remains a synthetic draft requiring review; successful generation does not establish clinical correctness.
- Edit/save, approval, finalization, history and PDF still need live browser validation. Voice input and AI revision were not exercised in this test.
- No schema changes; database version remains 5. The original root README remains unchanged.

Official API references: https://developers.openai.com/api/docs/guides/rate-limits and https://developers.openai.com/api/docs/models/gpt-4o-mini
