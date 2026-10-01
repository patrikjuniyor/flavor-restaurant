/** Core OTP UI. No credentials/tokens are persisted by the theme. */
(function(){
 'use strict';
 var ui=window.FlavorUI,cfg=window.flavorData||{};
 if(!ui||!cfg.hasCore)return;
 document.querySelectorAll('[data-flavor-auth]').forEach(function(root){
  var mobile=root.dataset.authMobile?document.querySelector(root.dataset.authMobile):root.querySelector('[data-auth-mobile]');
  var name=root.dataset.authName?document.querySelector(root.dataset.authName):null;
  var send=root.querySelector('[data-auth-send]'),verify=root.querySelector('[data-auth-verify]'),code=root.querySelector('[data-auth-code]'),box=root.querySelector('[data-auth-code-box]'),status=root.querySelector('[data-auth-status]');
  if(!mobile||!send||!verify||!code||!box||!status)return;
  var requested=false,busy=false,timer=null,wait=0;
  function phone(){var value=ui.latin(mobile.value).replace(/[\s-]/g,'');if(value.startsWith('+98'))value='0'+value.slice(3);else if(value.startsWith('0098'))value='0'+value.slice(4);return /^09\d{9}$/.test(value)?value:'';}
  function message(text,error){status.textContent=text;status.dataset.error=error?'yes':'no';status.setAttribute('role',error?'alert':'status');}
  function coolDown(){wait=30;send.disabled=true;send.setAttribute('aria-label','دریافت دوبارهٔ کد ورود');window.clearInterval(timer);timer=window.setInterval(function(){wait--;send.textContent='دریافت دوباره · '+ui.digits(wait);if(wait<=0){window.clearInterval(timer);send.disabled=busy;send.textContent='دریافت دوبارهٔ کد';}},1000);}
  function request(){
   if(busy||wait>0)return;
   var value=phone();if(!value){message('شمارهٔ معتبر مانند ۰۹۱۲۱۲۳۴۵۶۷ وارد کنید.',true);mobile.focus();return;}
   busy=true;send.disabled=true;message('در حال درخواست کد…',false);
   ui.request('auth/otp/request',{method:'POST',body:JSON.stringify({mobile:value})}).then(function(){if(value!==phone()){message('شماره تغییر کرد؛ برای شمارهٔ جدید کد بخواهید.',true);return;}requested=true;box.hidden=false;code.disabled=false;code.required=true;code.value='';message('درخواست کد پذیرفته شد. کد دریافتی را وارد کنید؛ پذیرش درخواست تضمین تحویل پیامک نیست.',false);code.focus();coolDown();}).catch(function(error){message(error.message,true);}).finally(function(){busy=false;if(wait<=0)send.disabled=false;});
  }
  function confirm(){
   if(busy||!requested)return;
   var value=phone(),entered=ui.latin(code.value).trim();
   if(!value||!/^\d{4,6}$/.test(entered)){message('شماره و کد یک‌بارمصرف را بررسی کنید.',true);code.focus();return;}
   busy=true;verify.disabled=true;message('در حال تأیید کد…',false);
   ui.request('auth/otp/verify',{method:'POST',body:JSON.stringify({mobile:value,code:entered,name:name?name.value:'',device_name:'Flavor website'})}).then(function(){
    // Core sets its HttpOnly WP cookie. Ignore Bearer/refresh tokens; do not
    // write auth credentials to local/session storage or URLs.
    if(root.dataset.authContext==='account'){location.reload();return;}
    return ui.refreshNonce().then(function(){box.hidden=true;requested=false;code.value='';code.disabled=true;code.required=false;send.hidden=true;message('ورود تأیید شد؛ می‌توانید سفارش را ادامه دهید.',false);});
   }).catch(function(error){message(error.message,true);}).finally(function(){busy=false;verify.disabled=false;});
  }
  send.addEventListener('click',request);verify.addEventListener('click',confirm);
  if(root.tagName==='FORM')root.addEventListener('submit',function(event){event.preventDefault();if(requested)confirm();else request();});
  code.addEventListener('keydown',function(event){if(event.key==='Enter'){event.preventDefault();event.stopPropagation();confirm();}});
  mobile.addEventListener('input',function(){requested=false;box.hidden=true;code.value='';code.disabled=true;code.required=false;message('',false);});
  root.hidden=false;
 });
})();
