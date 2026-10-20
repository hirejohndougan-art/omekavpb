const {JSDOM}=require('jsdom');const fs=require('fs');
const html=fs.readFileSync('/mnt/user-data/outputs/omeka-page-builder-v2.html','utf8');
const dom=new JSDOM(html,{url:'https://example.test/',runScripts:'dangerously',pretendToBeVisual:true,beforeParse(w){w.matchMedia=()=>({matches:false});}});
const w=dom.window,d=w.document;const errs=[];w.addEventListener('error',e=>errs.push(e.message));
setTimeout(()=>{
 const t=d.getElementById('sideToggle');
 console.log('title',t.title);
 t.click();console.log('collapsed',d.body.classList.contains('side-collapsed'),t.getAttribute('aria-label'),'stored',w.localStorage.getItem('omeka-page-builder-sidebar'));
 const blk=d.querySelector('.blk');blk.dispatchEvent(new w.PointerEvent('pointerdown',{bubbles:true}));
 d.querySelector('.blk.selected .set-btn').click();
 console.log('after settings btn collapsed?',d.body.classList.contains('side-collapsed'),'props visible',!d.getElementById('propsView').hidden);
 d.dispatchEvent(new w.KeyboardEvent('keydown',{key:'\\',ctrlKey:true,bubbles:true}));
 console.log('after ctrl+\\ collapsed',d.body.classList.contains('side-collapsed'));
 console.log('errors',errs);
},300);
