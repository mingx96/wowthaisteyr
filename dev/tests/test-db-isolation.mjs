import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';

const STUDIO_ROOT = 'C:\\Users\\mnguy\\Studio';
const PHP_EXE = 'C:\\Users\\mnguy\\.studio\\php-bin\\8.4.26-studio-1\\php.exe';

const SITES = [
    { id: 'steyr', name: 'wow-thai-steyr', port: 8881, db: path.join(STUDIO_ROOT, 'wow-thai-steyr', 'wp-content', 'database', '.ht.sqlite') },
    { id: 'valentin', name: 'wow-thai-valentin', port: 8882, db: path.join(STUDIO_ROOT, 'wow-thai-valentin', 'wp-content', 'database', '.ht.sqlite') },
    { id: 'saalbach', name: 'wow-thai-saalbach', port: 8883, db: path.join(STUDIO_ROOT, 'wow-thai-saalbach', 'wp-content', 'database', '.ht.sqlite') },
    { id: 'express', name: 'wow-express', port: 8884, db: path.join(STUDIO_ROOT, 'wow-express', 'wp-content', 'database', '.ht.sqlite') }
];

function runSql(dbPath, sql) {
    const escapedDb = dbPath.replace(/\\/g, '/');
    const phpCode = `
        $db = new SQLite3('${escapedDb}', SQLITE3_OPEN_READWRITE);
        $db->busyTimeout(5000);
        $res = $db->query('${sql.replace(/'/g, "\\'")}');
        $rows = [];
        if ($res && $res !== true) {
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
        }
        echo json_encode(['success' => true, 'rows' => $rows]);
    `;
    const out = execFileSync(PHP_EXE, ['-r', phpCode], { encoding: 'utf8' });
    return JSON.parse(out);
}

function execSql(dbPath, sql) {
    const escapedDb = dbPath.replace(/\\/g, '/');
    const phpCode = `
        $db = new SQLite3('${escapedDb}', SQLITE3_OPEN_READWRITE);
        $db->busyTimeout(5000);
        $ok = $db->exec('${sql.replace(/'/g, "\\'")}');
        echo json_encode(['success' => $ok]);
    `;
    const out = execFileSync(PHP_EXE, ['-r', phpCode], { encoding: 'utf8' });
    return JSON.parse(out);
}

async function testIsolation() {
    console.log('='.repeat(70));
    console.log(' EMPIRICAL TEST: SQLITE DATABASE MUTATION ISOLATION');
    console.log('='.repeat(70));

    // 1. Verify existence of distinct SQLite database files
    for (const site of SITES) {
        if (!fs.existsSync(site.db)) {
            throw new Error(`Database file not found: ${site.db}`);
        }
        const stat = fs.statSync(site.db);
        console.log(`[${site.id}] DB Size: ${(stat.size / 1024 / 1024).toFixed(2)} MB (${site.db})`);
    }

    // 2. Generate unique test tokens
    const tokens = {
        steyr: `token_steyr_${crypto.randomUUID()}`,
        valentin: `token_valentin_${crypto.randomUUID()}`,
        saalbach: `token_saalbach_${crypto.randomUUID()}`,
        express: `token_express_${crypto.randomUUID()}`
    };

    console.log('\nWriting unique isolation tokens into each database...');
    for (const site of SITES) {
        const token = tokens[site.id];
        const writeSql = `INSERT OR REPLACE INTO wp_options (option_name, option_value, autoload) VALUES ('challenger_test_isolation_key', '${token}', 'no');`;
        execSql(site.db, writeSql);
        console.log(`  -> Wrote token for [${site.id}]: ${token.slice(0, 25)}...`);
    }

    console.log('\nValidating isolation cross-matrix (zero contamination)...');
    let hasError = false;
    for (const targetSite of SITES) {
        const readSql = `SELECT option_value FROM wp_options WHERE option_name = 'challenger_test_isolation_key';`;
        const res = runSql(targetSite.db, readSql);
        const readToken = res.rows?.[0]?.option_value;

        if (readToken === tokens[targetSite.id]) {
            console.log(`  ✅ [${targetSite.id}] matches expected token.`);
        } else {
            console.error(`  ❌ [${targetSite.id}] TOKEN MISMATCH! Expected ${tokens[targetSite.id]}, got ${readToken}`);
            hasError = true;
        }

        // Check that NO other token is present
        for (const otherSite of SITES) {
            if (otherSite.id !== targetSite.id) {
                if (readToken === tokens[otherSite.id]) {
                    console.error(`  ❌ [${targetSite.id}] CONTAMINATED by [${otherSite.id}] token!`);
                    hasError = true;
                }
            }
        }
    }

    console.log('\nCleaning up isolation tokens from all databases...');
    for (const site of SITES) {
        const deleteSql = `DELETE FROM wp_options WHERE option_name = 'challenger_test_isolation_key';`;
        execSql(site.db, deleteSql);
        const checkSql = `SELECT option_value FROM wp_options WHERE option_name = 'challenger_test_isolation_key';`;
        const checkRes = runSql(site.db, checkSql);
        if (checkRes.rows.length === 0) {
            console.log(`  ✅ [${site.id}] Token cleaned up successfully.`);
        } else {
            console.error(`  ❌ [${site.id}] Token deletion failed!`);
            hasError = true;
        }
    }

    console.log('='.repeat(70));
    if (hasError) {
        console.error('ISOLATION TEST FAILED.');
        process.exit(1);
    } else {
        console.log('ALL ISOLATION CHECKS PASSED: 100% Database Independence Verified.');
        process.exit(0);
    }
}

testIsolation().catch(e => {
    console.error(e);
    process.exit(1);
});
