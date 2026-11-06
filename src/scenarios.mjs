import { readdirSync, readFileSync, existsSync, statSync } from 'node:fs';
import path from 'node:path';

/**
 * Discover all scenarios under <root>/scenarios/<family-dir>/<scenario-id>/scenario.json.
 * Returns [{ id, dir, familyDir, doc, docPath }].
 */
export function discoverScenarios(scenariosDir) {
  const out = [];
  if (!existsSync(scenariosDir)) return out;
  for (const familyDir of readdirSync(scenariosDir)) {
    const famPath = path.join(scenariosDir, familyDir);
    if (!statSync(famPath).isDirectory() || familyDir.startsWith('.')) continue;
    for (const entry of readdirSync(famPath)) {
      const scenPath = path.join(famPath, entry);
      const docPath = path.join(scenPath, 'scenario.json');
      if (!statSync(scenPath).isDirectory() || !existsSync(docPath)) continue;
      let doc;
      try {
        doc = JSON.parse(readFileSync(docPath, 'utf8'));
      } catch (e) {
        out.push({ id: entry, dir: scenPath, familyDir, doc: null, docPath, parseError: String(e) });
        continue;
      }
      out.push({ id: entry, dir: scenPath, familyDir, doc, docPath });
    }
  }
  out.sort((a, b) => (a.familyDir + a.id).localeCompare(b.familyDir + b.id));
  return out;
}

/** Files under dir (recursive), as POSIX-style relative paths. */
export function listFilesRecursive(dir, base = dir) {
  const out = [];
  for (const entry of readdirSync(dir)) {
    const p = path.join(dir, entry);
    if (statSync(p).isDirectory()) {
      out.push(...listFilesRecursive(p, base));
    } else {
      out.push(path.relative(base, p).split(path.sep).join('/'));
    }
  }
  return out.sort();
}

export function hasVisibleTests(scenarioDir) {
  const testsDir = path.join(scenarioDir, 'snapshot', 'tests');
  if (!existsSync(testsDir)) return false;
  return listFilesRecursive(testsDir).some((f) => f.endsWith('Test.php'));
}

export function hasHiddenTests(scenarioDir) {
  const hiddenDir = path.join(scenarioDir, 'hidden');
  if (!existsSync(hiddenDir)) return false;
  return listFilesRecursive(hiddenDir).some((f) => f.endsWith('Test.php'));
}
