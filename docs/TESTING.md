# Theme verification

## Automated checks

Run from the repository root:

```sh
node dev/verify.mjs
```

The script checks gates G1–G11 and runs JavaScript syntax checks, PHP lint, and the hours and menu-description PHP suites when PHP is installed. The GitHub Actions workflow installs Node 20 and PHP 8.2 to ensure those checks execute.

For a local theme package, `node dev/build.mjs` runs verification first and writes a ZIP beside the repository. It does not upload or deploy anything.

## Scope of current unit coverage

The committed PHP test suite exercises opening hours, lunch/Sushi availability, reservation slots, and daylight-saving boundaries. It does not exercise a live WordPress installation, payment gateway, order database, email transport, or the browser checkout flow.

`php dev/tests/menu-description.test.php` covers rich editor markup converted to
plain menu descriptions, encoded markup, block spacing, entities, script/style
removal, and preservation of default rich-text and fallback behavior. Its
standalone bootstrap supplies a minimal WordPress tag-stripping shim; it is not
a full WordPress/WooCommerce integration test. Cards, modal text, search, and
menu schema consume the same description produced by the menu data builder.

## Reporting

Report the command and outcome. If PHP cannot be found, treat the PHP checks as not run rather than as passing. Distinguish automated checks from manual browser checks.
