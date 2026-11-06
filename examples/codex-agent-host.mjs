#!/usr/bin/env node
/**
 * Host-side wrapper that runs OpenAI Codex CLI against a materialized benchmark
 * workspace and extracts the agent's patch.
 *
 * Why host-side: grading needs PHP (run it in the bench container), while Codex
 * needs its own binary + auth (run it here). The documented flow is:
 *
 *   # 1. materialize a workspace that includes the vendor cache (container):
 *   docker compose run --rm bench node bin/bench.mjs materialize \
 *       --scenario <id> --out /app/agentws/<id>
 *
 *   # 2. run the agent and capture its patch (host):
 *   node examples/codex-agent-host.mjs agentws/<id>
 *
 *   # 3. grade the patch through the full pipeline (container):
 *   docker compose run --rm bench node bin/bench.mjs run \
 *       --scenario <id> --patch var/agentws/<id>.patch
 *
 * Usage: node examples/codex-agent-host.mjs <workspace-dir> [extra codex args...]
 * The workspace must exist, be a git repo (materialize guarantees both) and
 * contain PROMPT.md. The patch is written next to the workspace as <name>.patch.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const [workspace, ...extraArgs] = process.argv.slice(2);
if (!workspace) {
  console.error('usage: codex-agent-host.mjs <workspace-dir> [extra codex args...]');
  process.exit(2);
}

const wsRoot = path.resolve(workspace);
const promptPath = path.join(wsRoot, 'PROMPT.md');
const prompt = readFileSync(promptPath, 'utf8');

console.log(`[codex-agent] workspace: ${wsRoot}`);
console.log('[codex-agent] launching codex exec (this can take minutes)...');

const res = spawnSync(
  'codex',
  [
    'exec',
    '-C', wsRoot,
    '-s', 'workspace-write',
    '--skip-git-repo-check',
    '--color', 'never',
    ...extraArgs,
    prompt,
  ],
  { stdio: ['ignore', 'inherit', 'inherit'] },
);

if (res.error) {
  console.error(`[codex-agent] failed to launch codex: ${res.error.message}`);
  process.exit(1);
}
if (res.status !== 0) {
  console.error(`[codex-agent] codex exited with status ${res.status} — its partial output may still contain useful work`);
}

// Extract the patch (same semantics as the harness: baseline -> current worktree).
const git = (args) => spawnSync('git', args, { cwd: wsRoot, encoding: 'utf8' });
git(['add', '-A', '--', '.', ':(exclude).bench', ':(exclude)PROMPT.md']);
const diff = git(['diff', '--cached', '--binary', '--no-color']);
if (diff.status !== 0) {
  console.error(`[codex-agent] git diff failed: ${diff.stderr}`);
  process.exit(1);
}

const patchPath = `${wsRoot.replace(/[\\/]+$/, '')}.patch`;
writeFileSync(patchPath, diff.stdout);
console.log(`[codex-agent] patch written: ${patchPath} (${diff.stdout.length} bytes)`);
process.exit(0);
