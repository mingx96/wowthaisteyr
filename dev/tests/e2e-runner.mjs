#!/usr/bin/env node
/**
 * WoW Thai Platform & WoW Express — Automated E2E Master Test Runner
 * 
 * Executes full 4-tier opaque-box E2E test suite:
 * - Tier 1: Feature Coverage (PLZ, Branch Identity, Delivery Fee, Dual Bon, Dashboard, DSGVO)
 * - Tier 2: Boundary & Corner Cases (PLZ, Subtotal 1-cent, Zero/Negative, German Umlauts, Empty Fields)
 * - Tier 3: Cross-Feature Combinations (Branch switching, Core COD, Extended Stripe, Out-of-zone fallback)
 * - Tier 4: Real-World Application Scenarios (Family dinner, Lunch upsell, Commuter fallback, Peak surge, Full dispatch lifecycle)
 * 
 * Usage:
 *   node dev/tests/e2e-runner.mjs
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { harness } from './lib/test-harness.mjs';
import { isPhpAvailable, phpLintFile } from './lib/php-bridge.mjs';
import { registerTier1Tests } from './tier1-feature-coverage.test.mjs';
import { registerTier2Tests } from './tier2-boundaries-corners.test.mjs';
import { registerTier3Tests } from './tier3-cross-feature.test.mjs';
import { registerTier4Tests } from './tier4-real-world-scenarios.test.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const THEME_DIR = path.resolve(__dirname, '../..');

async function main() {
    console.log('='.repeat(70));
    console.log(' WOW THAI & WOW EXPRESS — AUTOMATED E2E TEST RUNNER');
    console.log(' Canonical Theme Directory: ' + THEME_DIR);
    console.log(' Node.js Version:           ' + process.version);
    console.log(' PHP Available:             ' + (isPhpAvailable() ? 'YES (PHP 8.2+)' : 'NO'));
    console.log('='.repeat(70));

    // Step 1: Pre-flight syntax validation of theme PHP files
    if (isPhpAvailable()) {
        const phpFiles = fs.readdirSync(THEME_DIR).filter(f => f.endsWith('.php'));
        const incDir = path.join(THEME_DIR, 'inc');
        if (fs.existsSync(incDir)) {
            phpFiles.push(...fs.readdirSync(incDir).filter(f => f.endsWith('.php')).map(f => 'inc/' + f));
        }
        let syntaxErrors = 0;
        for (const rel of phpFiles) {
            const fullPath = path.join(THEME_DIR, rel);
            const res = phpLintFile(fullPath);
            if (!res.success) {
                syntaxErrors++;
                console.error(`❌ PHP Syntax Error in ${rel}: ${res.output}`);
            }
        }
        if (syntaxErrors > 0) {
            console.error(`\nAborting: Found ${syntaxErrors} PHP syntax errors.`);
            process.exit(1);
        }
        console.log(`✅ Pre-flight: Verified syntax of ${phpFiles.length} PHP theme files cleanly.\n`);
    }

    // Step 2: Register all 4 Test Tiers
    registerTier1Tests();
    registerTier2Tests();
    registerTier3Tests();
    registerTier4Tests();

    // Step 3: Execute all suites
    const summary = await harness.run();

    // Step 4: Tier-by-Tier Breakdown Reporting
    console.log('\n' + '='.repeat(70));
    console.log(' TIER-BY-TIER COVERAGE SUMMARY');
    console.log('='.repeat(70));
    
    const tierGroups = {
        'Tier 1: Feature Coverage': summary.suites.filter(s => s.name.startsWith('Tier 1')),
        'Tier 2: Boundary & Corner Cases': summary.suites.filter(s => s.name.startsWith('Tier 2')),
        'Tier 3: Cross-Feature Combinations': summary.suites.filter(s => s.name.startsWith('Tier 3')),
        'Tier 4: Real-World Application Scenarios': summary.suites.filter(s => s.name.startsWith('Tier 4'))
    };

    let allTiersPassed = true;
    for (const [tierTitle, suites] of Object.entries(tierGroups)) {
        const totalTests = suites.reduce((sum, s) => sum + s.tests.length, 0);
        const passedTests = suites.reduce((sum, s) => sum + s.passed, 0);
        const failedTests = suites.reduce((sum, s) => sum + s.failed, 0);
        const statusIcon = failedTests === 0 && totalTests > 0 ? '✅' : '❌';
        if (failedTests > 0) allTiersPassed = false;
        console.log(` ${statusIcon} ${tierTitle.padEnd(42)} ${passedTests}/${totalTests} passed (${failedTests} failed)`);
    }
    console.log('='.repeat(70));

    if (summary.failed > 0 || !allTiersPassed) {
        console.error(`\n❌ TEST SUITE FAILED: ${summary.failed} failure(s) detected.`);
        process.exit(1);
    } else {
        console.log(`\n✅ ALL TESTS PASSED: 100% success rate across all 4 tiers (${summary.total} tests).`);
        process.exit(0);
    }
}

main().catch(err => {
    console.error('Fatal Test Runner Exception:', err);
    process.exit(1);
});
