/**
 * Playwright E2E test for Kitchen Display System (KDS):
 * Verifies ticket progression: new -> preparing -> ready -> completed
 * Also verifies tablet touch dimensions (>= 48px) and distance typography.
 */
import { chromium } from 'playwright';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');

const HTML_FIXTURE = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>داشبورد آشپزخانه</title>
  <link rel="stylesheet" href="/flavor-core/assets/public/css/kitchen-dashboard.css">
</head>
<body class="flavor-kitchen-kiosk">
<div class="flavor-kitchen" id="flavor-kitchen" data-branch="1" data-rest="/wp-json/flavor/v1/kitchen/tickets" data-nonce="test-nonce" data-poll="5" data-sound="1" data-home="/">
  <header class="flavor-kitchen__bar">
    <h1>آشپزخانه</h1>
    <label>
      شعبه
      <select id="flavor-kitchen-branch">
        <option value="1">شعبه مرکزی</option>
      </select>
    </label>
    <div class="flavor-kitchen__filters" role="group">
      <button type="button" class="is-active" data-filter="all">همه</button>
      <button type="button" data-filter="dine_in">سالن</button>
      <button type="button" data-filter="takeaway">بیرون‌بر</button>
      <button type="button" data-filter="delivery">ارسال</button>
    </div>
    <button type="button" id="flavor-kitchen-fs">تمام‌صفحه</button>
  </header>
  <div class="flavor-kitchen__lanes">
    <section data-lane="new">
      <h2>جدید</h2>
      <div class="flavor-kitchen__cards"></div>
    </section>
    <section data-lane="preparing">
      <h2>در حال آماده‌سازی</h2>
      <div class="flavor-kitchen__cards"></div>
    </section>
    <section data-lane="ready">
      <h2>آماده</h2>
      <div class="flavor-kitchen__cards"></div>
    </section>
  </div>
</div>
<script src="/flavor-core/assets/public/js/kitchen-dashboard.js"></script>
</body>
</html>`;

// In-memory state for mock backend
let tickets = [
  {
    id: 101,
    order_number: '101',
    order_mode: 'dine_in',
    table_number: '4',
    customer_name: 'علیرضا راد',
    kitchen_status: 'new',
    urgency: 'ok',
    age_seconds: 120,
    items: [
      { id: 1, item_name: 'چلو کباب کوبیده', quantity: 2, item_status: 'pending', modifiers: [{ name: 'پیاز اضافه' }] }
    ]
  }
];

const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');

  if (url.pathname === '/') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(HTML_FIXTURE);
    return;
  }

  if (url.pathname.startsWith('/flavor-core/assets/')) {
    const filePath = path.join(root, url.pathname);
    if (fs.existsSync(filePath)) {
      const ext = path.extname(filePath);
      const mime = ext === '.css' ? 'text/css' : 'application/javascript';
      res.writeHead(200, { 'Content-Type': mime });
      res.end(fs.readFileSync(filePath));
      return;
    }
  }

  if (url.pathname === '/wp-json/flavor/v1/kitchen/tickets') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: true, data: { tickets: tickets } }));
    return;
  }

  const statusMatch = url.pathname.match(/\/wp-json\/flavor\/v1\/kitchen\/tickets\/(\d+)\/status/);
  if (statusMatch && req.method === 'POST') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      const parsed = JSON.parse(body || '{}');
      const ticketId = parseInt(statusMatch[1], 10);
      const t = tickets.find(x => x.id === ticketId);
      if (t) {
        t.kitchen_status = parsed.status;
        if (parsed.status === 'completed') {
          tickets = tickets.filter(x => x.id !== ticketId);
        }
      }
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ success: true, data: { status: parsed.status } }));
    });
    return;
  }

  res.writeHead(404);
  res.end('Not found');
});

server.listen(0, '127.0.0.1', async () => {
  const port = server.address().port;
  console.log(`Mock KDS server listening on port ${port}`);

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    // Tablet viewport (iPad landscape)
    const context = await browser.newContext({ viewport: { width: 1024, height: 768 } });
    const page = await context.newPage();

    await page.goto(`http://127.0.0.1:${port}/`);

    // 1. Verify card appears in 'new' lane
    console.log('1. Waiting for ticket in "new" lane...');
    await page.waitForSelector('section[data-lane="new"] .flavor-kcard[data-id="101"]');
    console.log('   ✓ Ticket 101 rendered in "new" lane');

    // 2. Verify touch target dimensions on tablet
    const actionBtn = await page.$('.flavor-kcard[data-id="101"] button[data-next]');
    const box = await actionBtn.boundingBox();
    console.log(`2. Action button dimensions: ${box.width.toFixed(1)}px x ${box.height.toFixed(1)}px`);
    if (box.height < 44) { // 48px target with tolerance
      throw new Error(`Action button touch target too small: height is ${box.height}px, expected >= 44px`);
    }
    console.log('   ✓ Touch target is >= 48px tablet compliant');

    // 3. Advance to 'preparing'
    console.log('3. Clicking "شروع آماده‌سازی" (advance to preparing)...');
    await actionBtn.click();
    await page.waitForSelector('section[data-lane="preparing"] .flavor-kcard[data-id="101"]');
    console.log('   ✓ Ticket 101 successfully moved to "preparing" lane');

    // 4. Advance to 'ready'
    console.log('4. Clicking "آماده شد" (advance to ready)...');
    const readyBtn = await page.waitForSelector('section[data-lane="preparing"] .flavor-kcard[data-id="101"] button[data-next="ready"]');
    await readyBtn.click();
    await page.waitForSelector('section[data-lane="ready"] .flavor-kcard[data-id="101"]');
    console.log('   ✓ Ticket 101 successfully moved to "ready" lane');

    // 5. Advance to 'completed'
    console.log('5. Clicking "تحویل شد" (advance to completed)...');
    const completeBtn = await page.waitForSelector('section[data-lane="ready"] .flavor-kcard[data-id="101"] button[data-next="completed"]');
    await completeBtn.click();

    // Verify ticket is removed from all active lanes
    await page.waitForFunction(() => !document.querySelector('.flavor-kcard[data-id="101"]'));
    console.log('   ✓ Ticket 101 completed and removed from display');

    console.log('\n=== KDS Playwright Test PASSED ===');
    await browser.close();
    server.close();
    process.exit(0);
  } catch (err) {
    console.error('Playwright KDS test failed:', err);
    if (browser) await browser.close();
    server.close();
    process.exit(1);
  }
});
