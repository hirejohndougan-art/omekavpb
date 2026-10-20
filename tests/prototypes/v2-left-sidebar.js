const {JSDOM}=require('jsdom');const fs=require('fs');
const html=fs.readFileSync('/mnt/user-data/outputs/omeka-page-builder-v2.html','utf8');
const dom=new JSDOM(html,{runScripts:'dangerously',pretendToBeVisual:true,beforeParse(w){w.matchMedia=()=>({matches:false});}});
const w=dom.window,d=w.document;const errs=[];w.addEventListener('error',e=>errs.push(e.message));
const vis=()=>`pal:${!d.getElementById('palView').hidden} props:${!d.getElementById('propsView').hidden}`;
setTimeout(()=>{
 console.log('right panel exists:',!!d.getElementById('props'),'start',vis());
 const blk=d.querySelector('.blk');blk.dispatchEvent(new w.PointerEvent('pointerdown',{bubbles:true}));
 console.log('after select',vis(),d.querySelector('#propsView .p-head h2').textContent);
 d.querySelector('.back-btn').click();console.log('after back',vis(),'selbar:',d.getElementById('selBar').textContent);
 d.querySelector('.selbar button').click();console.log('after selbar',vis());
 d.querySelector('.back-btn').click();
 blk.dispatchEvent(new w.PointerEvent('pointerdown',{bubbles:true}));console.log('reclick same',vis());
 d.querySelector('.path button').click();console.log('path->section',vis(),d.querySelector('#propsView .p-head h2').textContent);
 d.dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));console.log('after esc',vis(),'selbar empty:',d.getElementById('selBar').textContent==='');
 [...d.querySelectorAll('.pal')].find(b=>b.textContent.startsWith('Heading')).click();console.log('tap add keeps palette',vis());
 console.log('errors',errs);
},300);
