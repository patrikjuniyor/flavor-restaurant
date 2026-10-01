/** Explicit receipt-code actions. No secret is sent, saved or put into a URL. */
(function(){
 'use strict';
 document.querySelectorAll('[data-ui-receipt-secret]').forEach(function(root){
  var input=root.querySelector('input'),status=root.querySelector('[role=status]'),copy=root.querySelector('[data-ui-copy-secret]'),show=root.querySelector('[data-ui-show-secret]');
  if(!input||!status||!copy||!show)return;
  show.addEventListener('click',function(){input.type=input.type==='password'?'text':'password';show.textContent=input.type==='text'?'پنهان‌کردن کد':'نمایش کد';});
  copy.addEventListener('click',function(){
   function manual(){input.type='text';show.textContent='پنهان‌کردن کد';input.focus();input.select();status.textContent='کپی خودکار ممکن نیست؛ کد را دستی کپی کنید و در اختیار دیگران نگذارید.';}
   if(!navigator.clipboard){manual();return;}
   navigator.clipboard.writeText(input.value).then(function(){status.textContent='کد پیگیری کپی شد؛ آن را در اختیار دیگران نگذارید.';}).catch(manual);
  });
 });
})();
