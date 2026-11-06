import { readFileSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/** Repo root = directory containing package.json + bench.json, walking up from this module. */
export function repoRoot() {
  let dir = path.dirname(fileURLToPath(import.meta.url));
  for (let i = 0; i < 6; i++) {
    try {
      if (statSync(path.join(dir, 'package.json')).isFile() && statSync(path.join(dir, 'bench.json')).isFile()) {
        return dir;
      }
    } catch {
      // keep walking up
    }
    dir = path.dirname(dir);
  }
  throw new Error('Repository root not found (missing package.json + bench.json).');
}

export function loadConfig(root = repoRoot()) {
  const cfg = JSON.parse(readFileSync(path.join(root, 'bench.json'), 'utf8'));
  for (const key of ['paths', 'matrix']) {
    if (!cfg[key]) throw new Error(`bench.json missing "${key}"`);
  }
  cfg._root = root;
  return cfg;
}

export function cellById(cfg, id) {
  return cfg.matrix.find((c) => c.id === id) ?? null;
}

/** Resolve a config-relative path to an absolute path. */
export function cfgPath(cfg, key) {
  return path.join(cfg._root, cfg.paths[key]);
}
