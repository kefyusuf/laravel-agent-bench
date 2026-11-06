import { cpSync, existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { applyPatch, extractPatch, materializeWorkspace, tamperCheck } from './materialize.mjs';
import { buildPrompt } from './prompt.mjs';
import { parseSummary, runPhpunit, tail } from './phpunit.mjs';

/** var/vendor/<cell.id> if built, else null. */
export function resolveVendorDir(cfg, cell) {
  const p = path.join(cfg._root, cfg.paths.var, 'vendor', cell.id);
  return existsSync(path.join(p, 'autoload.php')) ? p : null;
}

export function runAgentCommand(workspaceDir, promptPath, cmdTemplate, timeoutSeconds) {
  const cmd = cmdTemplate
    .replaceAll('{{workspace}}', JSON.stringify(workspaceDir))
    .replaceAll('{{prompt}}', JSON.stringify(promptPath));
  const res = spawnSync(cmd, { shell: true, encoding: 'utf8', timeout: timeoutSeconds * 1000, maxBuffer: 64 * 1024 * 1024 });
  return {
    code: res.status ?? -1,
    stdout: tail(res.stdout ?? '', 40),
    stderr: tail(res.stderr ?? '', 40),
    timedOut: res.error?.code === 'ETIMEDOUT',
  };
}

/** A phpunit result only counts as executed if its summary parsed and it ran tests. */
function looksExecuted(parsed) {
  return parsed.tests !== null && parsed.tests > 0;
}

/**
 * Full grading flow for one scenario in one matrix cell.
 * Provide exactly one of: agentCmd (command with {{workspace}} / {{prompt}} placeholders) or patchFile.
 * The workspace is removed unless keep=true.
 */
export function runGrade({ cfg, scenario, cell, workspacesRoot, agentCmd = null, patchFile = null, keep = false }) {
  const doc = scenario.doc;
  const pid = process.pid;
  const ws = path.join(workspacesRoot, `${scenario.id}--${cell.id}--${pid}`);
  const result = {
    scenario: scenario.id,
    cell: cell.id,
    mode: agentCmd ? 'agent' : 'patch',
    verdict: 'error',
    tamper: [],
    agent: null,
    visible: null,
    hidden: null,
  };

  try {
    const vendorDir = resolveVendorDir(cfg, cell);
    const manifest = materializeWorkspace({
      baseDir: path.join(cfg._root, cfg.paths.base),
      scenarioDir: scenario.dir,
      cell,
      outDir: ws,
      withHidden: false,
      vendorDir,
      protectedPaths: cfg.grader?.protectedPaths,
    });

    const promptPath = path.join(ws, 'PROMPT.md');
    writeFileSync(promptPath, buildPrompt(doc));

    if (agentCmd) {
      result.agent = runAgentCommand(ws, promptPath, agentCmd, cfg.agent?.timeoutSeconds ?? 1800);
      if (result.agent.timedOut) {
        result.verdict = 'agent-timeout';
        return result;
      }
    } else {
      const text = readFileSync(patchFile, 'utf8');
      const applied = applyPatch(ws, text);
      if (!applied.ok) {
        result.verdict = 'patch-not-applicable';
        result.agent = { stderr: tail(applied.stderr, 20) };
        return result;
      }
    }

    result.tamper = tamperCheck(ws, manifest);
    if (result.tamper.length > 0) {
      result.verdict = 'tampered';
      return result;
    }

    result.patch = extractPatch(ws, manifest.initialCommit);
    if (!result.patch.trim()) {
      result.verdict = 'no-change';
      return result;
    }

    // visible run (hidden tests not present yet)
    const vis = runPhpunit({ workspaceDir: ws });
    result.visible = { code: vis.code, timedOut: vis.timedOut, ...parseSummary(vis), output: tail(`${vis.stdout}\n${vis.stderr}`) };
    if (vis.code !== 0 || !looksExecuted(result.visible)) {
      result.verdict = 'fail-visible';
      if (!looksExecuted(result.visible)) {
        result.visible.output = 'phpunit did not execute a parsed, non-empty test summary — the run is not gradeable. ' + result.visible.output;
      }
      return result;
    }

    // re-check tamper: the visible run executed agent code, which could have
    // mutated protected paths after the first check
    result.tamper = tamperCheck(ws, manifest);
    if (result.tamper.length > 0) {
      result.verdict = 'tampered';
      return result;
    }

    // inject hidden tests and run the full suite
    const hiddenDir = path.join(scenario.dir, 'hidden');
    if (!existsSync(hiddenDir)) {
      result.error = 'scenario has no hidden tests';
      return result;
    }
    cpSync(hiddenDir, ws, { recursive: true });
    const hid = runPhpunit({ workspaceDir: ws });
    result.hidden = { code: hid.code, timedOut: hid.timedOut, ...parseSummary(hid), output: tail(`${hid.stdout}\n${hid.stderr}`) };
    if (hid.code !== 0 || !looksExecuted(result.hidden)) {
      result.verdict = 'fail-hidden';
      return result;
    }
    result.verdict = 'pass';
    return result;
  } catch (e) {
    result.error = String(e);
    return result;
  } finally {
    if (!keep) {
      if (existsSync(ws)) rmSync(ws, { recursive: true, force: true });
    }
  }
}
