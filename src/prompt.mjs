/**
 * Build the agent-facing PROMPT.md. Strictly excludes grading fields:
 * intentional_defect, non_obvious_invariant, expected_diagnosis, failure_explanation, artifacts, validation.
 */
export function buildPrompt(doc) {
  const lines = [];
  lines.push('# Engineering task');
  lines.push('');
  lines.push('## Context');
  lines.push('');
  lines.push(doc.business_context.trim());
  lines.push('');
  lines.push('## Request');
  lines.push('');
  lines.push(doc.user_request.trim());
  lines.push('');
  lines.push('## Constraints');
  lines.push('');
  for (const c of doc.safe_fix_constraints) {
    lines.push(`- ${c}`);
  }
  lines.push('- Do not add composer dependencies.');
  lines.push('- The visible test suite in `tests/` is currently green; keep it green. Do not modify `tests/`, `phpunit.xml`, `composer.json`, or `bootstrap/app.php`.');
  lines.push('- Work inside this workspace only; it is a git repository — your change will be captured as a diff.');
  lines.push('');
  lines.push('## Stable surface');
  lines.push('');
  lines.push('Hidden acceptance checks depend only on the surface below. It must keep working exactly as described:');
  lines.push('');
  if (doc.public_surface.routes.length) {
    lines.push(`Routes: ${doc.public_surface.routes.map((r) => `\`${r}\``).join(', ')}`);
  }
  if (doc.public_surface.classes.length) {
    lines.push(`Classes: ${doc.public_surface.classes.map((c) => `\`${c}\``).join(', ')}`);
  }
  if (doc.public_surface.config_keys?.length) {
    lines.push(`Config keys: ${doc.public_surface.config_keys.map((c) => `\`${c}\``).join(', ')}`);
  }
  if (doc.public_surface.tables.length) {
    lines.push(`Database tables: ${doc.public_surface.tables.map((t) => `\`${t}\``).join(', ')}`);
  }
  if (doc.public_surface.artisan_commands?.length) {
    lines.push(`Artisan commands: ${doc.public_surface.artisan_commands.map((c) => `\`${c}\``).join(', ')}`);
  }
  lines.push('');
  lines.push('Deliver the smallest correct change that satisfies the request and the constraints. Explain your diagnosis in your final answer.');
  lines.push('');
  return lines.join('\n');
}
