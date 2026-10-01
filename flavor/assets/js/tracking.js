/** Read-only order status. Authorization remains in Core, not the browser. */
(function(){
 'use strict';
 var ui=window.FlavorUI,form=document.querySelector('[data-ui-tracking]');
 if(!ui||!form)return;
 var result=document.querySelector('[data-ui-track-result]'),error=form.querySelector('[data-ui-track-error]'),id=document.getElementById('flavor-track-id'),token=document.getElementById('flavor-track-token'),button=form.querySelector('[type=submit]');
 var statuses={pending:'در انتظار پرداخت',processing:'در حال انجام','on-hold':'در انتظار تأیید',completed:'تکمیل‌شده',cancelled:'لغوشده',refunded:'مستردشده',failed:'پرداخت ناموفق'};
 var stateNames=['new','preparing','ready','completed'],stepNames=['در صف بررسی آشپزخانه','در حال آماده‌سازی','آمادهٔ تحویل یا سرو','تحویل ثبت شده'];
 form.addEventListener('input',function(){result.hidden=true;result.innerHTML='';error.hidden=true;});
 form.addEventListener('submit',function(event){
  event.preventDefault();if(button.disabled)return;
  var orderId=Number(ui.latin(id.value).trim()),secret=token.value.trim();
  result.hidden=true;result.innerHTML='';error.hidden=true;
  if(!Number.isSafeInteger(orderId)||orderId<1){error.hidden=false;error.textContent='شناسهٔ سفارش معتبر وارد کنید.';id.focus();return;}
  if(secret&&!/^[A-Za-z0-9_-]{20,128}$/.test(secret)){error.hidden=false;error.textContent='کد پیگیری را دقیقاً از رسید وارد کنید.';token.focus();return;}
  button.disabled=true;form.setAttribute('aria-busy','true');
  ui.request('orders/'+orderId,{headers:secret?{'X-Guest-Token':secret}:{},cache:'no-store'}).then(function(order){
   var html='<div class="flavor-order-receipt__head"><div><span class="flavor-ui-overline">اطلاعات سفارش</span><h2 id="flavor-tracking-result-title">سفارش '+ui.esc(ui.digits(order.order_number||order.id))+'</h2></div><span class="flavor-ui-tag">'+ui.esc(statuses[order.status]||order.status_label||order.status)+'</span></div><div class="flavor-order-receipt__meta"><div><span>مبلغ سفارش</span><strong>'+ui.esc(order.total_html)+'</strong></div><div><span>زمان ثبت</span><strong>'+ui.esc(order.jalali_date||order.date||'—')+'</strong></div></div><div class="flavor-tracking-items">';
   (order.items||[]).forEach(function(item){html+='<div><span>'+ui.esc(item.name)+' × '+ui.esc(ui.digits(item.quantity))+'</span><strong>'+ui.esc(String(item.line_total_display||item.total_html||'').replace(/<[^>]*>/g,''))+'</strong></div>';});
   html+='</div>';
   var current=stateNames.indexOf(order.kitchen_status);
   if(order.kitchen_status_known===true&&current>=0&&!['pending','failed','cancelled','refunded'].includes(order.status)){
    html+='<ol class="flavor-order-timeline" aria-label="وضعیت ثبت‌شده در آشپزخانه">'+stepNames.map(function(label,index){return '<li class="'+(index<=current?'is-reached':'')+'"'+(index===current?' aria-current="step"':'')+'><span aria-hidden="true">'+ui.digits(index+1)+'</span><strong>'+label+'</strong></li>';}).join('')+'</ol><p class="flavor-ui-note">آمادهٔ تحویل بودن به معنی تحویل به پیک یا رسیدن به مقصد نیست.</p>';
   }else html+='<p class="flavor-ui-note">مرحلهٔ تأییدشدهٔ آماده‌سازی برای این سفارش نمایش داده نمی‌شود؛ وضعیت پرداخت و سفارش را در بالا ببینید.</p>';
   result.innerHTML=html;result.hidden=false;result.focus({preventScroll:true});result.scrollIntoView({block:'nearest',behavior:'instant'});
  }).catch(function(problem){result.hidden=true;result.innerHTML='';error.hidden=false;error.textContent=problem.message;}).finally(function(){button.disabled=false;form.removeAttribute('aria-busy');});
 });
 form.hidden=false;
})();
