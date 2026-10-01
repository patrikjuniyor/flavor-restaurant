/* Published page/branch/calendar checks. No booking is submitted. */
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import AxeBuilder from '@axe-core/playwright';
export async function testPages(page,report,base,output){
 async function audit(label){const r=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();report.accessibility.push({label,violations:r.violations});assert.equal(r.violations.length,0,label+': '+r.violations.map(v=>v.id).join(', '));}
 for(const name of ['about','contact','branches','missing-ui-qa-page']){
  const response=await page.goto(new URL(name+'/',base).href,{waitUntil:'networkidle'});
  assert.equal(response.status(),name==='missing-ui-qa-page'?404:200);
  assert.equal(await page.locator('h1').count(),1);
  for(const width of [320,390,768,1024,1440]){await page.setViewportSize({width,height:960});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),name+' overflow at '+width);}
  await audit(name+'-desktop');
  await page.screenshot({path:path.join(output,name+'-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:844});
  await audit(name+'-mobile');
 }
 await page.goto(new URL('branches/',base).href,{waitUntil:'networkidle'});
 await page.locator('#flavor-branch-query').fill('اصفهان');
 assert.equal(await page.locator('[data-ui-branch]:visible').count(),1);
 const branch=page.locator('[data-ui-branch]:visible');
 assert.ok((await branch.locator('a[href^="tel:"]').getAttribute('href')).includes('03132221100'));
 const branchHref=await branch.locator('h2 a').getAttribute('href');
 await page.locator('#flavor-branch-query').fill('وجودنداردQA');
 assert.equal(await page.locator('[data-ui-branch]:visible').count(),0);
 await page.goto(branchHref,{waitUntil:'networkidle'});
 assert.ok((await page.locator('.flavor-branch-hours').textContent()).includes('۱۰:۰۰'));
 assert.ok((await page.locator('.flavor-branch-hours').textContent()).includes('۲۲:۰۰'));
 await audit('published-branch-detail');
 const reservationHref=await page.locator('.flavor-branch-single a').filter({hasText:'بررسی ظرفیت رزرو'}).getAttribute('href');
 let bookings=0;const watch=req=>{if(req.method()==='POST'&&/\/reservations(?:\?|$)/.test(req.url()))bookings++;};page.on('request',watch);
 await page.goto(reservationHref,{waitUntil:'networkidle'});
 await page.waitForSelector('#flavor-res-branch option',{state:'attached'});
 assert.equal(await page.locator('#flavor-res-section option').count(),2,'Unconfigured dining sections are advertised.');
 await page.locator('.flavor-cal__day:not([disabled])').nth(1).click();
 await page.waitForSelector('.flavor-slot:not([disabled])');
 await page.locator('.flavor-slot:not([disabled])').first().click();
 assert.equal(await page.locator('.flavor-slot.is-active').getAttribute('aria-pressed'),'true');
 await page.locator('#flavor-res-party').fill('3');await page.locator('#flavor-res-party').press('Tab');
 await page.waitForFunction(()=>!document.getElementById('flavor-res-slots').hasAttribute('aria-busy'));
 assert.equal(await page.locator('.flavor-slot.is-active').count(),0);
 await page.setViewportSize({width:1440,height:1000});await audit('reservation-desktop');
 await page.screenshot({path:path.join(output,'reservation-desktop.png'),fullPage:true});
 await page.setViewportSize({width:390,height:844});await audit('reservation-mobile');
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 assert.equal(bookings,0);page.off('request',watch);
 report.checks.push('about/contact/branches/404: five widths, desktop/mobile axe and actual HTTP status','branch query/city filtering and Persian phone URI','branch hours from explicit rows, no made-up open status/map','real reservation branch/table-section/calendar/slots and stale-time reset; no booking submitted');
}
