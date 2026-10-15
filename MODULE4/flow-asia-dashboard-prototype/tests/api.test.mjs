import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { request } from '../js/core/api.js';

beforeEach(() => {
  globalThis.document = { querySelector: () => ({ content: 'test-csrf-token' }) };
  delete globalThis.window;
});

test('writes include the session, CSRF token and JSON body', async () => {
  let sent;
  globalThis.fetch = async (path, options) => {
    sent = { path, options };
    return { status: 201, ok: true, json: async () => ({ data: { id: 7 } }) };
  };
  const result = await request('/hr/events', { method: 'POST', body: { title: 'Town hall' } });
  assert.equal(result.data.id, 7);
  assert.equal(sent.options.credentials, 'same-origin');
  assert.equal(sent.options.cache, 'no-store');
  assert.equal(sent.options.headers['X-CSRF-TOKEN'], 'test-csrf-token');
  assert.deepEqual(JSON.parse(sent.options.body), { title: 'Town hall' });
});

test('successful event deletion accepts empty responses', async () => {
  globalThis.fetch = async () => ({ status: 204, ok: true });
  assert.equal(await request('/hr/events/7', { method: 'DELETE' }), null);
});

test('validation messages reach the caller', async () => {
  globalThis.fetch = async () => ({ status: 422, ok: false, json: async () => ({ errors: { ends_on: ['End date must follow start date.'] } }) });
  await assert.rejects(request('/hr/events'), /End date must follow start date/);
});

test('expired sessions and unexpected HTML cannot appear as successful data', async () => {
  globalThis.fetch = async () => ({ status: 419, ok: false, json: async () => ({}) });
  await assert.rejects(request('/hr/events'), /session has expired/);
  globalThis.fetch = async () => ({ status: 200, ok: true, json: async () => { throw new SyntaxError(); } });
  await assert.rejects(request('/hr/events'), /unexpected response/);
});

test('search cancellation is passed to fetch', async () => {
  const controller = new AbortController();
  globalThis.fetch = async (path, options) => {
    assert.equal(options.signal, controller.signal);
    throw new DOMException('Aborted', 'AbortError');
  };
  await assert.rejects(request('/hr/employees/search?q=Demo', { signal: controller.signal }), { name: 'AbortError' });
});

test('PDF downloads return binary data and keep authorization errors readable', async () => {
  const pdf = new Blob(['%PDF-test'], { type: 'application/pdf' });
  globalThis.fetch = async () => ({ status: 200, ok: true, headers: new Headers({ 'Content-Type': 'application/pdf' }), blob: async () => pdf });
  assert.equal(await request('/calendar/employees/2/pdf?month=2026-10', { responseType: 'blob' }), pdf);
  globalThis.fetch = async () => ({ status: 403, ok: false, json: async () => ({ message: 'This calendar is not permitted.' }) });
  await assert.rejects(request('/calendar/employees/2/pdf', { responseType: 'blob' }), /not permitted/);
});

test('HTML cannot be downloaded as a PDF', async () => {
  globalThis.fetch = async () => ({ status: 200, ok: true, headers: new Headers({ 'Content-Type': 'text/html' }) });
  await assert.rejects(request('/calendar/employees/2/pdf', { responseType: 'blob' }), /did not return a PDF/);
});

test('API calls retain the server application path when hosted in a subdirectory', async () => {
  globalThis.window = { flowAsiaBackend: { baseUrl: 'http://localhost/flow-asia/public/' } };
  let requestedUrl;
  globalThis.fetch = async url => {
    requestedUrl = url;
    return { status: 200, ok: true, json: async () => ({ data: [] }) };
  };
  await request('/hr/employees/search?q=Creative');
  assert.equal(requestedUrl, 'http://localhost/flow-asia/public/hr/employees/search?q=Creative');
});

test('HTML session failures still explain that authentication must be renewed', async () => {
  for (const status of [401, 419]) {
    globalThis.fetch = async () => ({ status, ok: false, json: async () => { throw new SyntaxError(); } });
    await assert.rejects(request('/hr/events'), /session has expired/);
  }
});

test('network and server failures provide retry instructions without exposing server diagnostics', async () => {
  globalThis.fetch = async () => { throw new TypeError('Failed to fetch'); };
  await assert.rejects(request('/hr/events'), /Check your connection and retry/);
  for (const status of [500, 503]) {
    globalThis.fetch = async () => ({ status, ok: false, json: async () => ({ message: 'SQLSTATE private database details' }) });
    await assert.rejects(request('/hr/events'), error => /Please retry/.test(error.message) && !error.message.includes('SQLSTATE'));
  }
});
