/** Core OTP UI. No credentials/tokens are persisted by the theme. */
(function(){
 'use strict';
 var ui=window.FlavorUI,cfg=window.flavorData||{};
 // The copy in this file comes from UI::strings(), served as
 // flavorData.i18n.ui; nothing here carries Persian text of its own.
 /* flavor-i18n-map: flavorData.i18n.ui */
 var STR=((window.flavorData||{}).i18n||{}).ui||{};
 function t(key){return STR[key]||key;}
 function fmt(key,v){return String(t(key)).replace('%s',v);}
 if(!ui||!cfg.hasCore)return;
 document.querySelectorAll('[data-flavor-auth]').forEach(function(root){
  var mobile=root.dataset.authMobile?document.querySelector(root.dataset.authMobile):root.querySelector('[data-auth-mobile]');
  var name=root.dataset.authName?document.querySelector(root.dataset.authName):null;
  var send=root.querySelector('[data-auth-send]'),verify=root.querySelector('[data-auth-verify]'),code=root.querySelector('[data-auth-code]'),box=root.querySelector('[data-auth-code-box]'),status=root.querySelector('[data-auth-status]');
  if(!mobile||!send||!verify||!code||!box||!status)return;
  var requested=false,busy=false,timer=null,wait=0;
  function phone(){var value=ui.latin(mobile.value).replace(/[\s-]/g,'');if(value.startsWith('+98'))value='0'+value.slice(3);else if(value.startsWith('0098'))value='0'+value.slice(4);return /^09\d{9}$/.test(value)?value:'';}
  function message(text,error){status.textContent=text;status.dataset.error=error?'yes':'no';status.setAttribute('role',error?'alert':'status');status.setAttribute('aria-live',error?'assertive':'polite');}
  function coolDown(){wait=30;send.disabled=true;send.setAttribute('aria-label',t('resend_aria'));window.clearInterval(timer);timer=window.setInterval(function(){wait--;send.textContent=fmt('resend_countdown',ui.digits(wait));if(wait<=0){window.clearInterval(timer);send.disabled=busy;send.textContent=t('resend_short');}},1000);}
  function request(){
   if(busy||wait>0)return;
   var value=phone();if(!value){message('',false);ui.fieldError(mobile,t('mobile_invalid_otp'));mobile.focus();return;}
   busy=true;send.disabled=true;send.setAttribute('aria-busy','true');message(t('code_requesting'),false);/* prompt 60: sending buttons carry aria-busy while in flight. */
   ui.request('auth/otp/request',{method:'POST',body:JSON.stringify({mobile:value})}).then(function(){if(value!==phone()){message(t('phone_changed'),true);return;}requested=true;box.hidden=false;code.disabled=false;code.required=true;code.value='';message(t('code_requested'),false);code.focus();coolDown();}).catch(function(error){message(error.message,true);}).finally(function(){busy=false;send.removeAttribute('aria-busy');if(wait<=0)send.disabled=false;});
  }
  function confirm(){
   if(busy||!requested)return;
   var value=phone(),entered=ui.latin(code.value).trim();
   if(!value){message(t('phone_changed'),true);ui.fieldClear(code);mobile.focus();return;}
   if(!/^\d{4,6}$/.test(entered)){message('',false);ui.fieldError(code,t('check_code'));code.focus();return;}
   busy=true;verify.disabled=true;verify.setAttribute('aria-busy','true');message(t('code_verifying'),false);
   ui.request('auth/otp/verify',{method:'POST',body:JSON.stringify({mobile:value,code:entered,name:name?name.value:'',device_name:'Flavor website'})}).then(function(){
    // Core sets its HttpOnly WP cookie. Ignore Bearer/refresh tokens; do not
    // write auth credentials to local/session storage or URLs.
    if(root.dataset.authContext==='account'){location.reload();return;}
    return ui.refreshNonce().then(function(){box.hidden=true;requested=false;code.value='';code.disabled=true;code.required=false;send.hidden=true;ui.fieldClear(mobile);ui.fieldClear(code);message(t('verified'),false);});
   }).catch(function(error){message(error.message,true);}).finally(function(){busy=false;verify.removeAttribute('aria-busy');verify.disabled=false;});
  }
  send.addEventListener('click',request);verify.addEventListener('click',confirm);
  if(root.tagName==='FORM')root.addEventListener('submit',function(event){event.preventDefault();if(requested)confirm();else request();});
  code.addEventListener('keydown',function(event){if(event.key==='Enter'){event.preventDefault();event.stopPropagation();confirm();}});
  mobile.addEventListener('input',function(){requested=false;box.hidden=true;code.value='';code.disabled=true;code.required=false;ui.fieldClear(mobile);message('',false);});
  code.addEventListener('input',function(){ui.fieldClear(code);});
  root.hidden=false;
 });
})();
