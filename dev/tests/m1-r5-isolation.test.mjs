/**
 * M1-R5-ISOLATION TEST SUITE
 * Adversarial empirical verification for Milestone 1 (M1)
 *
 * Tests:
 * 1. Studio configuration & zero remote connections in shared.json / cli.json
 * 2. Database & options isolation: siteurl, home, no production endpoints, no external webhooks
 * 3. Filesystem isolation: junctions, independent database/uploads, wp-config
 * 4. Static scan: zero external fonts, CDNs, trackers, or outbound production push calls
 * 5. Live HTTP inspection: ports 8881-8884 status 200, zero external asset links
 */

import fs from 'node:fs';
import path from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import http from 'node:http';

const PROD_ENDPOINTS = [
  'wow-thai-steyr.at',
  'wow-thai-valentin.at',
  'wowthai-saalbach.at',
  'wow-express.at',
];

const STUDIO_DIR = 'C:\\Users\\mnguy\\Studio';
const STUDIO_CONFIG_DIR = 'C:\\Users\\mnguy\\.studio';
const CANONICAL_REPO = 'c:\\Users\\mnguy\\Local Sites\\wow-thai-steyr\\app\\public';
const THEME_DIR = path.join(CANONICAL_REPO, 'wp-content', 'themes', 'wowthai');

const SITES = [
  { id: 'valentin', name: 'wow-thai-valentin', port: 8882 },
  { id: 'saalbach', name: 'wow-thai-saalbach', port: 8883 },
  { id: 'express', name: 'wow-express', port: 8884 },
];

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;
const failures = [];

function assert(condition, testName, details = '') {
  totalTests++;
  if (condition) {
    passedTests++;
    console.log(`  [PASS] ${testName}`);
  } else {
    failedTests++;
    console.error(`  [FAIL] ${testName} - ${details}`);
    failures.push({ testName, details });
  }
}

async function fetchHttp(url) {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        resolve({ statusCode: res.statusCode, headers: res.headers, body: data });
      });
    }).on('error', (err) => {
      reject(err);
    });
  });
}

function walkDir(dir, filterRegex) {
  let results = [];
  const list = fs.readdirSync(dir, { withFileTypes: true });
  for (const item of list) {
    if (['.git', 'node_modules', 'vendor'].includes(item.name)) continue;
    const fullPath = path.join(dir, item.name);
    if (item.isDirectory()) {
      results = results.concat(walkDir(fullPath, filterRegex));
    } else if (filterRegex.test(item.name)) {
      results.push(fullPath);
    }
  }
  return results;
}

console.log('===============================================================');
console.log(' EMPIRICAL CHALLENGE: M1 R5 ZERO PRODUCTION PUSH & ISOLATION   ');
console.log('===============================================================\n');

// -------------------------------------------------------------
// SECTION 1: STUDIO CONFIGURATION ISOLATION
// -------------------------------------------------------------
console.log('--- 1. Studio Configuration & Remote Connections Check ---');
const sharedJsonPath = path.join(STUDIO_CONFIG_DIR, 'shared.json');
const cliJsonPath = path.join(STUDIO_CONFIG_DIR, 'cli.json');

assert(fs.existsSync(sharedJsonPath), 'shared.json exists at C:\\Users\\mnguy\\.studio\\shared.json');
assert(fs.existsSync(cliJsonPath), 'cli.json exists at C:\\Users\\mnguy\\.studio\\cli.json');

const sharedConfig = JSON.parse(fs.readFileSync(sharedJsonPath, 'utf8'));
const cliConfig = JSON.parse(fs.readFileSync(cliJsonPath, 'utf8'));

// Verify no remote connections for new sites
const connectedSitesMap = sharedConfig.connectedWpcomSites || {};
const allConnectedRemotes = Object.values(connectedSitesMap).flat();

for (const site of SITES) {
  const match = allConnectedRemotes.find(
    (rem) => rem.name?.toLowerCase().includes(site.name) || rem.url?.toLowerCase().includes(site.name)
  );
  assert(
    !match,
    `No remote WPCOM connection for ${site.name} in shared.json`,
    match ? JSON.stringify(match) : ''
  );
}

// Verify production endpoints not registered in any remote connection
for (const endpoint of ['wow-thai-valentin.at', 'wowthai-saalbach.at', 'wow-express.at']) {
  const match = allConnectedRemotes.find((rem) => rem.url && rem.url.includes(endpoint));
  assert(
    !match,
    `Production endpoint ${endpoint} is NOT registered as a remote sync target`,
    match ? JSON.stringify(match) : ''
  );
}

// Check ports in cli.json
const registeredSites = cliConfig.sites || [];
for (const site of SITES) {
  const reg = registeredSites.find((s) => s.name === site.name);
  assert(
    reg !== undefined,
    `${site.name} registered in cli.json`,
    `Not found in cli.json`
  );
  if (reg) {
    assert(
      reg.port === site.port,
      `${site.name} assigned dedicated port ${site.port}`,
      `Assigned: ${reg.port}`
    );
    assert(
      fs.existsSync(reg.path),
      `${site.name} root directory exists at ${reg.path}`,
      `Path missing: ${reg.path}`
    );
  }
}

// -------------------------------------------------------------
// SECTION 2: SQLITE DATABASE & OPTIONS ISOLATION
// -------------------------------------------------------------
console.log('\n--- 2. SQLite Database & Database Options Check ---');
for (const site of SITES) {
  const dbPath = path.join(STUDIO_DIR, site.name, 'wp-content', 'database', '.ht.sqlite');
  assert(fs.existsSync(dbPath), `${site.name} SQLite database exists at ${dbPath}`);

  if (fs.existsSync(dbPath)) {
    const db = new DatabaseSync(dbPath);
    
    // Check siteurl and home
    const siteurlRow = db.prepare("SELECT option_value FROM wp_options WHERE option_name = 'siteurl'").get();
    const homeRow = db.prepare("SELECT option_value FROM wp_options WHERE option_name = 'home'").get();

    const expectedUrl = `http://localhost:${site.port}`;
    assert(
      siteurlRow && siteurlRow.option_value === expectedUrl,
      `${site.name} siteurl option is ${expectedUrl}`,
      `Actual: ${siteurlRow?.option_value}`
    );
    assert(
      homeRow && homeRow.option_value === expectedUrl,
      `${site.name} home option is ${expectedUrl}`,
      `Actual: ${homeRow?.option_value}`
    );

    // Deep search in wp_options for production endpoints
    const allOptions = db.prepare("SELECT option_name, option_value FROM wp_options").all();
    const leakingOptions = [];
    for (const opt of allOptions) {
      if (typeof opt.option_value === 'string') {
        for (const ep of PROD_ENDPOINTS) {
          if (opt.option_value.includes(ep)) {
            leakingOptions.push({ name: opt.option_name, endpoint: ep, snippet: opt.option_value.slice(0, 100) });
          }
        }
      }
    }
    assert(
      leakingOptions.length === 0,
      `${site.name} wp_options has 0 references to production endpoints`,
      `Found: ${JSON.stringify(leakingOptions)}`
    );

    // Check WooCommerce Webhooks table
    const tableExists = db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='wp_wc_webhooks'").get();
    if (tableExists) {
      const webhooks = db.prepare("SELECT * FROM wp_wc_webhooks").all();
      assert(
        webhooks.length === 0,
        `${site.name} WooCommerce webhooks count is 0`,
        `Found ${webhooks.length} webhooks: ${JSON.stringify(webhooks)}`
      );
    } else {
      assert(true, `${site.name} wp_wc_webhooks table empty or not yet created`);
    }

    db.close();
  }
}

// -------------------------------------------------------------
// SECTION 3: DRY JUNCTIONS & INFRASTRUCTURE ISOLATION
// -------------------------------------------------------------
console.log('\n--- 3. DRY Junctions & Directory Isolation Check ---');
const canonicalTheme = path.join(CANONICAL_REPO, 'wp-content', 'themes', 'wowthai');
const canonicalPlugin = path.join(CANONICAL_REPO, 'wp-content', 'plugins', 'wowthai-operations');

for (const site of SITES) {
  const siteDir = path.join(STUDIO_DIR, site.name);
  const themeJunction = path.join(siteDir, 'wp-content', 'themes', 'wowthai');
  const pluginJunction = path.join(siteDir, 'wp-content', 'plugins', 'wowthai-operations');

  // Verify theme exists and has junction or link
  assert(fs.existsSync(themeJunction), `${site.name} theme directory exists`);
  const themeStat = fs.lstatSync(themeJunction);
  assert(
    themeStat.isSymbolicLink() || themeStat.isDirectory(),
    `${site.name} theme wowthai is mounted/junctioned`
  );

  // Verify plugin exists
  assert(fs.existsSync(pluginJunction), `${site.name} plugin wowthai-operations exists`);
  const pluginStat = fs.lstatSync(pluginJunction);
  assert(
    pluginStat.isSymbolicLink() || pluginStat.isDirectory(),
    `${site.name} plugin wowthai-operations is mounted/junctioned`
  );

  // Verify database directory is LOCAL and not junctioned
  const dbDir = path.join(siteDir, 'wp-content', 'database');
  const dbStat = fs.lstatSync(dbDir);
  assert(
    !dbStat.isSymbolicLink(),
    `${site.name} database directory is independent (not junctioned)`
  );

  // Verify wp-config.php exists and is local
  const wpConfigPath = path.join(siteDir, 'wp-config.php');
  assert(fs.existsSync(wpConfigPath), `${site.name} has independent wp-config.php`);
  const wpConfigStat = fs.lstatSync(wpConfigPath);
  assert(!wpConfigStat.isSymbolicLink(), `${site.name} wp-config.php is a real file`);
}

// -------------------------------------------------------------
// SECTION 4: SOURCE CODE STATIC SCAN (ZERO EXTERNAL ASSETS / CALLS)
// -------------------------------------------------------------
console.log('\n--- 4. Source Code Static Scan (External Fonts, CDNs, Network Calls) ---');
const themeCodeFiles = walkDir(THEME_DIR, /\.(php|js|css)$/);

const externalFontRegex = /fonts\.(googleapis|gstatic)\.com|typekit\.net|use\.typekit\.net/i;
const externalCdnRegex = /cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|unpkg\.com|ajax\.googleapis\.com/i;
const externalAnalyticsRegex = /google-analytics\.com|googletagmanager\.com|connect\.facebook\.net/i;
const outboundRemoteRegex = /\b(wp_remote_get|wp_remote_post|wp_safe_remote_get|wp_safe_remote_post|curl_exec)\b/i;

const fontViolations = [];
const cdnViolations = [];
const analyticsViolations = [];
const outboundCalls = [];

for (const filePath of themeCodeFiles) {
  const content = fs.readFileSync(filePath, 'utf8');
  const relPath = path.relative(THEME_DIR, filePath);
  const lines = content.split('\n');

  lines.forEach((line, idx) => {
    const trimmed = line.trim();
    if (trimmed.startsWith('//') || trimmed.startsWith('*')) return;

    if (externalFontRegex.test(line)) {
      fontViolations.push(`${relPath}:${idx + 1}: ${trimmed}`);
    }
    if (externalCdnRegex.test(line)) {
      cdnViolations.push(`${relPath}:${idx + 1}: ${trimmed}`);
    }
    if (externalAnalyticsRegex.test(line)) {
      analyticsViolations.push(`${relPath}:${idx + 1}: ${trimmed}`);
    }
    if (outboundRemoteRegex.test(line)) {
      outboundCalls.push(`${relPath}:${idx + 1}: ${trimmed}`);
    }
  });
}

assert(
  fontViolations.length === 0,
  'Zero external Google Fonts / Typekit references in theme codebase',
  JSON.stringify(fontViolations)
);
assert(
  cdnViolations.length === 0,
  'Zero external CDN script / stylesheet references in theme codebase',
  JSON.stringify(cdnViolations)
);
assert(
  analyticsViolations.length === 0,
  'Zero external analytics / tracking scripts in theme codebase',
  JSON.stringify(analyticsViolations)
);

// Examine any outbound calls to verify they do not push to production
let dangerousOutboundCalls = [];
for (const call of outboundCalls) {
  for (const ep of PROD_ENDPOINTS) {
    if (call.includes(ep)) {
      dangerousOutboundCalls.push(call);
    }
  }
}
assert(
  dangerousOutboundCalls.length === 0,
  'Zero outbound HTTP calls targeting production endpoints in theme codebase',
  JSON.stringify(dangerousOutboundCalls)
);

// -------------------------------------------------------------
// SECTION 5: LIVE HTTP ENDPOINT INTEGRITY & ASSET INSPECTION
// -------------------------------------------------------------
console.log('\n--- 5. Live HTTP Responses & Rendered HTML Asset Inspection ---');
for (const site of SITES) {
  try {
    const url = `http://localhost:${site.port}`;
    const res = await fetchHttp(url);

    assert(
      res.statusCode === 200,
      `${site.name} (${url}) returns HTTP 200 OK`,
      `Returned status ${res.statusCode}`
    );

    const body = res.body;

    // Check rendered stylesheets
    const stylesheetMatches = [...body.matchAll(/<link[^>]+rel=["']stylesheet["'][^>]*href=["']([^"']+)["']/gi)];
    const externalStyles = stylesheetMatches
      .map((m) => m[1])
      .filter((href) => href.startsWith('http://') || href.startsWith('https://'))
      .filter((href) => !href.includes(`localhost:${site.port}`));

    assert(
      externalStyles.length === 0,
      `${site.name} rendered HTML loads 0 external stylesheets`,
      `Found external styles: ${JSON.stringify(externalStyles)}`
    );

    // Check rendered scripts
    const scriptMatches = [...body.matchAll(/<script[^>]+src=["']([^"']+)["']/gi)];
    const externalScripts = scriptMatches
      .map((m) => m[1])
      .filter((src) => src.startsWith('http://') || src.startsWith('https://'))
      .filter((src) => !src.includes(`localhost:${site.port}`));

    assert(
      externalScripts.length === 0,
      `${site.name} rendered HTML loads 0 external scripts`,
      `Found external scripts: ${JSON.stringify(externalScripts)}`
    );

    // Check for production domain leaks in rendered page
    const leakingUrlsInHtml = [];
    for (const ep of PROD_ENDPOINTS) {
      if (body.includes(ep)) {
        leakingUrlsInHtml.push(ep);
      }
    }
    assert(
      leakingUrlsInHtml.length === 0,
      `${site.name} rendered HTML contains 0 production domain strings`,
      `Found leaking domains: ${JSON.stringify(leakingUrlsInHtml)}`
    );

  } catch (err) {
    assert(false, `${site.name} HTTP request succeeded`, err.message);
  }
}

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
console.log('\n===============================================================');
console.log(` SUMMARY: ${passedTests}/${totalTests} tests passed (${failedTests} failed)`);
console.log('===============================================================');

if (failedTests > 0) {
  console.error(`\nCHALLENGE VERDICT: REQUEST_CHANGES (${failedTests} failures)`);
  process.exit(1);
} else {
  console.log('\nCHALLENGE VERDICT: APPROVE (All R5 isolation & guardrail checks passed)');
  process.exit(0);
}
