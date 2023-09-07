System.register(["./index-legacy-bc110cf2.js"],(function(t,e){"use strict";var n,r,a;return{setters:[t=>{n=t.a9,r=t.aa,a=t.ab}],execute:function(){
/*!
       * (C) Ionic http://ionicframework.com - MIT License
       */
t("createSwipeBackGesture",((t,e,i,s,o)=>{const c=t.ownerDocument.defaultView;let u=n(t);const l=t=>u?-t.deltaX:t.deltaX;return r({el:t,gestureName:"goback-swipe",gesturePriority:40,threshold:10,canStart:r=>(u=n(t),(t=>{const{startX:e}=t;return u?e>=c.innerWidth-50:e<=50})(r)&&e()),onStart:i,onMove:t=>{const e=l(t)/c.innerWidth;s(e)},onEnd:t=>{const e=l(t),n=c.innerWidth,r=e/n,i=(t=>u?-t.velocityX:t.velocityX)(t),s=i>=0&&(i>.2||e>n/2),d=(s?1-r:r)*n;let h=0;if(d>5){const t=d/Math.abs(i);h=Math.min(t,540)}o(s,r<=0?.01:a(0,r,.9999),h)}})}))}}}));
