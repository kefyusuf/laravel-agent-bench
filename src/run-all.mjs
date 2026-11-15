import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { discoverScenarios } from './scenarios.mjs';
import { runGrade, resolveVendorDir } from './grade.mjs';

/**
 * Run one agent command against every scenario on one matrix cell and aggregate.
 * With runs > 1, each scenario is graded N times (statistical protocol: pass@1
 * rate per scenario). Per-run JSON files are written as <scenario>.run<k>.json
 * plus an aggregate <scenario>.json holding all verdicts.
 */
export function runAll({ cfg, scenariosDir, agentCmd, cell, workspacesRoot, outDir, keep = false, only = null, runs = 1, model = null }) {
  const all = discoverScenarios(scenariosDir);
  const scenarios = only ? all.filter((s) => s.id === only) : all;
  mkdirSync(outDir, { recursive: true });

  const results = [];
  for (const scenario of scenarios) {
    if (scenario.doc && !scenario.doc.laravel.includes(cell.laravel)) continue;

    const verdicts = [];
    for (let k = 1; k <= Math.max(1, runs); k++) {
      process.stdout.write(`running ${scenario.id} (run ${k}/${Math.max(1, runs)}) ...\n`);
      const r = runGrade({
        cfg,
        scenario,
        cell,
        workspacesRoot,
        agentCmd,
        keep,
      });
      r.run = k;
      verdicts.push(r);
      results.push(r);
      writeFileSync(path.join(outDir, `${scenario.id}.run${k}.json`), JSON.stringify(r, null, 2));
    }

    writeFileSync(path.join(outDir, `${scenario.id}.json`), JSON.stringify({
      scenario: scenario.id,
      cell: cell.id,
      model: model || 'agent default',
      runs,
      verdicts: verdicts.map((r) => ({ run: r.run, verdict: r.verdict })),
      passRate: `${verdicts.filter((r) => r.verdict === 'pass').length}/${runs}`,
    }, null, 2));

    const passedRuns = verdicts.filter((r) => r.verdict === 'pass').length;
    process.stdout.write(`${passedRuns}/${Math.max(1, runs)} pass ${scenario.id}\n`);
  }

  const passed = results.filter((r) => r.verdict === 'pass').length;
  return { results, passed };
}
