/** Explicit receipt-code actions. No secret is sent, saved or put into a URL. */
(function(){
 'use strict';
 // The copy in this file comes from UI::strings(), served as
 // flavorData.i18n.ui; nothing here carries Persian text of its own.
 /* flavor-i18n-map: flavorData.i18n.ui */
 var STR=((window.flavorData||{}).i18n||{}).ui||{};
 function t(key){return STR[key]||key;}
 function fmt(key,v){return String(t(key)).replace('%s',v);}
 document.querySelectorAll('[data-ui-receipt-secret]').forEach(function(root){
  var input=root.querySelector('input'),status=root.querySelector('[role=status]'),copy=root.querySelector('[data-ui-copy-secret]'),show=root.querySelector('[data-ui-show-secret]');
  if(!input||!status||!copy||!show)return;
  show.addEventListener('click',function(){input.type=input.type==='password'?'text':'password';show.textContent=input.type==='text'?t('hide_code'):t('show_code');});
  copy.addEventListener('click',function(){
   function manual(){input.type='text';show.textContent=t('hide_code');input.focus();input.select();status.textContent=t('copy_manual');}
   if(!navigator.clipboard){manual();return;}
   navigator.clipboard.writeText(input.value).then(function(){status.textContent=t('code_copied');}).catch(manual);
  });
 });
})();
