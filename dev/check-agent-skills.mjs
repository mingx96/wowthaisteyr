#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const root = process.cwd();
const names = ['wordpress-theme', 'woocommerce-checkout-qa', 'wp-project-triage', 'wp-performance', 'web-design-guidelines'];
const errors = [];
for (const name of names) {
  const file = path.join(root, '.agents', 'skills', name, 'SKILL.md');
  if (!fs.existsSync(file)) { errors.push('Missing skill: ' + file); continue; }
  const content = fs.readFileSync(file, 'utf8');
  const frontmatter = content.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!frontmatter) { errors.push('Invalid frontmatter: ' + name); continue; }
  const field = frontmatter[1].match(/^name:\s*(.+)$/m);
  const parsedName = field?.[1]?.trim().replace(/^["']|["']$/g, '');
  if (parsedName !== name) errors.push('Unexpected skill name: ' + name);
  if (!/^description:\s*\S/m.test(frontmatter[1])) errors.push('Missing skill description: ' + name);
}
for (const p of [
  '.agents/skills/wp-project-triage/references/triage.schema.json',
  '.agents/skills/wp-project-triage/scripts/detect_wp_project.mjs',
  '.agents/skills/wp-performance/scripts/perf_inspect.mjs',
  '.agents/skills/web-design-guidelines/references/web-interface-guidelines.md',
  'third_party/licenses/wordpress-gpl.txt',
]) {
  if (!fs.existsSync(path.join(root, p))) errors.push('Missing bundled dependency: ' + p);
}
const detector = path.join(root, '.agents/skills/wp-project-triage/scripts/detect_wp_project.mjs');
if (fs.existsSync(detector)) {
  const run = spawnSync(process.execPath, [detector], { cwd: root, encoding: 'utf8', maxBuffer: 2_000_000 });
  if (run.status !== 0) errors.push('WordPress repo detector failed: ' + run.stderr);
  else {
    try {
      const result = JSON.parse(run.stdout);
      if (!result?.project || !result?.signals || !result?.tooling) errors.push('Detector report missing fields');
    } catch { errors.push('Detector did not produce valid JSON'); }
  }
}
if (errors.length) {
  console.error(errors.map(x => 'FAIL: ' + x).join('\n'));
  process.exit(1);
}
console.log('PASS: ' + names.length + ' skills have valid metadata and WordPress detector runs.');
