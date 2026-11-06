import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { existsSync } from 'node:fs';

/**
 * Run phpunit inside a materialized workspace.
 * php is resolved from BENCH_PHP env var, or plain "php" from PATH.
 * Returns { code, stdout, stderr, timedOut }.
 */
export function runPhpunit({ workspaceDir, timeoutMs = 120000, extraArgs = [] }) {
  const phpBin = process.env.BENCH_PHP || 'php';
  const phpunitBin = path.join('vendor', 'phpunit', 'phpunit', 'phpunit');
  if (!existsSync(path.join(workspaceDir, phpunitBin))) {
    return { code: -1, stdout: '', stderr: 'vendor/phpunit not present in workspace; run `bench vendor:prepare` first', timedOut: false };
  }
  const res = spawnSync(phpBin, [phpunitBin, '--no-coverage', ...extraArgs], {
    cwd: workspaceDir,
    encoding: 'utf8',
    timeout: timeoutMs,
    maxBuffer: 64 * 1024 * 1024,
  });
  const timedOut = res.error?.code === 'ETIMEDOUT';
  return {
    code: res.status ?? (timedOut ? -2 : -1),
    stdout: res.stdout ?? '',
    stderr: res.stderr ?? '',
    timedOut,
  };
}

/** Parse the phpunit summary line ("OK (12 tests, 40 assertions)" / "Tests: 3, Assertions: 5, Failures: 2.") into counts. */
export function parseSummary(output) {
  // phpunit wraps colored summary segments in ANSI escapes ("Tests: 6" may arrive as
  // ESC[37;41mTests: 6ESC[0m), which breaks naive matching — strip them first.
  const ansi = /\u001B\[[0-9;]*m/g;
  const combined = `${output.stdout}\n${output.stderr}`.replace(ansi, '');
  const ok = combined.match(/OK \((\d+) tests?, (\d+) assertions?\)/);
  if (ok) {
    return { pass: true, tests: Number(ok[1]), assertions: Number(ok[2]) };
  }
  const summary = combined.match(/Tests?:\s*(\d+),\s*Assertions?:\s*(\d+)(?:,\s*(\w+)s?:\s*(\d+))?/);
  if (summary) {
    return {
      pass: false,
      tests: Number(summary[1]),
      assertions: Number(summary[2]),
      failureKind: summary[3] ?? 'failures',
      failureCount: Number(summary[4] ?? 0),
    };
  }
  return { pass: output.code === 0, tests: null, assertions: null, unparsed: true };
}

/** Truncate phpunit output for reports (keep tail; failures appear at the end). */
export function tail(text, maxLines = 60) {
  const lines = text.split(/\r?\n/).filter(Boolean);
  const sliced = lines.slice(-maxLines);
  return sliced.join('\n');
}
