const {JSDOM}=require('jsdom');const fs=require('fs');
const html=fs.readFileSync('/mnt/user-data/outputs/omeka-page-builder.html','utf8');
const dom=new JSDOM(html,{runScripts:'dangerously',pretendToBeVisual:true,beforeParse(w){w.matchMedia=()=>({matches:false});}});
const d=dom.window.document;
setTimeout(()=>{
 const blks=d.querySelectorAll('#grid .blk');console.log('blocks',blks.length,'palette',d.querySelectorAll('.pal').length);
 d.querySelectorAll('.pal')[1].dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
 console.log('after add',d.querySelectorAll('#grid .blk').length,'props:',d.querySelector('.p-head h2').textContent);
 d.querySelector('.blk.selected .tools button[aria-label="Duplicate"]').click();
 console.log('after dup',d.querySelectorAll('#grid .blk').length);
 d.getElementById('undo').click();console.log('after undo',d.querySelectorAll('#grid .blk').length);
 d.querySelector('.seg button[data-dev="phone"]').click();console.log('page width',d.getElementById('page').style.width);
 d.getElementById('dataBtn').click();console.log('json entries',JSON.parse(d.getElementById('dataPre').textContent).length);
},300);
