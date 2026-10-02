/* Disposable real-WordPress storefront UI checks. No payment/SMS/order submitted. */
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { testCustomer } from './customer.mjs';
import { testPages } from './pages.mjs';
import { testCustomizer } from './customizer.mjs';
import { testCartEditing } from './editor.mjs';
const base = process.env.SITE_URL || 'http://localhost:8080/';
const stage = Number(process.env.UI_STAGE || 1);
if (stage >= 6 && process.env.FLAVOR_DEMO_QA !== '1') throw new Error('Customizer writes require an opted-in disposable site: FLAVOR_DEMO_QA=1.');
const output = path.resolve(process.env.QA_OUTPUT_DIR || '../../.cache/ui-qa');
await fs.mkdir(output, { recursive: true });
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const report = { stage, result: 'FAIL', widths: [], accessibility: [], checks: [] };
async function audit(label) {
 const result = await new AxeBuilder({ page }).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
 report.accessibility.push({ label, violations: result.violations });
 assert.equal(result.violations.length, 0, label + ': ' + result.violations.map(v=>v.id).join(', '));
}
async function widths() {
 for(const width of [320,390,768,1024,1440]) {
  await page.setViewportSize({ width, height: 960 });
  const scroll = await page.evaluate(() => document.documentElement.scrollWidth);
  assert.ok(scroll <= width + 1, 'Horizontal overflow at ' + width);
  report.widths.push({ width, scroll });
 }
}
try {
 await page.goto(new URL('menu/', base).href, { waitUntil: 'networkidle' });
 await page.waitForFunction(() => document.querySelectorAll('#flavor-menu-grid [data-add]').length >= 8);
 assert.equal(await page.locator('body.flavor-ui').count(), 1);
 assert.equal(await page.locator('h1').count(), 1);
 await page.evaluate(() => document.fonts.ready);
 await widths();
 await audit('menu-desktop');
 await page.screenshot({ path: path.join(output, 'menu-desktop.png'), fullPage: true });
 await page.setViewportSize({ width:390,height:844 });
 await audit('menu-mobile');
 await page.screenshot({ path: path.join(output, 'menu-mobile.png'), fullPage: true });
 await page.setViewportSize({ width:1440,height:1000 });
 await page.locator('[data-ui-view="list"]').click();
 assert.equal(await page.locator('body.flavor-ui-menu-layout-list').count(),1);
 await page.locator('[data-ui-view="grid"]').click();
 const cat = page.locator('#flavor-cats [data-cat]').nth(1);
 await cat.click();
 assert.equal(await cat.getAttribute('aria-pressed'),'true');
 assert.equal(await page.locator('#flavor-menu-grid .flavor-card:visible').count(),2);
 await page.locator('#flavor-cats [data-cat="0"]').click();
 const trigger = page.locator('#flavor-menu-grid [data-add]').first();
 const productId = await trigger.getAttribute('data-add');
 await trigger.click();
 await page.waitForSelector('#flavor-sheet:not([hidden])');
 assert.equal(await page.locator('#flavor-cart-count').textContent(),'0','Opening product added a cart item.');
 assert.equal(await page.locator('.flavor-sheet__close').evaluate(el=>el===document.activeElement),true);
 assert.ok((await page.locator('#flavor-sheet-price').textContent()).includes('تومان'));
 const before = await page.locator('#flavor-sheet-price').textContent();
 await page.locator('[data-q="1"]').click();
 assert.equal(await page.locator('#flavor-qty').textContent(),'۲');
 assert.notEqual(await page.locator('#flavor-sheet-price').textContent(),before);
 await page.locator('[data-q="-1"]').click();
 assert.equal(await page.locator('#flavor-sheet-price').textContent(),before);
 await audit('product-details');
 await page.screenshot({ path:path.join(output,'product-desktop.png') });
 await page.keyboard.press('Escape');
 assert.equal(await page.locator('#flavor-sheet').isVisible(),false);
 assert.equal(await trigger.evaluate(el=>el===document.activeElement),true);
 assert.equal(await page.locator('#main').getAttribute('inert'),null);
 await page.goto(new URL('menu/#item-'+productId,base).href,{waitUntil:'networkidle'});
 await page.waitForSelector('#flavor-sheet:not([hidden])');
 assert.equal(await page.locator('#flavor-cart-count').textContent(),'0');
 await page.locator('#flavor-sheet-add').click();
 await page.waitForFunction(()=>document.getElementById('flavor-cart-count').textContent==='1');
 await page.reload({waitUntil:'networkidle'});
 assert.equal(await page.locator('#flavor-cart-count').textContent(),'1');
 await page.locator('.flavor-sheet__close').click();
 if (stage >= 2) {
  await page.locator('#flavor-cart-toggle').click();
  assert.equal(await page.locator('#flavor-cart-toggle').getAttribute('aria-expanded'),'true');
  assert.equal(await page.locator('#flavor-cart-close').evaluate(el=>el===document.activeElement),true);
  await audit('cart-review');
  await page.screenshot({path:path.join(output,'cart-desktop.png')});
  await page.locator('[data-ui-cart-qty="1"]').first().click();
  await page.waitForFunction(()=>window.FlavorCartUI.state().count===2 && !document.getElementById('flavor-cart-lines').hasAttribute('aria-busy'));
  assert.equal(await page.locator('.flavor-cart-line output').first().textContent(),'۲');
  const lineState=await page.evaluate(()=>window.FlavorCartUI.state().items[0]);
  assert.equal((await page.locator('.flavor-cart-line__price').first().textContent()).trim(),lineState.line_total_html);
  assert.equal((await page.locator('#flavor-cart-total').textContent()).trim(),await page.evaluate(()=>window.FlavorCartUI.state().total_html));
  await page.locator('#flavor-cart-continue').click();
  await page.waitForSelector('#flavor-checkout:not([hidden])');
  await page.waitForSelector('#flavor-pay-box input[name="pay"]');
  await audit('cart-checkout');
  await page.screenshot({path:path.join(output,'checkout-desktop.png')});
  await page.setViewportSize({width:390,height:844});
  await audit('cart-checkout-mobile');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  await page.screenshot({path:path.join(output,'checkout-mobile.png')});
  await page.locator('#flavor-mobile').fill('۰۹۱۲۱۲۳۴۵۶۷');
  const orderMode=await page.evaluate(()=>document.querySelector('#flavor-modes .is-active').dataset.mode);
  if(orderMode==='delivery') {
   await page.locator('#flavor-city').fill('تهران');
   await page.locator('#flavor-hood').fill('ونک');
   await page.locator('#flavor-line').fill('نشانی آزمایشی QA، هیچ سفارش واقعی ارسال نشود');
   await page.locator('#flavor-hood').press('Tab');
   await page.waitForSelector('#flavor-zone-msg[data-available="yes"]');
   assert.ok((await page.locator('#flavor-cart-zone').textContent()).includes('تومان'));
  }
  let submits=0;
  await page.route('**/wp-json/flavor/v1/checkout',async route=>{
   submits++;
   await new Promise(resolve=>setTimeout(resolve,100));
   await route.fulfill({status:400,contentType:'application/json',body:JSON.stringify({code:'qa_declined',message:'خطای آزمایشی پرداخت؛ سبد باید باقی بماند.',data:{status:400}})});
  });
  await page.locator('#flavor-place').click();
  await page.waitForSelector('#flavor-checkout-err:not([hidden])');
  assert.equal(submits,1);
  assert.equal(await page.locator('#flavor-cart-count').textContent(),'2');
  assert.ok((await page.locator('#flavor-mobile').inputValue()).includes('۰۹۱۲'));
  assert.equal(await page.locator('#flavor-place').isEnabled(),true);
  await page.unroute('**/wp-json/flavor/v1/checkout');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#flavor-cart-panel').isVisible(),false);
  await page.locator('#flavor-cart-toggle').click();
  await page.locator('#flavor-cart-back').click();
  await page.locator('[data-rm]').first().click();
  await page.waitForFunction(()=>window.FlavorCartUI.state().count===0);
  assert.equal(await page.locator('#flavor-cart-empty').isVisible(),true);
  assert.equal(await page.locator('#flavor-cart-actions').isVisible(),false);
  await audit('empty-cart');
  await page.keyboard.press('Escape');
  report.checks.push('real cart quantity/remove and server line totals','cart and checkout desktop/mobile accessibility','live delivery quote separated from current cart total','intercepted payment error preserves cart/fields and re-enables retry','empty cart and dialog focus/Escape');
 }
 if(stage>=3){
  await page.setViewportSize({width:390,height:844});
  await page.locator('#flavor-drawer-toggle').click();
  assert.equal(await page.locator('#flavor-mobile-drawer').getAttribute('aria-hidden'),'false');
  assert.equal(await page.locator('#flavor-drawer-close').evaluate(el=>el===document.activeElement),true);
  await page.keyboard.press('Shift+Tab');
  assert.equal(await page.locator('#flavor-mobile-drawer a').last().evaluate(el=>el===document.activeElement),true);
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#flavor-drawer-toggle').evaluate(el=>el===document.activeElement),true);
  assert.equal(await page.locator('#main').getAttribute('inert'),null);
  await page.locator('#flavor-mobile-cart-btn').click();
  assert.equal(await page.locator('#flavor-cart-panel').isVisible(),true);
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#flavor-mobile-cart-btn').evaluate(el=>el===document.activeElement),true);
  assert.equal(await page.locator('#flavor-cart-toggle').getAttribute('aria-expanded'),'false');
  await page.goto(new URL('menu/?open_cart=1',base).href,{waitUntil:'networkidle'});
  assert.equal(await page.locator('#flavor-cart-panel').isVisible(),true);
  await page.keyboard.press('Escape');
  await page.evaluate(()=>window.flavorToast('پیام آزمایشی قابل خواندن','success'));
  await audit('accessible-feedback');
  await page.locator('.flavor-ui-toast').waitFor({state:'detached'});
  report.checks.push('shared mobile drawer focus/inert/trap across skins','mobile cart and open-cart links','readable reduced-motion feedback');
 }
 if(stage>=6)await testCartEditing(page,report,base,output);
 if(stage>=4)await testCustomer(page,context,report,base,output);
 if(stage>=5)await testPages(page,report,base,output);
 if(stage>=6)await testCustomizer(browser,report,base,output);
 report.checks.push('responsive menu/grid/list','category filtering and pressed states','real product detail and live quantity price','no implicit cart add','modal Escape/focus/inert restoration','deep link and explicit cart persistence');
 const noJs = await browser.newContext({ javaScriptEnabled:false,viewport:{width:390,height:844} });
 const staticPage=await noJs.newPage();
 await staticPage.goto(new URL('menu/',base).href);
 assert.ok(await staticPage.locator('#flavor-menu-grid .flavor-food-card').count()>=8);
 assert.equal(await staticPage.locator('#flavor-cats-nav').isVisible(),false);
 assert.ok((await staticPage.locator('#flavor-menu-grid a').first().getAttribute('href')).includes('/product/'));
 await noJs.close();
 report.checks.push('real server-rendered menu and product links without JS');
 assert.deepEqual(errors,[]);
 report.result='PASS';
 console.log('PASS UI stage '+stage+': menu, 5 widths, axe, product, keyboard, explicit cart and no-JS.');
} catch(error) { report.error=error.message; throw error; }
finally { await fs.writeFile(path.join(output,'stage-'+stage+'-report.json'),JSON.stringify(report,null,2)); await browser.close(); }
