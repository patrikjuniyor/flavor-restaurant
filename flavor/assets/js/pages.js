/** Progressive branch filtering; all published cards stay visible without JS. */
(function(){
 'use strict';
 var filters=document.querySelector('[data-ui-branch-filters]');if(!filters)return;
 var input=document.getElementById('flavor-branch-query'),cards=Array.from(document.querySelectorAll('[data-ui-branch]')),status=document.querySelector('[data-ui-branch-status]'),city='';
 function normalize(value){return String(value||'').replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/\s+/g,' ').trim().toLowerCase();}
 function apply(){var query=normalize(input.value),count=0;cards.forEach(function(card){var visible=(!city||card.dataset.city===city)&&normalize(card.dataset.name+' '+card.dataset.city).includes(query);card.hidden=!visible;if(visible)count++;});if(status)status.textContent=String(count).replace(/\d/g,function(n){return '۰۱۲۳۴۵۶۷۸۹'[n];})+' شعبه نمایش داده می‌شود.';}
 input.addEventListener('input',apply);filters.addEventListener('click',function(event){var button=event.target.closest('[data-ui-city]');if(!button)return;city=button.dataset.uiCity;filters.querySelectorAll('[data-ui-city]').forEach(function(item){item.setAttribute('aria-pressed',String(item===button));});apply();});
 filters.hidden=false;apply();
})();
