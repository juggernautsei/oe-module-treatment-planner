# Compatibility and verification

PHP version and OpenEMR version are separate compatibility dimensions.

| Environment | Verification in this update | Remaining validation |
| --- | --- | --- |
| Official OpenEMR 7.0.3 image, PHP 8.3.15 | Actual core native-session and CSRF API smoke test; actual bundled Twig rendering | Full installed-site browser workflows, installation/upgrade and real role accounts |
| Local OpenEMR 8.5.0, PHP 8.5.12 | Actual wrapper-session and CSRF API smoke test; bundled Twig rendering; additive local migration and synthetic assessment snapshot query | Browser readiness guard, one new synthetic generation, frozen evidence, source warnings and transition to Pending Review verified; approval/finalization checklist and progress browser checks remain pending because the selected chart context changed and access was refused |
| PHP 7.4, 8.3, 8.5 | Isolated regression, native/wrapper, permissions, source/profile and print/error tests in CI | Does not establish installed OpenEMR compatibility by itself |
| Real database integration | Fresh/repeated additive migrations, record preservation, review transactions, input snapshots, alternate layouts, progress idempotency and patient checks | Real multi-user role and sensitivity workflows in each supported deployment |

CI pins both official core images by digest so API/template tests are repeatable. No OpenEMR server is booted by that job; it is not an end-to-end compatibility certification. The optional tests/core-session.php uses synthetic in-memory session data and makes no database or AI calls. tests/template.php can use TP_TEST_AUTOLOAD to render with the installed core Twig runtime.

The older OpenEMR 8.5 edit/review/finalization/history tests predate these new features. Do not count them as browser validation of the new release. The new live synthetic draft saved successfully but omitted requested source citations and included an unmatched code-shaped value; the source review warnings exposed both. Source/profile and progress tests use synthetic data only. Test artifacts and credentials are not shipped.
