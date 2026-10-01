/** Global navigation and accessible feedback, shared by every skin. */
(function(){
 'use strict';
 var toggle=document.getElementById('flavor-drawer-toggle');
 var close=document.getElementById('flavor-drawer-close');
 var drawer=document.getElementById('flavor-mobile-drawer');
 var overlay=document.getElementById('flavor-drawer-overlay');
 var returnFocus=null,previousOverflow='',inertSiblings=[];
 function focusables(){return drawer?Array.from(drawer.querySelectorAll('a[href],button:not([disabled]),input:not([disabled])')).filter(function(el){return el.getClientRects().length;}):[];}
 function openDrawer(){
  if(!drawer||!overlay||returnFocus)return;
  returnFocus=document.activeElement;previousOverflow=document.body.style.overflow;
  drawer.removeAttribute('inert');drawer.classList.add('is-active');overlay.classList.add('is-active');drawer.setAttribute('aria-hidden','false');
  if(toggle)toggle.setAttribute('aria-expanded','true');
  Array.from(document.body.children).forEach(function(el){if(el!==drawer&&el!==overlay&&!['SCRIPT','STYLE','LINK'].includes(el.tagName)&&!el.hasAttribute('inert')){el.setAttribute('inert','');inertSiblings.push(el);}});
  document.body.style.overflow='hidden';
  if(close)close.focus({preventScroll:true});
 }
 function closeDrawer(){
  if(!drawer||!returnFocus)return;
  drawer.classList.remove('is-active');if(overlay)overlay.classList.remove('is-active');drawer.setAttribute('aria-hidden','true');drawer.setAttribute('inert','');
  if(toggle)toggle.setAttribute('aria-expanded','false');
  inertSiblings.forEach(function(el){el.removeAttribute('inert');});inertSiblings=[];document.body.style.overflow=previousOverflow;
  var target=returnFocus;returnFocus=null;if(target&&target.isConnected)target.focus({preventScroll:true});
 }
 if(drawer)drawer.setAttribute('inert','');
 if(toggle)toggle.addEventListener('click',openDrawer);
 if(close)close.addEventListener('click',closeDrawer);
 if(overlay)overlay.addEventListener('click',closeDrawer);
 if(drawer)drawer.querySelectorAll('a').forEach(function(link){link.addEventListener('click',closeDrawer);});
 document.addEventListener('keydown',function(event){
  if(!returnFocus)return;
  if(event.key==='Escape'){event.preventDefault();closeDrawer();return;}
  if(event.key!=='Tab')return;
  var items=focusables();if(!items.length)return;
  if(event.shiftKey&&document.activeElement===items[0]){event.preventDefault();items[items.length-1].focus();}
  else if(!event.shiftKey&&document.activeElement===items[items.length-1]){event.preventDefault();items[0].focus();}
 });
 window.matchMedia('(min-width:992px)').addEventListener('change',function(event){if(event.matches)closeDrawer();});
 var header=document.getElementById('flavor-site-header');
 if(header)window.addEventListener('scroll',function(){header.classList.toggle('is-scrolled',window.scrollY>20);},{passive:true});
 var mobileCart=document.getElementById('flavor-mobile-cart-btn');
 if(mobileCart)mobileCart.addEventListener('click',function(event){
  event.preventDefault();
  if(window.FlavorCartUI){window.FlavorCartUI.open(mobileCart);return;}
  var cartToggle=document.getElementById('flavor-cart-toggle');if(cartToggle){cartToggle.click();return;}
  var url=new URL((window.flavorData&&window.flavorData.menuUrl)||'/menu/',location.href);url.searchParams.set('open_cart','1');location.href=url.href;
 });
 window.flavorToast=function(message,type){
  var root=document.getElementById('flavor-toast-container');
  if(!root){root=document.createElement('div');root.id='flavor-toast-container';document.body.appendChild(root);}
  var toast=document.createElement('div');toast.className='flavor-ui-toast';toast.dataset.type=type||'success';toast.textContent=message;
  if(window.FlavorUI)window.FlavorUI.announce(message);else{toast.setAttribute('role',type==='error'?'alert':'status');}
  root.appendChild(toast);
  window.setTimeout(function(){toast.remove();if(!root.children.length)root.remove();},4500);
 };
})();
