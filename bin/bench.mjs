#!/usr/bin/env node
import { existsSync, mkdirSync, cpSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { loadConfig, cfgPath } from '../src/config.mjs';
import { discoverScenarios } from '../src/scenarios.mjs';
import { validateStructural, validateDynamic } from '../src/validate.mjs';
import { materializeWorkspace, gitAvailable } from '../src/materialize.mjs';
import { buildPrompt } from '../src/prompt.mjs';
import { runGrade } from '../src/grade.mjs';
import { runAll } from '../src/run-all.mjs';
import { runDiagnose } from '../src/diagnose.mjs';

function die(msg, code = 2) {
  console.error(`bench: ${msg}`);
  process.exit(code);
}

function parseArgs(argv) {
  const args = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) {
      const key = a.slice(2);
      if (key === 'dynamic' || key === 'json' || key === 'keep' || key === 'with-hidden') {
        args[key] = true;
      } else {
        args[key] = argv[++i];
      }
    } else {
      args._.push(a);
    }
  }
  return args;
}

function scenariosOrDie(cfg, id = null) {
  const all = discoverScenarios(cfgPath(cfg, 'scenarios'));
  if (!id) return all;
  const found = all.find((s) => s.id === id);
  if (!found) die(`scenario "${id}" not found (see: bench list)`);
  return [found];
}

/** --cell accepts a cell id ("laravel-13") or a laravel constraint ("^13.0"). */
function cellsOrDie(cfg, idOrConstraint = null) {
  if (!idOrConstraint) return cfg.matrix;
  const cells = cfg.matrix.filter((c) => c.id === idOrConstraint || c.laravel === idOrConstraint);
  if (cells.length === 0) die(`no matrix cell matching "${idOrConstraint}" (see bench.json)`);
  return cells;
}

const cmd = process.argv[2];
const args = parseArgs(process.argv.slice(3));
const cfg = loadConfig();

if (!gitAvailable()) die('git is required for workspace materialization and patching');

const workspacesRoot = path.join(cfg._root, cfg.paths.var, 'workspaces');
mkdirSync(workspacesRoot, { recursive: true });

try {
  if (cmd === 'list') {
    const scenarios = discoverScenarios(cfgPath(cfg, 'scenarios'));
    if (args.json) {
      console.log(JSON.stringify(scenarios.map(({ id, dir, doc }) => ({ id, dir, status: doc?.status ?? 'invalid' })), null, 2));
    } else {
      console.log(`matrix: ${cfg.matrix.map((c) => `${c.id} (laravel ${c.laravel}, php ${c.php})`).join(' | ')}`);
      console.log('');
      for (const s of scenarios) {
        const status = s.doc?.status ?? 'INVALID';
        console.log(`${s.id.padEnd(45)} ${String(s.doc?.difficulty ?? '?').padEnd(4)} ${status.padEnd(10)} [${s.familyDir}]`);
      }
    }
    process.exit(0);
  }

  if (cmd === 'validate') {
    const scenarios = scenariosOrDie(cfg, args.scenario);
    const cells = cellsOrDie(cfg, args.cell);
    let anyFail = false;
    const report = [];

    for (const s of scenarios) {
      if (!args.dynamic) {
        const structural = validateStructural(cfg, s);
        report.push({ scenario: s.id, structural: structural.ok, errors: structural.errors, warnings: structural.warnings });
        if (!structural.ok) anyFail = true;
        if (!args.json) {
          console.log(`${structural.ok ? 'PASS' : 'FAIL'}  ${s.id} (structural)`);
          for (const e of structural.errors) console.log(`      error:   ${e}`);
          for (const w of structural.warnings) console.log(`      warning: ${w}`);
        }
        continue;
      }

      for (const cell of cells) {
        if (!s.doc?.laravel?.includes(cell.laravel)) continue;
        const dyn = validateDynamic(cfg, s, cell, { workspacesRoot, keep: args.keep });
        report.push(dyn);
        if (!dyn.ok) anyFail = true;
        if (!args.json) {
          console.log(`${dyn.ok ? 'PASS' : 'FAIL'}  ${s.id} @ ${cell.id} (dynamic gate: ${dyn.steps.filter((x) => x.pass).length}/${dyn.steps.length} steps green)`);
          for (const st of dyn.steps) {
            console.log(`      ${st.pass ? 'ok  ' : 'FAIL'}  ${st.name.padEnd(40)} ${st.pass ? '' : `exit=${st.code} ${st.output?.split('\n')[0] ?? ''}`}`);
          }
          if (dyn.fatal) console.log(`      fatal: ${dyn.fatal}`);
        }
      }
    }

    if (args.json) console.log(JSON.stringify(report, null, 2));
    process.exit(anyFail ? 1 : 0);
  }

  if (cmd === 'materialize') {
    if (!args.scenario || !args.out) die('usage: bench materialize --scenario <id> --out <dir> [--with-hidden] [--cell <id-or-constraint>]');
    const [s] = scenariosOrDie(cfg, args.scenario);
    const [cell] = cellsOrDie(cfg, args.cell);
    const vendorDir = path.join(cfg._root, cfg.paths.var, 'vendor', cell.id);
    const out = path.resolve(args.out);
    materializeWorkspace({
      baseDir: path.join(cfg._root, cfg.paths.base),
      scenarioDir: s.dir,
      cell,
      outDir: out,
      withHidden: !!args['with-hidden'],
      vendorDir: existsSync(path.join(vendorDir, 'autoload.php')) ? vendorDir : null,
      protectedPaths: cfg.grader?.protectedPaths,
    });
    writeFileSync(path.join(out, 'PROMPT.md'), buildPrompt(s.doc));
    console.log(`materialized ${s.id} @ ${cell.id} -> ${out}`);
    process.exit(0);
  }

  if (cmd === 'prompt') {
    if (!args.scenario) die('usage: bench prompt --scenario <id>');
    const [s] = scenariosOrDie(cfg, args.scenario);
    process.stdout.write(buildPrompt(s.doc));
    process.exit(0);
  }

  if (cmd === 'run') {
    if (!args.scenario || (!args.agent && !args.patch)) {
      die('usage: bench run --scenario <id> (--agent "<cmd with {{workspace}} {{prompt}}>" | --patch <file>) [--cell <id-or-constraint>] [--keep]');
    }
    const scenarios = scenariosOrDie(cfg, args.scenario);
    const cells = cellsOrDie(cfg, args.cell);
    const results = [];
    let exitCode = 0;
    for (const s of scenarios) {
      for (const cell of cells) {
        if (s.doc && !s.doc.laravel.includes(cell.laravel)) continue;
        const r = runGrade({
          cfg,
          scenario: s,
          cell,
          workspacesRoot,
          agentCmd: args.agent ?? null,
          patchFile: args.patch ?? null,
          keep: args.keep,
        });
        results.push(r);
        console.log(`${r.verdict.toUpperCase().padEnd(18)} ${r.scenario} @ ${r.cell}`);
        if (r.verdict === 'fail-visible' || r.verdict === 'fail-hidden') {
          const out = (r.visible?.code !== 0 ? r.visible?.output : r.hidden?.output) ?? '';
          for (const line of out.split('\n').filter(Boolean).slice(-15)) console.log(`      ${line}`);
        }
        if (r.verdict !== 'pass') exitCode = 1;
      }
    }
    if (args.json) console.log(JSON.stringify(results, null, 2));
    if (args.out) {
      writeFileSync(path.resolve(args.out), JSON.stringify(results, null, 2));
    }
    process.exit(exitCode);
  }

  if (cmd === 'run-all') {
    if (!args.agent) {
      die('usage: bench run-all --agent "<cmd with {{workspace}} {{prompt}}>" [--cell <id-or-constraint>] [--out <dir>] [--scenario <id>]');
    }
    const cells = cellsOrDie(cfg, args.cell);
    const outDir = args.out ? path.resolve(args.out) : path.join(cfg._root, cfg.paths.var, 'agent-runs');
    const results = [];
    for (const cell of cells) {
      const { results: cellResults, passed } = runAll({
        cfg,
        scenariosDir: cfgPath(cfg, 'scenarios'),
        agentCmd: args.agent,
        cell,
        workspacesRoot,
        outDir,
        keep: args.keep,
        only: args.scenario ?? null,
      });
      console.log(`\ncell ${cell.id}: ${passed}/${cellResults.length} scenarios passed`);
      results.push(...cellResults);
    }
    if (args.json) console.log(JSON.stringify(results, null, 2));
    process.exit(results.every((r) => r.verdict === 'pass') ? 0 : 1);
  }

  if (cmd === 'diagnose') {
    if (!args.scenario || !args.agent) {
      die('usage: bench diagnose --scenario <id> --agent "<cmd with {{workspace}} {{prompt}}>" [--cell <id-or-constraint>] [--keep]');
    }
    const scenarios = scenariosOrDie(cfg, args.scenario);
    const cells = cellsOrDie(cfg, args.cell);
    const results = [];
    for (const s of scenarios) {
      for (const cell of cells) {
        if (s.doc && !s.doc.laravel.includes(cell.laravel)) continue;
        const r = runDiagnose({ cfg, scenario: s, cell, workspacesRoot, agentCmd: args.agent, keep: args.keep });
        results.push(r);
        console.log(`${r.verdict.toUpperCase().padEnd(16)} ${r.scenario} @ ${r.cell}  matched: [${r.matched.join(', ')}]`);
      }
    }
    if (args.json) console.log(JSON.stringify(results, null, 2));
    if (args.out) {
      writeFileSync(path.resolve(args.out), JSON.stringify(results, null, 2));
    }
    process.exit(results.every((r) => r.verdict === 'diagnosis-pass') ? 0 : 1);
  }

  if (cmd === 'vendor:prepare') {
    const cells = cellsOrDie(cfg, args.cell);
    for (const cell of cells) {
      const scratch = path.join(cfg._root, cfg.paths.var, 'scratch', cell.id);
      try {
        materializeWorkspace({
          baseDir: path.join(cfg._root, cfg.paths.base),
          scenarioDir: null,
          cell,
          outDir: scratch,
          withHidden: false,
          vendorDir: null,
          protectedPaths: cfg.grader?.protectedPaths,
        });
        const composerBin = process.env.BENCH_COMPOSER || 'composer';
        const res = spawnSync(`${composerBin} install --no-interaction --prefer-dist --no-progress`, {
          cwd: scratch,
          shell: true,
          encoding: 'utf8',
          maxBuffer: 64 * 1024 * 1024,
        });
        if (res.status !== 0) {
          console.error(`bench: composer install failed for ${cell.id}:\n${(res.stderr || res.stdout || '').slice(-2000)}`);
          process.exit(1);
        }
        const dest = path.join(cfg._root, cfg.paths.var, 'vendor', cell.id);
        rmSync(dest, { recursive: true, force: true });
        cpSync(path.join(scratch, 'vendor'), dest, { recursive: true });
        console.log(`vendor cache ready: ${dest}`);
      } finally {
        if (existsSync(scratch)) rmSync(scratch, { recursive: true, force: true });
      }
    }
    process.exit(0);
  }

  die(`unknown command "${cmd ?? ''}" (try: list | validate | materialize | prompt | run | vendor:prepare)`);
} catch (e) {
  die(String(e?.stack ?? e), 1);
}
