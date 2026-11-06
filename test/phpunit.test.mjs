import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseSummary } from '../src/phpunit.mjs';

test('parses ANSI-colored failing summary', () => {
  const s = parseSummary({
    code: 1,
    stdout: '\u001b[37;41mTests: 6\u001b[0m\u001b[37;41m, Assertions: 22\u001b[0m\u001b[37;41m, Failures: 1\u001b[0m.\n',
    stderr: '',
  });
  assert.equal(s.pass, false);
  assert.equal(s.tests, 6);
  assert.equal(s.assertions, 22);
  assert.equal(s.failureCount, 1);
});

test('parses OK summary', () => {
  const s = parseSummary({ code: 0, stdout: 'Time: 00:01\nOK (12 tests, 40 assertions)\n', stderr: '' });
  assert.equal(s.pass, true);
  assert.equal(s.tests, 12);
  assert.equal(s.assertions, 40);
});

test('parses failing summary', () => {
  const s = parseSummary({ code: 1, stdout: 'Tests: 3, Assertions: 5, Failures: 2.\n', stderr: '' });
  assert.equal(s.pass, false);
  assert.equal(s.tests, 3);
  assert.equal(s.assertions, 5);
  assert.equal(s.failureKind, 'Failures');
  assert.equal(s.failureCount, 2);
});

test('parses errors variant', () => {
  const s = parseSummary({ code: 1, stdout: '', stderr: 'Tests: 2, Assertions: 1, Errors: 2.\n' });
  assert.equal(s.pass, false);
  assert.equal(s.failureKind, 'Errors');
});

test('unparseable output falls back to exit code', () => {
  const s = parseSummary({ code: 1, stdout: 'segfault', stderr: '' });
  assert.equal(s.pass, false);
  assert.equal(s.unparsed, true);
});
