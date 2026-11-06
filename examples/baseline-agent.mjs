#!/usr/bin/env node
/**
 * Baseline agent: a deterministic, non-LLM "pattern-matching engineer" that
 * knows only the most common Laravel gotchas. It exists to
 *   (a) exercise the full `bench run --agent` contract (spawn, PROMPT delivery,
 *       diff extraction, tamper check, grading) end to end, and
 *   (b) establish a reproducible baseline score showing that the benchmark is
 *       not solvable by surface-level pattern matching.
 *
 * Knowledge (deliberately narrow):
 *   R1  dispatch inside DB::transaction without ->afterCommit()
 *   R2  unchecked Http response consumed with ->json()/->body()
 *
 * Anything else it "sees" it leaves alone. Usage:
 *   node examples/baseline-agent.mjs <workspace> <prompt-file>
 * Exits 0 whether or not it changed anything (no-change is a valid outcome).
 */
import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const [workspace, promptFile] = process.argv.slice(2);
if (!workspace || !promptFile) {
  console.error('usage: baseline-agent.mjs <workspace> <prompt-file>');
  process.exit(2);
}

const prompt = readFileSync(promptFile, 'utf8');
console.log(`[baseline-agent] workspace: ${workspace}`);
console.log(`[baseline-agent] prompt: ${prompt.split('\n').length} lines (read for context; rules are fixed)`);

function* phpFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const p = path.join(dir, entry);
    if (statSync(p).isDirectory()) {
      if (entry === 'vendor' || entry === 'node_modules' || entry === '.git' || entry === '.bench') continue;
      yield* phpFiles(p);
    } else if (entry.endsWith('.php')) {
      yield p;
    }
  }
}

let changes = 0;

for (const file of phpFiles(workspace)) {
  const rel = path.relative(workspace, file).replace(/\\/g, '/');
  let src = readFileSync(file, 'utf8');
  const before = src;

  // R1: dispatch without afterCommit inside a transactional controller/job.
  if (/DB::transaction\(/.test(src)) {
    src = src.replace(/^(\s*)(\b[\w\\]+::dispatch\([^;]*\));$/gm, (match, indent, call) => {
      return call.includes('->afterCommit()') || call.includes('dispatchAfterResponse')
        ? match
        : `${indent}${call}->afterCommit();`;
    });
  }

  // R2: HTTP responses whose body is read without a status check.
  const responseVars = [...src.matchAll(/\$(\w+)\s*=\s*Http::/g)].map((m) => `$${m[1]}`);
  for (const v of responseVars) {
    // escape $ so the variable interpolates as a literal, not as an end-of-line anchor
    const ev = v.replace(/\$/g, '\\$');
    const consumed = new RegExp(`(?:^|\\n)(\\s*)(?:${ev}\\s*=\\s*)?\\$?\\w+\\s*=\\s*${ev}->(?:json|body|object)\\(`);
    if (!src.includes(`${v}->throw()`) && !src.includes(`${v}->successful()`) && consumed.test(src)) {
      // insert ->throw() immediately after the assignment statement
      const assign = new RegExp(`(\\s*${ev}\\s*=\\s*Http::[\\s\\S]*?\\)(?:\\s*\\([^)]*\\))*;)`);
      const m = src.match(assign);
      if (m) {
        src = src.replace(m[0], `${m[0]}\n${m[1].match(/^\s*/)[0]}${v}->throw();`);
      }
    }
  }

  if (src !== before) {
    writeFileSync(file, src);
    changes++;
    console.log(`[baseline-agent] edited ${rel}`);
  }
}

console.log(`[baseline-agent] done — ${changes} file(s) changed`);
process.exit(0);
