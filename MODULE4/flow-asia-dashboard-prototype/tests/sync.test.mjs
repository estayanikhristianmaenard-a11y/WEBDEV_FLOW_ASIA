import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installCalendarSync } from '../js/modules/m4-sync.js';

function setup(t, overrides = {}) {
  t.mock.timers.enable({ apis: ['setTimeout'] });
  const events = new EventTarget(), visibility = new EventTarget();
  const state = { visible: true, blocked: false, reads: 0, errors: [] };
  const dispose = installCalendarSync({
    refresh: async () => { state.reads++; },
    isBlocked: () => state.blocked,
    onError: error => state.errors.push(error.message),
    eventTarget: events, visibilityTarget: visibility,
    isVisible: () => state.visible,
    ...overrides,
  });
  t.after(dispose);
  return { events, visibility, state, dispose };
}

test('host update and focus signals coalesce into one refresh with no record payload', async t => {
  let received;
  const { events } = setup(t, { refresh: async (...args) => { received = args; } });
  events.dispatchEvent(new Event('flowasia:calendar-updated'));
  events.dispatchEvent(new Event('focus'));
  t.mock.timers.tick(250);
  await Promise.resolve();
  assert.deepEqual(received, []);
});

test('hidden tabs wait for visibility and open forms wait until safe', async t => {
  const { events, visibility, state } = setup(t);
  state.visible = false;
  events.dispatchEvent(new Event('flowasia:calendar-updated'));
  t.mock.timers.tick(1000);
  assert.equal(state.reads, 0);
  state.visible = true; state.blocked = true;
  visibility.dispatchEvent(new Event('visibilitychange'));
  t.mock.timers.tick(250);
  assert.equal(state.reads, 0);
  state.blocked = false;
  t.mock.timers.tick(250);
  await Promise.resolve();
  assert.equal(state.reads, 1);
});

test('updates during an active read wait and cause one further fresh read', async t => {
  let finish, reads = 0;
  const { events } = setup(t, { refresh: () => {
    reads++;
    return new Promise(resolve => { finish = resolve; });
  } });
  events.dispatchEvent(new Event('focus')); t.mock.timers.tick(250);
  assert.equal(reads, 1);
  events.dispatchEvent(new Event('flowasia:calendar-updated'));
  events.dispatchEvent(new Event('flowasia:calendar-updated'));
  t.mock.timers.tick(250);
  assert.equal(reads, 1);
  finish(); await Promise.resolve();
  t.mock.timers.tick(250);
  assert.equal(reads, 2);
  finish(); await Promise.resolve();
});

test('refresh failures reach the existing error state without an automatic retry loop', async t => {
  let reads = 0;
  const { events, state } = setup(t, { refresh: async () => { reads++; throw new Error('Dependency unavailable'); } });
  events.dispatchEvent(new Event('focus')); t.mock.timers.tick(250);
  await Promise.resolve(); await Promise.resolve();
  t.mock.timers.tick(2000);
  assert.deepEqual(state.errors, ['Dependency unavailable']);
  assert.equal(reads, 1);
});

test('disposing a module removes its pending refresh and event handlers', async t => {
  const { events, state, dispose } = setup(t);
  events.dispatchEvent(new Event('focus')); dispose();
  events.dispatchEvent(new Event('flowasia:calendar-updated'));
  t.mock.timers.tick(1000); await Promise.resolve();
  assert.equal(state.reads, 0);
});
