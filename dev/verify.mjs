#!/usr/bin/env node
/**
 * WOW Thai Steyr — Automated Quality & Consistency Gate (G1–G10)
 * Usage:
 *   node dev/verify.mjs [--baseline] [--fix-eol]
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');

const args = new Set(process.argv.slice(2));
const isBaseline = args.has('--baseline');
const fixEol = args.has('--fix-eol');

function walkFiles(dir, relBase = '') {
    const out = [];
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const rel = relBase ? `${relBase}/${entry.name}` : entry.name;
        if (entry.isDirectory()) {
            if (['.git', 'node_modules', 'dev'].includes(entry.name)) continue;
            out.push(...walkFiles(path.join(dir, entry.name), rel));
        } else {
            out.push(rel);
        }
    }
    return out;
}

const allFiles = walkFiles(ROOT);
const phpFiles = allFiles.filter((f) => f.endsWith('.php'));
const jsFiles = allFiles.filter((f) => f.endsWith('.js'));
const cssFiles = allFiles.filter((f) => f.endsWith('.css'));
const codeFiles = allFiles.filter((f) => /\.(php|js|css|json)$/i.test(f));

const results = [];
function recordGate(id, name, issues, warnOnly = false) {
    const status = issues.length === 0 ? 'PASS' : (warnOnly ? 'WARN' : 'FAIL');
    results.push({ id, name, status, issues });
}

// G1: Opening hours literals only in inc/hours.php
{
    const issues = [];
    const hourRegex = /\b(11:00|11:30|14:30|17:00|21:00|19:00)\b/g;
    for (const rel of [...phpFiles, ...jsFiles]) {
        if (rel === 'inc/hours.php' || rel === 'inc/menu-normalizer.php') continue;
        const content = fs.readFileSync(path.join(ROOT, rel), 'utf8');
        const lines = content.split('\n');
        lines.forEach((line, idx) => {
            if (line.includes('// hours-ok')) return;
            const matches = line.match(hourRegex);
            if (matches) {
                issues.push(`${rel}:${idx + 1} contains hardcoded hour(s): ${matches.join(', ')}`);
            }
        });
    }
    recordGate('G1', 'Single Source of Truth for Opening Hours (inc/hours.php)', issues, isBaseline);
}

// G2: No external Google Fonts
{
    const issues = [];
    const fontRegex = /fonts\.(googleapis|gstatic)\.com/i;
    for (const rel of [...phpFiles, ...cssFiles, ...jsFiles]) {
        const content = fs.readFileSync(path.join(ROOT, rel), 'utf8');
        const lines = content.split('\n');
        lines.forEach((line, idx) => {
            if (fontRegex.test(line)) {
                issues.push(`${rel}:${idx + 1} references external Google Fonts`);
            }
        });
    }
    recordGate('G2', 'Zero External Google Fonts (DSGVO Compliance)', issues, isBaseline);
}

// G3: Zero UTF-8 Mojibake
{
    const issues = [];
    const mojibakeRegex = /Ã[\x80-\xBF]|Â[\x80-\xBF]|ðŸ|â€[\x80-\xBF]/;
    for (const rel of codeFiles) {
        const content = fs.readFileSync(path.join(ROOT, rel), 'utf8');
        const lines = content.split('\n');
        lines.forEach((line, idx) => {
            if (mojibakeRegex.test(line)) {
                issues.push(`${rel}:${idx + 1} suspected mojibake sequence`);
            }
        });
    }
    recordGate('G3', 'Zero UTF-8 Mojibake', issues, false);
}

// G4: UTF-8 without BOM & LF line endings
{
    const issues = [];
    for (const rel of codeFiles) {
        const fullPath = path.join(ROOT, rel);
        const buf = fs.readFileSync(fullPath);
        const hasBom = buf.length >= 3 && buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf;
        let text = buf.toString('utf8');
        if (hasBom) {
            text = text.slice(1);
        }
        const hasCrlf = text.includes('\r\n');
        if ((hasBom || hasCrlf) && fixEol) {
            fs.writeFileSync(fullPath, text.replace(/\r\n/g, '\n'), 'utf8');
        } else {
            if (hasBom) issues.push(`${rel} has UTF-8 BOM`);
            if (hasCrlf) issues.push(`${rel} contains CRLF line endings`);
        }
    }
    recordGate('G4', 'UTF-8 no BOM & LF Line Endings', issues, isBaseline);
}

// G5: JS & PHP Syntax Check + Hours Unit Tests
{
    const issues = [];
    for (const rel of jsFiles) {
        try {
            execFileSync(process.execPath, ['--check', path.join(ROOT, rel)], { stdio: 'pipe' });
        } catch (err) {
            issues.push(`JS syntax error in ${rel}: ${err.message}`);
        }
    }
    try {
        execFileSync(process.execPath, [path.join(ROOT, 'dev', 'tests', 'public-design.test.mjs')], { stdio: 'pipe' });
    } catch (err) {
        issues.push(`Public design static contract failure: ${err.message}`);
    }
    const localPhp = 'C:\\Users\\mnguy\\AppData\\Roaming\\Local\\lightning-services\\php-8.2.29+0\\bin\\win64\\php.exe';
    const phpBin = fs.existsSync(localPhp) ? localPhp : 'php';
    let phpAvailable = false;
    try {
        execFileSync(phpBin, ['-v'], { stdio: 'pipe' });
        phpAvailable = true;
    } catch {
        phpAvailable = false;
    }
    if (phpAvailable) {
        for (const rel of phpFiles) {
            try {
                execFileSync(phpBin, ['-l', path.join(ROOT, rel)], { stdio: 'pipe' });
            } catch (err) {
                issues.push(`PHP syntax error in ${rel}: ${err.message}`);
            }
        }
        for (const testName of ['hours.test.php', 'hours-schedule.test.php', 'hours-api.test.php', 'menu-description.test.php', 'portal-read-model.test.php']) {
            const testFile = path.join(ROOT, 'dev', 'tests', testName);
            if (!fs.existsSync(testFile)) continue;
            try {
                execFileSync(phpBin, [testFile], { stdio: 'pipe' });
            } catch (err) {
                issues.push(`PHP unit test failure in dev/tests/${testName}: ${err.message}`);
            }
        }
    }
    recordGate('G5', 'JS & PHP Syntax + Hours, Menu and Portal Unit Suites', issues, false);
}

// G6: Version Synchronization between style.css and functions.php
{
    const issues = [];
    const styleCss = fs.readFileSync(path.join(ROOT, 'style.css'), 'utf8');
    const funcPhp = fs.readFileSync(path.join(ROOT, 'functions.php'), 'utf8');
    const styleVer = (styleCss.match(/Version:\s*([0-9.]+)/i) || [])[1];
    const funcVer = (funcPhp.match(/\$version\s*=\s*['"]([0-9.]+)['"]/) || [])[1];
    if (!styleVer || !funcVer || styleVer !== funcVer) {
        issues.push(`Version mismatch: style.css (${styleVer}) !== functions.php (${funcVer})`);
    }
    recordGate('G6', 'Theme Version Synchronization', issues, false);
}

// G7: Superglobal Sanitization Heuristic
{
    const issues = [];
    const sgRegex = /\$_(POST|GET|REQUEST)\s*\[/;
    const lhsAssignRegex = /^\s*\$_(POST|GET|REQUEST)\s*\[[^\]]+\]\s*=\s*['"][^'"]*['"]\s*;/;
    const safeRegex = /(sanitize_|absint|intval|floatval|wp_unslash|esc_|isset\s*\(|empty\s*\(|\(int\)|\(float\)|\(string\)|\/\/\s*verify-ok)/;
    for (const rel of phpFiles) {
        const lines = fs.readFileSync(path.join(ROOT, rel), 'utf8').split('\n');
        lines.forEach((line, idx) => {
            if (lhsAssignRegex.test(line)) return;
            if (sgRegex.test(line) && !safeRegex.test(line)) {
                issues.push(`${rel}:${idx + 1} unsanitized superglobal access: ${line.trim()}`);
            }
        });
    }
    recordGate('G7', 'Superglobal Input Sanitization Heuristic', issues, isBaseline);
}

// G8: No Raw UTC date() / strtotime() / time()
{
    const issues = [];
    const rawTimeRegex = /(^|[^a-zA-Z0-9_$>])(date|strtotime|time)\s*\(/;
    for (const rel of phpFiles) {
        const lines = fs.readFileSync(path.join(ROOT, rel), 'utf8').split('\n');
        lines.forEach((line, idx) => {
            const trimmed = line.trim();
            if (trimmed.startsWith('//') || trimmed.startsWith('*') || line.includes('// verify-ok')) return;
            if (rawTimeRegex.test(line)) {
                issues.push(`${rel}:${idx + 1} uses raw UTC time function: ${trimmed}`);
            }
        });
    }
    recordGate('G8', 'Timezone-Safe Date/Time Functions (Europe/Vienna)', issues, isBaseline);
}

// G9: No Wrong Phone Number (45866) or Nonexistent Pickup Meta Keys
{
    const issues = [];
    for (const rel of phpFiles) {
        const lines = fs.readFileSync(path.join(ROOT, rel), 'utf8').split('\n');
        lines.forEach((line, idx) => {
            if (line.includes('45866')) {
                issues.push(`${rel}:${idx + 1} contains wrong phone number 45866`);
            }
            if ((line.includes('_wowthai_pickup_time') || line.includes('_wowthai_pickup_date')) && !line.includes('// legacy-fallback')) {
                issues.push(`${rel}:${idx + 1} reads nonexistent _wowthai_pickup_time/_date without legacy-fallback`);
            }
        });
    }
    recordGate('G9', 'Phone Number & Pickup Meta Key Integrity', issues, isBaseline);
}

// G10: Kitchen 80mm Bon & Admin Order Column Customization Rendering
{
    const issues = [];
    const adminOrders = fs.readFileSync(path.join(ROOT, 'inc/admin-orders.php'), 'utf8');
    if (!adminOrders.includes('wowthai_get_order_item_customizations')) {
        issues.push('inc/admin-orders.php is missing wowthai_get_order_item_customizations() helper');
    }
    if (!adminOrders.includes('class="item-variant"')) {
        issues.push('inc/admin-orders.php 80mm Bon does not render <div class="item-variant">');
    }
    recordGate('G10', 'Kitchen 80mm Bon & Admin Order Customizations', issues, isBaseline);
}

// G11: SEO Architecture & Metadata Integrity
{
    const issues = [];
    const seoFile = path.join(ROOT, 'inc/seo.php');
    if (!fs.existsSync(seoFile)) {
        issues.push('inc/seo.php does not exist');
    } else {
        const seoContent = fs.readFileSync(seoFile, 'utf8');
        if (!seoContent.includes('wowthai_get_seo_meta')) {
            issues.push('inc/seo.php is missing wowthai_get_seo_meta()');
        }
        if (!seoContent.includes('pre_get_document_title')) {
            issues.push('inc/seo.php is missing pre_get_document_title filter');
        }
        if (!seoContent.includes('wp_sitemaps_post_types')) {
            issues.push('inc/seo.php is missing wp_sitemaps_post_types filter');
        }
        if (!seoContent.includes('wp_sitemaps_add_provider')) {
            issues.push('inc/seo.php is missing wp_sitemaps_add_provider filter to protect users sitemap');
        }
    }
    const resInfo = fs.readFileSync(path.join(ROOT, 'inc/restaurant-info.php'), 'utf8');
    if (!resInfo.includes('hasMap')) {
        issues.push('inc/restaurant-info.php is missing hasMap schema');
    }
    if (!resInfo.includes('wowthai_output_menu_schema')) {
        issues.push('inc/restaurant-info.php is missing wowthai_output_menu_schema()');
    }
    const frontPage = fs.readFileSync(path.join(ROOT, 'front-page.php'), 'utf8');
    if (!frontPage.includes('hero-seo-badge')) {
        issues.push('front-page.php is missing hero-seo-badge in H1 heading');
    }
    if (frontPage.includes('alt=""')) {
        issues.push('front-page.php has empty hero alt attribute');
    }
    const funcPhp = fs.readFileSync(path.join(ROOT, 'functions.php'), 'utf8');
    if (!funcPhp.includes("require_once get_template_directory() . '/inc/seo.php'")) {
        issues.push('functions.php does not require inc/seo.php');
    }
    recordGate('G11', 'SEO Engine & Structured Data Integrity', issues, isBaseline);
}

// Summary Output
let failedCount = 0;
console.log('==============================================================');
console.log(' WOW THAI STEYR — AUTOMATED VERIFICATION REPORT (G1–G11)');
console.log('==============================================================');
for (const r of results) {
    const icon = r.status === 'PASS' ? '✅' : (r.status === 'WARN' ? '⚠️' : '❌');
    console.log(`${icon} [${r.id}] ${r.name}: ${r.status} (${r.issues.length} issue(s))`);
    for (const issue of r.issues.slice(0, 15)) {
        console.log(`     - ${issue}`);
    }
    if (r.issues.length > 15) {
        console.log(`     ... and ${r.issues.length - 15} more`);
    }
    if (r.status === 'FAIL') failedCount++;
}
console.log('==============================================================');
if (failedCount > 0) {
    console.error(`Verification FAILED with ${failedCount} failing gate(s).`);
    process.exit(1);
} else {
    console.log('Verification PASSED.');
}

