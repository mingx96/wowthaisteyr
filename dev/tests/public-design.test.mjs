import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const templates = ['front-page.php', 'page-menu.php', 'page-reservations.php', 'footer.php'];
for (const file of templates) {
  const source = read(file);
  assert.doesNotMatch(source, /[↗→]/u, `${file} must use the shared SVG action icon, not a Unicode arrow`);
}

const reservations = read('page-reservations.php');
assert.ok(
  reservations.indexOf('class="reservation-panel"') < reservations.indexOf('class="reservation-aside"'),
  'the reservation form panel must come before the information aside in source order',
);

const fonts = read('assets/css/fonts.css');
assert.match(fonts, /font-family:\s*['"]Playfair Display['"]/u, 'Playfair Display must be declared locally');
assert.doesNotMatch(fonts, /Chonburi/u, 'Chonburi must not remain in the active font stylesheet');
for (const file of [
  'nuFkD-vYSZviVYUb_rj3ij__anPXDTnogkk7.woff2',
  'nuFkD-vYSZviVYUb_rj3ij__anPXDTnojEk72xU.woff2',
  'nuFiD-vYSZviVYUb_rj3ij__anPXDTzYgA.woff2',
  'nuFiD-vYSZviVYUb_rj3ij__anPXDTLYgFE_.woff2',
]) {
  const full = path.join(root, 'assets/fonts', file);
  assert.ok(fs.existsSync(full) && fs.statSync(full).size > 1000, `${file} must be a non-empty self-hosted font`);
}

for (const file of ['assets/css/styles.css', 'assets/css/checkout.css', 'assets/css/portal.css']) {
  assert.match(read(file), /Playfair Display/u, `${file} must use the restored display font`);
  assert.doesNotMatch(read(file), /Chonburi/u, `${file} must not reference the rejected display font`);
}

assert.match(read('functions.php'), /\$version\s*=\s*['"]1\.5\.6['"]/u, 'theme asset version must be 1.5.6');
assert.match(read('style.css'), /Version:\s*1\.5\.6/u, 'theme header version must be 1.5.6');

console.log('PASS public design static contract: local Playfair/DM Sans system, shared SVG arrows, form-first source, version 1.5.6');
