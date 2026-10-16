#!/usr/bin/env node
/**
 * WOW Thai Steyr — Deterministic Theme ZIP Builder
 * Runs dev/verify.mjs first, then creates ../wowthai.zip with forward-slash paths,
 * excluding dev/, .git/, COORDINATION.md, *.md, and node_modules/.
 */
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');
const OUT_ZIP = path.resolve(ROOT, '..', 'wowthai.zip');

// 1. Run verification gate first
console.log('Running dev/verify.mjs before building wowthai.zip...');
execFileSync(process.execPath, [path.join(__dirname, 'verify.mjs')], { stdio: 'inherit' });

// 2. Collect files to package
function collectThemeFiles(dir, relBase = '') {
    const out = [];
    const entries = fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name));
    for (const entry of entries) {
        if (['.git', 'dev', 'node_modules', '.vscode', '.idea'].includes(entry.name)) continue;
        if (entry.name.startsWith('.')) continue;
        if (entry.name.endsWith('.md')) continue;
        const rel = relBase ? `${relBase}/${entry.name}` : entry.name;
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            out.push(...collectThemeFiles(full, rel));
        } else {
            out.push({ rel, full });
        }
    }
    return out;
}

// CRC32 implementation for deterministic ZIP creation
const crcTable = new Uint32Array(256);
for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) {
        c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
    }
    crcTable[n] = c >>> 0;
}
function crc32(buf) {
    let crc = 0xffffffff;
    for (let i = 0; i < buf.length; i++) {
        crc = crcTable[(crc ^ buf[i]) & 0xff] ^ (crc >>> 8);
    }
    return (crc ^ 0xffffffff) >>> 0;
}

const files = collectThemeFiles(ROOT);
const localParts = [];
const centralParts = [];
let offset = 0;

// Fixed DOS date/time (2026-10-16 12:00:00) for deterministic builds
const dosTime = (12 << 11) | (0 << 5) | 0;
const dosDate = ((2026 - 1980) << 9) | (10 << 5) | 16;

for (const file of files) {
    const zipEntryName = Buffer.from(`wowthai/${file.rel}`, 'utf8');
    const rawData = fs.readFileSync(file.full);
    const compressed = zlib.deflateRawSync(rawData, { level: 9 });
    const crc = crc32(rawData);

    const localHeader = Buffer.alloc(30 + zipEntryName.length);
    localHeader.writeUInt32LE(0x04034b50, 0); // Local file header signature
    localHeader.writeUInt16LE(20, 4);         // Version needed to extract (2.0)
    localHeader.writeUInt16LE(0x0800, 6);     // UTF-8 flag
    localHeader.writeUInt16LE(8, 8);          // Compression method: deflate
    localHeader.writeUInt16LE(dosTime, 10);
    localHeader.writeUInt16LE(dosDate, 12);
    localHeader.writeUInt32LE(crc, 14);
    localHeader.writeUInt32LE(compressed.length, 18);
    localHeader.writeUInt32LE(rawData.length, 22);
    localHeader.writeUInt16LE(zipEntryName.length, 26);
    localHeader.writeUInt16LE(0, 28);         // Extra field length
    zipEntryName.copy(localHeader, 30);

    const centralHeader = Buffer.alloc(46 + zipEntryName.length);
    centralHeader.writeUInt32LE(0x02014b50, 0); // Central directory signature
    centralHeader.writeUInt16LE(20, 4);         // Version made by
    centralHeader.writeUInt16LE(20, 6);         // Version needed
    centralHeader.writeUInt16LE(0x0800, 8);     // UTF-8 flag
    centralHeader.writeUInt16LE(8, 10);         // Compression method: deflate
    centralHeader.writeUInt16LE(dosTime, 12);
    centralHeader.writeUInt16LE(dosDate, 14);
    centralHeader.writeUInt32LE(crc, 16);
    centralHeader.writeUInt32LE(compressed.length, 20);
    centralHeader.writeUInt32LE(rawData.length, 24);
    centralHeader.writeUInt16LE(zipEntryName.length, 28);
    centralHeader.writeUInt16LE(0, 30);
    centralHeader.writeUInt16LE(0, 32);
    centralHeader.writeUInt16LE(0, 34);
    centralHeader.writeUInt16LE(0, 36);
    centralHeader.writeUInt32LE(0, 38);
    centralHeader.writeUInt32LE(offset, 42);
    zipEntryName.copy(centralHeader, 46);

    localParts.push(localHeader, compressed);
    centralParts.push(centralHeader);
    offset += localHeader.length + compressed.length;
}

const centralDir = Buffer.concat(centralParts);
const eocd = Buffer.alloc(22);
eocd.writeUInt32LE(0x06054b50, 0);
eocd.writeUInt16LE(0, 4);
eocd.writeUInt16LE(0, 6);
eocd.writeUInt16LE(files.length, 8);
eocd.writeUInt16LE(files.length, 10);
eocd.writeUInt32LE(centralDir.length, 12);
eocd.writeUInt32LE(offset, 16);
eocd.writeUInt16LE(0, 20);

const finalZip = Buffer.concat([...localParts, centralDir, eocd]);
fs.writeFileSync(OUT_ZIP, finalZip);
console.log(`Built ${OUT_ZIP} (${files.length} files, ${(finalZip.length / 1024).toFixed(1)} KB)`);
