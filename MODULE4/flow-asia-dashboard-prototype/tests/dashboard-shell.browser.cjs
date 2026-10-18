const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const baseUrl = process.env.M4_BASE_URL || 'http://127.0.0.1:8035';

async function loaded(page) {
  await page.waitForFunction(() => document.querySelector('#refresh-data-btn')?.disabled === false);
}

(async () => {
  const browser = await chromium.launch({
    ...(process.env.M4_BROWSER_PATH ? { executablePath: process.env.M4_BROWSER_PATH } : {}), headless: true,
  });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 965 } });
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.route('https://**/*', route => route.abort());
    await page.goto(`${baseUrl}/m4`); await loaded(page);
    const config = await page.evaluate(() => window.flowAsiaBackend);
    assert.equal(config.user.role, 'employee');
    const ownFeed = await page.request.get(`${baseUrl}/calendar/employees/${config.user.employee_id}/month?month=${config.month}`);
    assert.equal(ownFeed.status(), 200);
    const employee = (await ownFeed.json()).data;
    assert.equal(await page.locator('#header-dashboard-sub').innerText(), config.todayLabel);
    assert.equal(await page.locator('#user-display-name').innerText(), config.user.name);
    assert.equal(await page.locator('#kpi-present').innerText(), String(employee.summary.status_counts.present));
    assert.equal(await page.locator('#kpi-overtime').innerText(), String(employee.summary.status_counts.overtime));
    assert.equal(await page.locator('.summary-cards > .card').count(), 5);
    const nav = ['My Dashboard', 'Time In / Out', 'My Attendance', 'My Leave', 'Overtime and Schedule', 'Payslips', 'Announcements', 'Notifications', 'My Profile'];
    assert.deepEqual(await page.locator('.nav-group .nv').allTextContents(), nav);
    for (const id of ['request-leave-btn', 'time-in-btn', 'signout-btn']) assert.equal(await page.locator(`#${id}`).isDisabled(), true);
    assert.equal((await page.request.get(`${baseUrl}/calendar/employees/1/month?month=${config.month}`)).status(), 403);
    assert.equal((await page.request.get(`${baseUrl}/hr/events`)).status(), 403);

    const recorded = employee.days.find(day => day.status === 'absent') || employee.days[0];
    if (recorded) {
      const dayButton = page.locator(`#staff-calendar-grid button[data-date="${recorded.date}"]`);
      await dayButton.click();
      assert.equal(await dayButton.getAttribute('aria-selected'), 'true');
      assert.match(await page.locator('#selected-day-title').innerText(), new RegExp(config.statuses[recorded.status].label));
      await page.keyboard.press('Shift+Tab');
      assert.equal(await page.locator('#modal-dismiss-btn').evaluate(node => node === document.activeElement), true);
      await page.keyboard.press('Tab');
      assert.equal(await page.locator('#modal-close-btn').evaluate(node => node === document.activeElement), true);
      await page.keyboard.press('Escape');
      assert.equal(await dayButton.evaluate(node => node === document.activeElement), true);
    }
    const currentToday = await page.locator('#attendance-today-status').innerText();
    await page.getByRole('button', { name: 'Next calendar period', exact: true }).click(); await loaded(page);
    const nextMonth = await page.locator('#employee-calendar-month').inputValue();
    assert.notEqual(nextMonth, config.month);
    assert.equal(await page.locator('#attendance-today-status').innerText(), currentToday, 'Today must stay independent of the selected month');
    const nextFeed = await page.request.get(`${baseUrl}/calendar/employees/${config.user.employee_id}/month?month=${nextMonth}`);
    const nextData = (await nextFeed.json()).data;
    assert.equal(await page.locator('#kpi-present').innerText(), String(nextData.summary.status_counts.present));
    await page.locator('#employee-calendar-view').selectOption('week');
    assert.match(await page.locator('.cal-month-title').innerText(), /Week 1/);
    assert.equal(await page.locator('#kpi-present').innerText(), String(nextData.summary.status_counts.present));
    await page.locator('#employee-calendar-view').selectOption('month');
    await page.locator('#employee-calendar-month').fill(config.month); await loaded(page);

    await page.locator('.extended-legend summary').click();
    assert.equal(await page.locator('.extended-legend-items').getByText('Invalid punch', { exact: true }).isVisible(), true);
    await page.locator('.extended-legend summary').click();
    for (const width of [1920, 1024, 768, 390]) {
      await page.setViewportSize({ width, height: 965 });
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `No horizontal overflow at ${width}px`);
      const logo = await page.locator('.flow-asia-logo').boundingBox();
      assert.ok(logo.x >= 0 && logo.x + logo.width <= width, `Brand remains inside the viewport at ${width}px`);
    }
    await page.locator('#theme-toggle-btn').click();
    assert.equal(await page.locator('html').getAttribute('data-theme'), 'light');
    await page.locator('#staff-calendar-grid button').first().click();
    await page.keyboard.press('Escape');

    // Simulate a changed upstream response without modifying existing database records.
    const changed = JSON.parse(JSON.stringify(await ownFeed.json()));
    const changedDay = changed.data.days.find(day => day.status === 'absent');
    assert.ok(changedDay);
    changedDay.status = 'present'; changedDay.additional_statuses = [];
    changed.data.summary.status_counts.present++;
    changed.data.summary.status_counts.absent--;
    await page.route('**/calendar/employees/*/month?*', route => route.fulfill({ status: 200, json: changed }));
    await page.locator('#staff-calendar-grid button').first().click();
    await page.evaluate(() => window.dispatchEvent(new Event('flowasia:calendar-updated')));
    await page.waitForTimeout(350);
    assert.equal(await page.locator('#day-modal').evaluate(node => node.classList.contains('open')), true, 'Sync must preserve open day details');
    assert.equal(await page.locator('#kpi-present').innerText(), String(employee.summary.status_counts.present));
    await page.keyboard.press('Escape');
    await page.waitForFunction(expected => document.querySelector('#kpi-present').textContent === String(expected), changed.data.summary.status_counts.present);
    await loaded(page);
    assert.match(await page.locator(`#staff-calendar-grid button[data-date="${changedDay.date}"]`).getAttribute('aria-label'), /Present/);
    await page.unroute('**/calendar/employees/*/month?*');
    await page.locator('#refresh-data-btn').click(); await loaded(page);
    assert.equal(await page.locator('#kpi-present').innerText(), String(employee.summary.status_counts.present));

    await page.route('**/calendar/employees/*/month?*', route => route.fulfill({ status: 503, contentType: 'application/json', body: '{"message":"Unavailable"}' }));
    await page.locator('#refresh-data-btn').click(); await loaded(page);
    assert.equal(await page.locator('#attendance-today-status').innerText(), 'Attendance unavailable');
    assert.equal(await page.locator('#kpi-overtime').innerText(), '—');
    assert.match(await page.locator('#selected-day-description').innerText(), /retry/i);
    await page.unroute('**/calendar/employees/*/month?*');
    await page.locator('#refresh-data-btn').click(); await loaded(page);
    assert.equal(await page.locator('#attendance-today-status').innerText(), currentToday);

    // This intercept tests the handoff protocol only; the peer modules are absent.
    await page.route(`${baseUrl}/m4`, async route => {
      const response = await route.fetch();
      const html = (await response.text()).replace(/window\.flowAsiaBackend=(\{.*?\});<\/script>/, (_, json) => {
        const settings = JSON.parse(json);
        settings.navigation = { leave: `${baseUrl}/host/leave`, punch: 'javascript:alert(1)', profile: 'https://example.invalid/profile' };
        settings.logoutUrl = `${baseUrl}/host/logout`;
        return `window.flowAsiaBackend=${JSON.stringify(settings)};</script>`;
      });
      await route.fulfill({ response, body: html });
    });
    await page.reload(); await loaded(page);
    assert.equal(await page.locator('.nav-group [data-page="leave"]').getAttribute('href'), `${baseUrl}/host/leave`);
    assert.equal(await page.locator('#request-leave-btn').isEnabled(), true);
    assert.equal(await page.locator('#time-in-btn').isDisabled(), true);
    assert.equal(await page.locator('.nav-group [data-page="profile"]').isDisabled(), true);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    await page.route(`${baseUrl}/host/logout`, route => route.fulfill({ status: 200, contentType: 'text/html', body: 'Host handoff' }));
    const posted = page.waitForRequest(`${baseUrl}/host/logout`);
    await page.locator('#signout-btn').click();
    const logout = await posted;
    assert.equal(logout.method(), 'POST');
    assert.equal(new URLSearchParams(logout.postData()).get('_token'), csrf);
    assert.deepEqual(errors, []);
    console.log('PASS: database-backed summary, module-update refresh, modal-safe sync, server date, permissions, month/week, details, focus, error/retry, responsive themes and POST-CSRF handoff. Upstream UI responses are simulated; live peer modules remain absent.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
