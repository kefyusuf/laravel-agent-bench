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
export function buildScoreTable(runsDir) {
  if (!existsSync(runsDir)) {
    return { agents: {}, markdown: '_no agent-run evidence found in ' + runsDir + '_' };
  }

  const agents = {};

  for (const file of readdirSync(runsDir).sort()) {
    const match = file.match(/^agent-run-([a-z0-9-]+)\.json$/);
    if (!match) continue;
    const agentLabel = match[1].split('-')[0];

    const parsed = JSON.parse(readFileSync(path.join(runsDir, file), 'utf8'));

    const runs = [];
    if (Array.isArray(parsed)) {
      runs.push(...parsed.map((r) => ({ scenario: r.scenario, verdict: r.verdict })));
    } else if (parsed && typeof parsed === 'object' && Array.isArray(parsed.summary)) {
      runs.push(...parsed.summary.map((r) => ({ scenario: r.scenario, verdict: r.verdict })));
    } else if (parsed && typeof parsed === 'object' && parsed.scenario && parsed.verdict) {
      runs.push({ scenario: parsed.scenario, verdict: parsed.verdict });
    } else if (parsed && typeof parsed === 'object' && parsed.scenario && Array.isArray(parsed.verdicts)) {
      for (const v of parsed.verdicts) {
        runs.push({ scenario: parsed.scenario, verdict: v.verdict });
      }
    }

    agents[agentLabel] ??= {};
    for (const run of runs) {
      (agents[agentLabel][run.scenario] ??= []).push(run.verdict);
    }
  }

  const labels = Object.keys(agents).sort();
  if (labels.length === 0) {
    return { agents: {}, markdown: '_no agent evidence found in ' + runsDir + '_' };
  }
  const scenarios = [...new Set(labels.flatMap((l) => Object.keys(agents[l])))].sort();

  const lines = [];
  lines.push('| Scenario | ' + labels.join(' | ') + ' |');
  lines.push('| --- | ' + labels.map(() => '---').join(' | ') + ' |');
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
    lines.push(`| ${scenario} | ` + cells.join(' | ') + ' |');
  }
  const totals = labels.map((l) => {
    const vals = Object.values(agents[l]).flat();
    return `${vals.filter((v) => v === 'pass').length}/${vals.length}`;
  });
  lines.push('| **pass total** | ' + totals.join(' | ') + ' |');

  return { agents, markdown: lines.join('\n') };
}
