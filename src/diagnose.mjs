import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { materializeWorkspace } from './materialize.mjs';
import { runAgentCommand } from './grade.mjs';
import { buildDiagnosisPrompt, buildJudgePrompt, scoreDiagnosis, parseJudgeVerdict } from './prompt.mjs';

/**
 * Diagnosis mode: the agent must NOT modify anything; it analyzes and reports.
 * Grading is deterministic — at least one of the scenario's
 * diagnosis_keywords (correct-mechanism phrasings, OR semantics) must appear
 * in the <diagnosis> section of the agent's output.
 *
 * With judgeCmd set, a second agent run grades the diagnosis against the
 * known root cause (expected_diagnosis). The judge verdict is RECORDED for
 * reporting — it does not gate the deterministic keyword verdict.
 */
export function runDiagnose({ cfg, scenario, cell, workspacesRoot, agentCmd, judgeCmd = null, keep = false }) {
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
    judge: null,
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

    const combined = `${result.agent.stdout ?? ''}\n${result.agent.stderr ?? ''}`;
    const scored = scoreDiagnosis(combined, keywords);
    result.matched = scored.matched;
    result.missing = scored.missing;
    result.hadTags = scored.hadTags;

    if (judgeCmd) {
      const open = combined.lastIndexOf('<diagnosis>');
      const close = combined.lastIndexOf('</diagnosis>');
      const diagnosisText = scored.hadTags
        ? combined.slice(open + '<diagnosis>'.length, close)
        : combined;

      mkdirSync(path.join(ws, '.bench'), { recursive: true });
      const judgePromptPath = path.join(ws, '.bench', 'judge-prompt.md');
      writeFileSync(judgePromptPath, buildJudgePrompt(doc, diagnosisText));

      result.judge = runAgentCommand(ws, judgePromptPath, judgeCmd, cfg.agent?.timeoutSeconds ?? 1800);
      const parsed = parseJudgeVerdict(`${result.judge.stdout ?? ''}\n${result.judge.stderr ?? ''}`);
      result.judge.verdict = parsed?.verdict ?? 'unparsed';
      result.judge.reasons = parsed?.reasons ?? '';

      // The judge grades semantically against the known root cause; the keyword
      // scan is a literal heuristic that misses correct phrasings. When a judge
      // verdict exists, it is authoritative.
      if (result.judge.verdict === 'correct') {
        result.verdict = 'diagnosis-pass';
      } else if (result.judge.verdict === 'partial') {
        result.verdict = 'diagnosis-partial';
      } else if (result.judge.verdict === 'incorrect') {
        result.verdict = 'diagnosis-miss';
      } else {
        result.verdict = scored.pass ? 'diagnosis-pass' : 'diagnosis-miss';
      }
      return result;
    }

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
