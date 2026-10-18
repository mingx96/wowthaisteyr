/**
 * PHP CLI Bridge for Syntax Checking and Interface Contract Verification
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const DEFAULT_LOCAL_PHP = 'C:\\Users\\mnguy\\AppData\\Roaming\\Local\\lightning-services\\php-8.2.29+0\\bin\\win64\\php.exe';

export function getPhpBinary() {
    if (process.env.PHP_BIN && fs.existsSync(process.env.PHP_BIN)) {
        return process.env.PHP_BIN;
    }
    if (fs.existsSync(DEFAULT_LOCAL_PHP)) {
        return DEFAULT_LOCAL_PHP;
    }
    return 'php';
}

export function isPhpAvailable() {
    const bin = getPhpBinary();
    try {
        execFileSync(bin, ['-v'], { stdio: 'pipe' });
        return true;
    } catch {
        return false;
    }
}

export function phpLintFile(absFilePath) {
    const bin = getPhpBinary();
    try {
        const out = execFileSync(bin, ['-l', absFilePath], { stdio: 'pipe' }).toString('utf8');
        return { success: true, output: out.trim() };
    } catch (err) {
        return { success: false, error: err.message, output: (err.stdout || err.stderr || '').toString('utf8') };
    }
}

export function phpEval(phpCode) {
    const bin = getPhpBinary();
    try {
        const out = execFileSync(bin, ['-r', phpCode], { stdio: 'pipe' }).toString('utf8');
        return { success: true, output: out };
    } catch (err) {
        return { success: false, error: err.message, output: (err.stdout || err.stderr || '').toString('utf8') };
    }
}
