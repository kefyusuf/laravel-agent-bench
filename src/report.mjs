import { existsSync, readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';

/**
 * Aggregate agent-run evidence files (agent-run-<agent>-*.json in a directory)
 * into a markdown score table.
 *
 * Accepted file shapes:
 *   [ { scenario, verdict }, ... ]                 (bench run/run-all --json --out)
 *   { agent, cell, summary: [{scenario, verdict}] } (sweep summaries)
 *
 * The agent label comes from the filename: agent-run-<label>-*.json → <label>.
 * Later files may overwrite earlier verdicts for the same scenario (the most
 * recent evidence wins — files are read in sorted order).
 */
export function buildScoreTable(runsDir, scenariosDir = null) {
  if (!existsSync(runsDir)) {
    return { agents: {}, markdown: '_no agent-run evidence found in ' + runsDir + '_' };
  }

  const agents = {};

  // aggregate <scenario>.json files supersede per-run files for the same
  // (agent, scenario) — parse aggregates first, defer per-run files, and only
  // fall back to them when no aggregate covered the scenario.
  const deferred = [];
  const covered = new Set();

  for (const file of readdirSync(runsDir).sort()) {
    const match = file.match(/^agent-run-([a-z0-9-]+)\.json$/);
    if (!match) continue;
    const agentLabel = match[1].split('-')[0];

    const parsed = JSON.parse(readFileSync(path.join(runsDir, file), 'utf8'));

    const runs = [];
    let isPerRun = false;
    if (Array.isArray(parsed)) {
      runs.push(...parsed.map((r) => ({ scenario: r.scenario, verdict: r.verdict })));
    } else if (parsed && typeof parsed === 'object' && Array.isArray(parsed.summary)) {
      runs.push(...parsed.summary.map((r) => ({ scenario: r.scenario, verdict: r.verdict })));
    } else if (parsed && typeof parsed === 'object' && parsed.scenario && parsed.verdict) {
      runs.push({ scenario: parsed.scenario, verdict: parsed.verdict });
      isPerRun = true;
    } else if (parsed && typeof parsed === 'object' && parsed.scenario && Array.isArray(parsed.verdicts)) {
      for (const v of parsed.verdicts) {
        runs.push({ scenario: parsed.scenario, verdict: v.verdict });
      }
    }

    if (isPerRun) {
      deferred.push({ agentLabel, scenario: parsed.scenario, verdict: parsed.verdict });
      continue;
    }

    agents[agentLabel] ??= {};
    for (const run of runs) {
      (agents[agentLabel][run.scenario] ??= []).push(run.verdict);
      covered.add(agentLabel + ':' + run.scenario);
    }
  }

  // deferred per-run files only fill scenarios no aggregate covered
  for (const { agentLabel, scenario, verdict } of deferred) {
    if (covered.has(agentLabel + ':' + scenario)) continue;
    agents[agentLabel] ??= {};
    (agents[agentLabel][scenario] ??= []).push(verdict);
  }

  const labels = Object.keys(agents).sort();
  if (labels.length === 0) {
    return { agents: {}, markdown: '_no agent evidence found in ' + runsDir + '_' };
  }
  const scenarios = [...new Set(labels.flatMap((l) => Object.keys(agents[l])))].sort();

  // author-assigned difficulty (from the scenario manifests) — recorded next to
  // the measured pass-rates so calibration gaps stay visible
  const difficulty = {};
  if (scenariosDir && existsSync(scenariosDir)) {
    for (const area of readdirSync(scenariosDir)) {
      const areaPath = path.join(scenariosDir, area);
      for (const id of readdirSync(areaPath)) {
        const manifest = path.join(areaPath, id, 'scenario.json');
        if (!existsSync(manifest)) continue;
        try {
          const doc = JSON.parse(readFileSync(manifest, 'utf8'));
          difficulty[doc.id] = doc.difficulty;
        } catch { /* unparseable manifests are the validator's problem */ }
      }
    }
  }

  const diffHeader = scenariosDir ? 'difficulty | ' : '';
  const diffCell = (scenario) => scenariosDir ? String(difficulty[scenario] ?? '—') + ' | ' : '';

  const lines = [];
  lines.push('| Scenario | ' + diffHeader + labels.join(' | ') + ' |');
  lines.push('| --- | ' + (scenariosDir ? '--- | ' : '') + labels.map(() => '---').join(' | ') + ' |');
  for (const scenario of scenarios) {
    const cells = labels.map((l) => {
      const verdicts = (agents[l][scenario] ?? []).filter(Boolean);
      if (verdicts.length === 0) return '—';
      const passes = verdicts.filter((v) => v === 'pass').length;
      const rate = `${passes}/${verdicts.length}`;
      if (verdicts.length === 1) {
        return verdicts[0] === 'pass' ? '✅ pass'
          : verdicts[0] === 'fail-hidden' ? '❌ fail-hidden'
          : verdicts[0] === 'fail-visible' ? '❌ fail-visible'
          : '· ' + verdicts[0];
      }
      if (passes === verdicts.length) return `✅ ${rate}`;
      if (passes === 0) return `❌ ${rate}`;
      return `⚠️ ${rate}`;
    });
    lines.push(`| ${scenario} | ` + diffCell(scenario) + cells.join(' | ') + ' |');
  }
  const totals = labels.map((l) => {
    const vals = Object.values(agents[l]).flat();
    return `${vals.filter((v) => v === 'pass').length}/${vals.length}`;
  });
  lines.push('| **pass total** | ' + (scenariosDir ? ' | ' : '') + totals.join(' | ') + ' |');

  return { agents, markdown: lines.join('\n') };
}
