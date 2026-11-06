import path from 'node:path';

/**
 * Pragmatic validation of a parsed scenario.json, mirroring schema/scenario.schema.json.
 * Returns a list of human-readable error strings; empty list = valid.
 */
export function validateScenarioDocument(doc, { dirName, knownCells }) {
  const errors = [];
  const fail = (msg) => errors.push(msg);

  const req = (cond, msg) => {
    if (!cond) fail(msg);
  };

  req(doc && typeof doc === 'object' && !Array.isArray(doc), 'document must be a JSON object');
  if (!doc || typeof doc !== 'object' || Array.isArray(doc)) return errors;

  const required = [
    'schema', 'id', 'family', 'title', 'difficulty', 'status', 'laravel', 'requires',
    'business_context', 'user_request', 'intentional_defect', 'non_obvious_invariant',
    'expected_diagnosis', 'safe_fix_constraints', 'failure_explanation', 'public_surface', 'artifacts',
  ];
  for (const key of required) {
    req(doc[key] !== undefined, `missing required field "${key}"`);
  }

  const allowed = new Set(required);
  for (const key of Object.keys(doc)) {
    req(allowed.has(key) || key === 'validation', `unknown field "${key}"`);
  }

  if (doc.schema !== undefined) req(doc.schema === 1, '"schema" must be 1');
  if (doc.id !== undefined) {
    req(/^[a-z0-9]+(-[a-z0-9]+)*$/.test(doc.id), '"id" must be kebab-case');
    req(doc.id === dirName, `"id" ("${doc.id}") must equal directory name ("${dirName}")`);
  }
  if (doc.family !== undefined) {
    req(/^[a-z0-9-]+(\/[a-z0-9-]+)?$/.test(doc.family), '"family" must look like "area/name"');
  }
  if (doc.title !== undefined) {
    req(typeof doc.title === 'string' && doc.title.length >= 10 && doc.title.length <= 120,
      '"title" must be a string of 10..120 chars');
  }
  if (doc.difficulty !== undefined) {
    req(Number.isInteger(doc.difficulty) && doc.difficulty >= 1 && doc.difficulty <= 5,
      '"difficulty" must be an integer 1..5');
  }
  if (doc.status !== undefined) req(doc.status === 'draft' || doc.status === 'validated', '"status" must be draft|validated');
  if (doc.laravel !== undefined) {
    req(Array.isArray(doc.laravel) && doc.laravel.length >= 1, '"laravel" must be a non-empty array');
    if (Array.isArray(doc.laravel)) {
      for (const v of doc.laravel) {
        req(knownCells.includes(v), `"laravel" entry "${v}" is not a bench.json matrix cell`);
      }
    }
  }
  if (doc.requires !== undefined) {
    const ok = ['sqlite', 'mysql', 'postgres', 'redis', 'redis-cluster', 'network'];
    req(Array.isArray(doc.requires) && doc.requires.every((r) => ok.includes(r)),
      `"requires" entries must be one of ${ok.join(', ')}`);
  }

  const strMin = (v, name, min) => req(typeof v === 'string' && v.length >= min, `"${name}" must be a string of at least ${min} chars`);
  strMin(doc.business_context, 'business_context', 80);
  strMin(doc.user_request, 'user_request', 20);
  strMin(doc.non_obvious_invariant, 'non_obvious_invariant', 40);
  strMin(doc.expected_diagnosis, 'expected_diagnosis', 40);
  strMin(doc.failure_explanation, 'failure_explanation', 80);

  if (doc.intentional_defect !== undefined) {
    const d = doc.intentional_defect;
    req(d && typeof d === 'object' && !Array.isArray(d), '"intentional_defect" must be an object');
    if (d && typeof d === 'object') {
      for (const k of ['summary', 'location', 'root_cause', 'why_it_looks_correct']) {
        req(d[k] !== undefined, `intentional_defect.${k} is required`);
      }
      req(Array.isArray(d.location) && d.location.length >= 1 && d.location.every((x) => typeof x === 'string'),
        'intentional_defect.location must be a non-empty array of strings');
    }
  }

  if (doc.safe_fix_constraints !== undefined) {
    req(Array.isArray(doc.safe_fix_constraints) && doc.safe_fix_constraints.length >= 2
      && doc.safe_fix_constraints.every((s) => typeof s === 'string'),
    '"safe_fix_constraints" must be an array of at least 2 strings');
  }

  if (doc.public_surface !== undefined) {
    const p = doc.public_surface;
    req(p && typeof p === 'object', '"public_surface" must be an object');
    if (p && typeof p === 'object') {
      for (const k of ['routes', 'classes', 'tables']) {
        req(Array.isArray(p[k]), `public_surface.${k} must be an array`);
      }
      for (const k of ['config_keys', 'artisan_commands']) {
        if (p[k] !== undefined) req(Array.isArray(p[k]), `public_surface.${k} must be an array`);
      }
    }
  }

  if (doc.artifacts !== undefined) {
    const a = doc.artifacts;
    req(a && typeof a === 'object', '"artifacts" must be an object');
    if (a && typeof a === 'object') {
      req(typeof a.solution === 'string' && a.solution.length > 0, 'artifacts.solution is required');
      if (a.distractors !== undefined) {
        req(Array.isArray(a.distractors) && a.distractors.length >= 1
          && a.distractors.every((s) => typeof s === 'string'),
        'artifacts.distractors must be an array of at least 1 path string');
      }
    }
  }

  if (doc.validation !== undefined) {
    const v = doc.validation;
    req(v && typeof v === 'object', '"validation" must be an object');
  }

  return errors;
}

/** Map a PHP FQCN in App\ or Database\ namespace to its expected workspace path. */
export function classToWorkspacePath(fqcn) {
  if (fqcn.startsWith('App\\')) {
    return path.join('app', ...fqcn.slice(4).split('\\')) + '.php';
  }
  if (fqcn.startsWith('Database\\Factories\\')) {
    return path.join('database', 'factories', ...fqcn.slice(18).split('\\')) + '.php';
  }
  return null;
}
