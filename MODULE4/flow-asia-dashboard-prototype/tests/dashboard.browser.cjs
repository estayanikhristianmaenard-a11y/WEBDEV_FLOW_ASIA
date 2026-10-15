const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const baseUrl = process.env.M4_BASE_URL || 'http://127.0.0.1:8000';
const eventTitle = `Integration check ${Date.now()}`;

async function loaded(page) {
  await page.waitForFunction(() => document.querySelector('#refresh-data-btn')?.disabled === false);
}

(async () => {
  const browser = await chromium.launch({
    ...(process.env.M4_BROWSER_PATH ? { executablePath: process.env.M4_BROWSER_PATH } : {}),
    headless: true,
  });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const errors = [];
  const createdIds = new Set();
  page.on('pageerror', error => errors.push(error.message));
  await context.route('https://**/*', route => route.abort());
  try {
    await page.goto(`${baseUrl}/m4`);
    await loaded(page);
    assert.equal(await page.evaluate(() => window.flowAsiaBackend.user.role), 'hr_admin');
    assert.equal(await page.locator('#team-calendar tbody tr').count(), 40);
    const month = '2026-10';
    await page.locator('#employee-calendar-month').fill(month);
    await loaded(page);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');

    await page.getByRole('button', { name: 'Create event', exact: true }).click();
    await page.locator('[name="title"]').fill(eventTitle);
    await page.locator('[name="starts_on"]').fill(`${month}-05`);
    await page.locator('[name="ends_on"]').fill(`${month}-04`);
    await page.getByRole('button', { name: 'Save event', exact: true }).click();
    await page.waitForFunction(() => document.querySelector('form [role="alert"]')?.textContent.length > 0);
    assert.match(await page.locator('form [role="alert"]').innerText(), /end.*start/i);
    await page.locator('[name="ends_on"]').fill(`${month}-06`);

    let posts = 0;
    let release;
    const held = new Promise(resolve => { release = resolve; });
    await page.route('**/hr/events', async route => {
      if (route.request().method() === 'POST') { posts++; await held; }
      await route.continue();
    });
    const saved = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/hr/events'));
    await page.getByRole('button', { name: 'Save event', exact: true }).click();
    await page.locator('form').evaluate(form => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    release();
    const savedResponse = await saved;
    assert.equal(savedResponse.status(), 201);
    const eventId = (await savedResponse.json()).data.id;
    createdIds.add(eventId);
    await loaded(page);
    assert.equal(posts, 1, 'Duplicate form submissions must create only one event');
    await page.unroute('**/hr/events');

    const eventItem = () => page.locator('#company-event-list .req-item').filter({ hasText: eventTitle });
    assert.equal(await eventItem().count(), 1);
    await page.reload(); await loaded(page);
    assert.equal(await eventItem().count(), 1, 'Created events must survive a reload');
    assert.equal(await page.locator('#staff-calendar-grid .company-event-marker').filter({ hasText: eventTitle }).count(), 2);
    assert.ok(await page.locator('#team-calendar thead .company-event-marker').count() >= 2);

    await eventItem().getByRole('button', { name: 'Edit', exact: true }).click();
    await page.locator('[name="title"]').fill(`${eventTitle} updated`);
    await page.locator('[name="ends_on"]').fill(`${month}-07`);
    await page.getByRole('button', { name: 'Save event', exact: true }).click();
    await page.waitForSelector('form', { state: 'detached' }); await loaded(page);
    await page.reload(); await loaded(page);
    assert.match(await eventItem().innerText(), /updated/);
    assert.equal(await page.locator('#staff-calendar-grid .company-event-marker').filter({ hasText: eventTitle }).count(), 3);

    await page.locator('#hr-search-input').fill('EMP-002');
    await page.locator('.employee-search-option').first().click();
    await page.waitForFunction(() => document.querySelector('#calendar-employee-meta').textContent.includes('EMP-002') && document.querySelector('#staff-calendar-grid').getAttribute('aria-busy') === 'false');
    await page.locator('#staff-calendar-grid button[aria-label^="2026-10-13:"]').click();
    assert.match(await page.locator('#modal-status').innerText(), /Invalid/);
    assert.match(await page.locator('#modal-cr-box').innerText(), /could not be verified/);
    await page.locator('#modal-dismiss-btn').click();
    await page.locator('#staff-calendar-grid button[aria-label^="2026-10-10:"]').click();
    const reportPagePromise = context.waitForEvent('page');
    await page.getByRole('link', { name: 'Open progress report' }).click();
    const reportPage = await reportPagePromise;
    await reportPage.waitForLoadState();
    assert.match(await reportPage.locator('.report-body').innerText(), /Demo progress report/);
    await reportPage.close(); await page.locator('#modal-dismiss-btn').click();
    const downloadPromise = page.waitForEvent('download');
    await page.locator('#export-pdf-btn').click();
    const download = await downloadPromise;
    const stream = await download.createReadStream();
    const chunks = [];
    for await (const chunk of stream) chunks.push(chunk);
    assert.equal(Buffer.concat(chunks).subarray(0, 5).toString(), '%PDF-');

    await page.route('**/calendar/employees/*/month?*', route => route.fulfill({ status: 503, contentType: 'text/html', body: 'Unavailable' }));
    await page.locator('#refresh-data-btn').click(); await loaded(page);
    assert.match(await page.locator('#backend-status').innerText(), /Please retry/);
    assert.equal(await eventItem().count(), 1, 'Events must remain available when employee data fails');
    assert.equal(await page.locator('#staff-calendar-grid .company-event-marker').filter({ hasText: eventTitle }).count(), 3);
    assert.equal(await page.locator('#kpi-present').innerText(), '—');
    await page.locator('#staff-calendar-grid button[aria-label^="2026-10-05:"]').click();
    assert.equal(await page.locator('#modal-status').innerText(), 'Employee calendar unavailable');
    await page.locator('#modal-dismiss-btn').click();
    await page.unroute('**/calendar/employees/*/month?*');
    await page.locator('#refresh-data-btn').click(); await loaded(page);
    assert.match(await page.locator('#backend-status').innerText(), /Calendar loaded/);

    await page.route('**/calendar/events?*', route => route.fulfill({ status: 503, contentType: 'text/html', body: 'Unavailable' }));
    await page.locator('#refresh-data-btn').click(); await loaded(page);
    assert.match(await page.locator('#company-event-list').innerText(), /events unavailable/);
    await page.unroute('**/calendar/events?*');
    await page.locator('#refresh-data-btn').click(); await loaded(page);

    await page.route(`${baseUrl}/m4`, async route => {
      const response = await route.fetch();
      const html = (await response.text()).replace(/"employee_id":\d+/, '"employee_id":null');
      await route.fulfill({ response, body: html });
    });
    await page.reload(); await loaded(page);
    assert.match(await page.locator('#backend-status').innerText(), /Select an employee/);
    assert.equal(await page.locator('#staff-calendar-grid .company-event-marker').filter({ hasText: eventTitle }).count(), 3, 'Unlinked HR accounts must still see company events on calendar days');
    await page.unroute(`${baseUrl}/m4`);
    await page.reload(); await loaded(page);

    await page.getByRole('button', { name: 'Create event', exact: true }).click();
    await page.locator('[name="title"]').fill(`${eventTitle} expired`);
    await page.route('**/hr/events', route => route.fulfill({ status: 419, contentType: 'text/html', body: 'Expired' }));
    await page.getByRole('button', { name: 'Save event', exact: true }).click();
    await page.waitForFunction(() => document.querySelector('form [role="alert"]')?.textContent.includes('session has expired'));
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    await page.unroute('**/hr/events');

    assert.equal((await context.request.delete(`${baseUrl}/hr/events/999999999`, { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } })).status(), 404);
    assert.equal((await context.request.post(`${baseUrl}/hr/events`, { data: {}, headers: { Accept: 'application/json' } })).status(), 419);

    page.once('dialog', dialog => dialog.accept());
    await eventItem().getByRole('button', { name: 'Delete', exact: true }).click();
    await page.waitForFunction(title => !document.querySelector('#company-event-list').textContent.includes(title), eventTitle);
    await loaded(page); await page.reload(); await loaded(page);
    assert.equal(await eventItem().count(), 0, 'Deletion must survive a reload');
    createdIds.delete(eventId);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('#theme-toggle-btn').click();
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
    assert.deepEqual(errors, []);
    console.log('PASS: persistent event CRUD, validation, duplicate prevention, search, Invalid/report details, real PDF, independent events, retries, HTML session expiry, CSRF, missing records, and mobile layout.');
  } finally {
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content').catch(() => '');
    for (const id of createdIds) {
      await context.request.delete(`${baseUrl}/hr/events/${id}`, { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
    }
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
