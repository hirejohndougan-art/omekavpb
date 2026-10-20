const {JSDOM}=require('jsdom');const fs=require('fs');
const html=fs.readFileSync('/mnt/user-data/outputs/omeka-page-builder-v2.html','utf8');
const dom=new JSDOM(html,{runScripts:'dangerously',pretendToBeVisual:true,beforeParse(w){w.matchMedia=()=>({matches:false});}});
const w=dom.window,d=w.document;
const errs=[];w.addEventListener('error',e=>errs.push(e.message));
setTimeout(()=>{
 const q=s=>d.querySelectorAll(s).length;
 console.log('sections',q('#secs > .sec'),'cols',q('.col'),'blocks',q('.blk'),'resize handles',q('.resize'));
 // select second section's col 1 via pointerdown
 const col=d.querySelectorAll('.sec')[1].querySelectorAll('.col')[0];
 col.dispatchEvent(new w.PointerEvent('pointerdown',{bubbles:true}));
 console.log('panel:',d.querySelector('.p-head h2').textContent);
 // tap add Heading into selected col
 [...d.querySelectorAll('.pal')].find(b=>b.textContent.startsWith('Heading')).click();
 console.log('blocks now',q('.blk'),'panel:',d.querySelector('.p-head h2').textContent, 'path:',d.querySelector('.path').textContent);
 // select section via path
 d.querySelector('.path button').click();
 console.log('panel:',d.querySelector('.p-head h2').textContent);
 // apply 3-col preset
 d.querySelector('.presets button[aria-label="3 columns"]').click();
 console.log('cols in sec2',d.querySelectorAll('.sec')[1].querySelectorAll('.col').length);
 d.querySelector('.presets button[aria-label="1 column"]').click();
 console.log('after 1col, cols',d.querySelectorAll('.sec')[1].querySelectorAll('.col').length,'blocks',q('.blk'));
 d.querySelector('.presets button[aria-label="Wide + narrow"]').click();
 // widen col1
 d.querySelector('.wrow button[aria-label="Make column 1 wider"]').click();
 console.log('widths', [...d.querySelectorAll('.sec')[1].querySelectorAll('.col')].map(c=>c.style.gridColumn).join(','));
 d.querySelector('button.btn.small').click(); // add column
 console.log('after add col',d.querySelectorAll('.sec')[1].querySelectorAll('.col').length);
 [...d.querySelectorAll('.pal')].find(b=>b.textContent.startsWith('2 columns')).click();
 console.log('sections',q('#secs > .sec'));
 d.getElementById('undo').click(); console.log('after undo sections',q('#secs > .sec'));
 d.querySelector('.seg button[data-dev="phone"]').click(); console.log('handles on phone',q('.resize'));
 d.getElementById('dataBtn').click(); const j=JSON.parse(d.getElementById('dataPre').textContent); console.log('export sections',j.length, 'groups in s2', j[1].groups.length);
 console.log('errors',errs);
},300);
