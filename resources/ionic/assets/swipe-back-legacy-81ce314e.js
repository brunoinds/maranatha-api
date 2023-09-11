System.register(["./index-legacy-4514fca9.js"],(function(t,e){"use strict";var n,r,i;return{setters:[t=>{n=t.ao,r=t.ap,i=t.aq}],execute:function(){
/*!
       * (C) Ionic http://ionicframework.com - MIT License
       */
t("createSwipeBackGesture",((t,e,o,s,a)=>{const c=t.ownerDocument.defaultView;let u=n(t);const l=t=>u?-t.deltaX:t.deltaX;return r({el:t,gestureName:"goback-swipe",gesturePriority:40,threshold:10,canStart:r=>(u=n(t),(t=>{const{startX:e}=t;return u?e>=c.innerWidth-50:e<=50})(r)&&e()),onStart:o,onMove:t=>{const e=l(t)/c.innerWidth;s(e)},onEnd:t=>{const e=l(t),n=c.innerWidth,r=e/n,o=(t=>u?-t.velocityX:t.velocityX)(t),s=o>=0&&(o>.2||e>n/2),d=(s?1-r:r)*n;let h=0;if(d>5){const t=d/Math.abs(o);h=Math.min(t,540)}a(s,r<=0?.01:i(0,r,.9999),h)}})}))}}}));
