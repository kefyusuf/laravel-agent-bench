import { createHash } from 'node:crypto';
import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { listFilesRecursive } from './scenarios.mjs';

export const GIT = 'git';

export const DEFAULT_PROTECTED_PATHS = [
  'composer.json',
  'phpunit.xml',
  'tests',
  'bootstrap/app.php',
  'bootstrap/providers.php',
  'vendor',
];

function git(args, cwd, input) {
  const res = spawnSync(GIT, args, { cwd, input, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  return { code: res.status, stdout: res.stdout ?? '', stderr: res.stderr ?? '' };
}

export function gitAvailable() {
  const res = spawnSync(GIT, ['--version'], { encoding: 'utf8' });
  return res.status === 0;
}

export function hashFile(absPath) {
  return createHash('sha256').update(readFileSync(absPath)).digest('hex');
}

/** Render the parameterized composer.json from the base stub for a matrix cell. */
export function renderComposerJson(baseDir, cell) {
  const template = readFileSync(path.join(baseDir, 'composer.json.in'), 'utf8');
  return template
    .replaceAll('%%LARAVEL%%', cell.laravel)
    .replaceAll('%%PHPUNIT%%', cell.phpunit)
    .replaceAll('%%PHP%%', cell.php);
}

/**
 * Materialize a scenario workspace:
 *   base skeleton + scenario snapshot overlay + rendered composer.json + git init/commit
 *   + optional vendor cache copy + protected-file manifest (+ optional hidden tests).
 *
 * Overlay precedence: snapshot files overwrite base files with the same relative path.
 * Returns the manifest object — grading callers MUST keep it in memory; the on-disk
 * copy lives inside the agent-writable workspace and is therefore not trustworthy.
 */
export function materializeWorkspace({
  baseDir,
  scenarioDir,
  cell,
  outDir,
  withHidden = false,
  vendorDir = null,
  protectedPaths = DEFAULT_PROTECTED_PATHS,
}) {
  rmSync(outDir, { recursive: true, force: true });
  mkdirSync(outDir, { recursive: true });

  // 1. base skeleton (everything except composer.json.in)
  cpSync(baseDir, outDir, {
    recursive: true,
    filter: (src) => path.basename(src) !== 'composer.json.in',
  });

  // 2. scenario overlay
  const snapshotDir = scenarioDir ? path.join(scenarioDir, 'snapshot') : null;
  if (snapshotDir && existsSync(snapshotDir)) {
    cpSync(snapshotDir, outDir, { recursive: true, force: true });
  }

  // 3. hidden tests (grading workspaces only)
  if (withHidden && scenarioDir) {
    const hiddenDir = path.join(scenarioDir, 'hidden');
    if (existsSync(hiddenDir)) {
      cpSync(hiddenDir, outDir, { recursive: true, force: true });
    }
  }

  // 4. rendered composer.json
  writeFileSync(path.join(outDir, 'composer.json'), renderComposerJson(baseDir, cell));

  // 5. git repo with a single baseline commit; deterministic identity, no autocrlf surprises
  git(['init', '-b', 'main'], outDir);
  git(['config', 'user.email', 'bench@laravel-agent-bench.invalid'], outDir);
  git(['config', 'user.name', 'bench'], outDir);
  git(['config', 'core.autocrlf', 'false'], outDir);
  git(['add', '-A'], outDir);
  const commit = git(['commit', '-m', 'baseline', '--no-gpg-sign'], outDir);
  if (commit.code !== 0) throw new Error(`git baseline commit failed in ${outDir}: ${commit.stderr}`);

  // 6. vendor cache (kept untracked via .gitignore) — copied BEFORE hashing so that
  //    vendor is covered by the tamper manifest (agents can patch phpunit itself)
  if (vendorDir) {
    cpSync(vendorDir, path.join(outDir, 'vendor'), { recursive: true });
  }

  // 7. protected-file manifest
  const manifest = {
    protectedPaths,
    protected: {},
    initialCommit: baselineCommit(outDir),
  };
  for (const rel of protectedPaths) {
    const abs = path.join(outDir, rel);
    if (!existsSync(abs)) continue;
    manifest.protected[rel] = hashTree(abs, outDir);
  }
  mkdirSync(path.join(outDir, '.bench'), { recursive: true });
  writeFileSync(path.join(outDir, '.bench', 'manifest.json'), JSON.stringify(manifest, null, 2));

  return manifest;
}

function hashTree(abs, workspaceRoot) {
  const rel = path.relative(workspaceRoot, abs).split(path.sep).join('/');
  if (!existsSync(abs)) return null;
  if (!statSync(abs).isDirectory()) return { [rel]: hashFile(abs) };
  const hashes = {};
  for (const f of listFilesRecursive(abs)) {
    hashes[path.posix.join(rel, f)] = hashFile(path.join(abs, f));
  }
  return hashes;
}

export function baselineCommit(workspaceDir) {
  const res = git(['rev-list', '--max-parents=0', 'HEAD'], workspaceDir);
  if (res.code !== 0) throw new Error(`cannot resolve baseline commit: ${res.stderr}`);
  return res.stdout.trim();
}

/** Read the manifest of an existing workspace (fallback for external inspection only). */
export function readManifest(workspaceDir) {
  return JSON.parse(readFileSync(path.join(workspaceDir, '.bench', 'manifest.json'), 'utf8'));
}

/**
 * Tamper check: re-hash protected paths and compare against the manifest.
 * Grading MUST pass the in-memory manifest returned by materializeWorkspace —
 * the on-disk copy is inside the agent-writable workspace and self-verifying.
 * Returns list of violations (empty = clean).
 */
export function tamperCheck(workspaceDir, manifest = null) {
  const m = manifest ?? readManifest(workspaceDir);
  const violations = [];
  for (const rel of m.protectedPaths) {
    const abs = path.join(workspaceDir, rel);
    const before = m.protected[rel] ?? null;
    const after = existsSync(abs) ? hashTree(abs, workspaceDir) : null;
    const beforeStr = before === null ? '<absent>' : JSON.stringify(before);
    const afterStr = after === null ? '<absent>' : JSON.stringify(after);
    if (beforeStr !== afterStr) violations.push(rel);
  }
  return violations;
}

/** Extract the agent patch as unified diff text (baseline -> current worktree). */
export function extractPatch(workspaceDir, initialCommit = null) {
  git(['add', '-A', '--', '.', ':(exclude).bench', ':(exclude)PROMPT.md'], workspaceDir);
  // Diff against the stored baseline commit so that agent-made commits are still
  // captured (git diff --cached alone would compare against the agent's HEAD).
  const base = initialCommit ?? baselineCommit(workspaceDir);
  const res = git(['diff', '--cached', '--binary', '--no-color', base], workspaceDir);
  if (res.code !== 0) throw new Error(`git diff failed: ${res.stderr}`);
  return res.stdout;
}

/** Apply a patch (unified diff text) to a workspace. checkOnly=true only verifies applicability. */
export function applyPatch(workspaceDir, patchText, { checkOnly = false } = {}) {
  const args = ['apply', '--whitespace=nowarn'];
  if (checkOnly) args.push('--check');
  args.push('-');
  const res = git(args, workspaceDir, patchText);
  return { ok: res.code === 0, stderr: res.stderr, stdout: res.stdout };
}
