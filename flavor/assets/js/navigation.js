/** Live cart badge only; no order, payment or reservation is made by navigation. */
(function () {
 'use strict';
 var cfg=window.flavorData||{};
 var nav=document.querySelector('.flavor-mobile-nav');
 if(!nav)return;
 nav.querySelectorAll('[data-ui-nav-cart]').forEach(function(button){button.hidden=false;});
 function paint(count,total){
  nav.querySelectorAll('[data-ui-nav-count]').forEach(function(badge){badge.hidden=!(Number(count)>0);badge.textContent=String(count||0).replace(/\d/g,function(n){return '۰۱۲۳۴۵۶۷۸۹'[n];});});
  var plain=document.createElement('textarea');plain.innerHTML=String(total||'').replace(/<[^>]*>/g,'');
  nav.querySelectorAll('[data-ui-nav-total]').forEach(function(amount){amount.hidden=!(Number(count)>0&&plain.value);amount.textContent=plain.value;amount.setAttribute('aria-label','مبلغ فعلی سبد: '+plain.value);amount.title='مبلغ فعلی سبد: '+plain.value;});
 }
 document.addEventListener('flavor:cart-updated',function(event){paint(event.detail.count,event.detail.total);});
 function height(){document.body.style.setProperty('--ui-mobile-nav-height',nav.getBoundingClientRect().height+'px');}
 if(typeof ResizeObserver!=='undefined')new ResizeObserver(height).observe(nav);else window.addEventListener('resize',height);
 height();
 var mobile=window.matchMedia('(max-width:991px)');
 function load(){
  if(!mobile.matches||!cfg.hasCore||document.getElementById('flavor-cart-panel'))return;
  var headers={'X-WP-Nonce':cfg.nonce||''};
  try{var token=sessionStorage.getItem('flavorCartToken');if(token)headers['X-Cart-Token']=token;}catch(ignore){}
  fetch(cfg.rest+'cart',{credentials:'same-origin',headers:headers,cache:'no-store'}).then(function(response){if(!response.ok)throw new Error('Cart unavailable');return response.json();}).then(function(json){if(json.success===false)return;var cart=json.success===true?json.data:json;paint(cart.count,cart.total_html);if(cart.cart_token){try{sessionStorage.setItem('flavorCartToken',cart.cart_token);}catch(ignore){}}}).catch(function(){/* No invented count when offline. */});
 }
 load();mobile.addEventListener('change',load);
})();
