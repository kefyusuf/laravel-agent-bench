import { existsSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { materializeWorkspace } from './materialize.mjs';
import { runAgentCommand } from './grade.mjs';
import { buildDiagnosisPrompt, scoreDiagnosis } from './prompt.mjs';

/**
 * Diagnosis mode: the agent must NOT modify anything; it analyzes and reports.
 * Grading is deterministic — at least one of the scenario's
 * diagnosis_keywords (correct-mechanism phrasings, OR semantics) must appear
 * in the <diagnosis> section of the agent's output.
 */
export function runDiagnose({ cfg, scenario, cell, workspacesRoot, agentCmd, keep = false }) {
  const doc = scenario.doc;
  const ws = path.join(workspacesRoot, `diagnosis-${scenario.id}--${cell.id}--${process.pid}`);
  const result = {
    scenario: scenario.id,
    cell: cell.id,
    mode: 'diagnosis',
    verdict: 'error',
    agent: null,
    matched: [],
    missing: [],
  };

  try {
    const vendorDir = existsSync(path.join(cfg._root, cfg.paths.var, 'vendor', cell.id, 'autoload.php'))
      ? path.join(cfg._root, cfg.paths.var, 'vendor', cell.id)
      : null;
    materializeWorkspace({
      baseDir: path.join(cfg._root, cfg.paths.base),
      scenarioDir: scenario.dir,
      cell,
      outDir: ws,
      withHidden: false,
      vendorDir,
      protectedPaths: cfg.grader?.protectedPaths,
    });

    const promptPath = path.join(ws, 'PROMPT.md');
    writeFileSync(promptPath, buildDiagnosisPrompt(doc));

    result.agent = runAgentCommand(ws, promptPath, agentCmd, cfg.agent?.timeoutSeconds ?? 1800);
    if (result.agent.timedOut) {
      result.verdict = 'agent-timeout';
      return result;
    }

    const keywords = doc.diagnosis_keywords ?? [];
    if (keywords.length === 0) {
      result.error = 'scenario has no diagnosis_keywords';
      return result;
    }

    const scored = scoreDiagnosis(`${result.agent.stdout ?? ''}\n${result.agent.stderr ?? ''}`, keywords);
    result.matched = scored.matched;
    result.missing = scored.missing;
    result.hadTags = scored.hadTags;
    result.verdict = scored.pass ? 'diagnosis-pass' : 'diagnosis-miss';
    return result;
  } catch (e) {
    result.error = String(e);
    return result;
  } finally {
    if (!keep && existsSync(ws)) {
      rmSync(ws, { recursive: true, force: true });
    }
  }
}
