const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function fn(file, name) {
  const source=fs.readFileSync(file,'utf8');
  const start=source.search(new RegExp(`(?:async )?function ${name}\\(`));
  assert.ok(start>=0);
  const end=source.indexOf('\nfunction ',start+1);
  const asyncEnd=source.indexOf('\nasync function ',start+1);
  const bounds=[end,asyncEnd].filter(n=>n>=0);
  return source.slice(start,bounds.length?Math.min(...bounds):undefined);
}

test('closing a session after child selection changes uses the session owner',async()=>{
  let sent;
  const ctx={sessionId:'session-a',sessionStudentId:'child-a',current:()=>({id:'child-b'}),authFetch:async(url,opts)=>{sent=JSON.parse(opts.body)}};
  vm.createContext(ctx);vm.runInContext(fn('frontend/lessons.js','endSession'),ctx);
  await ctx.endSession();assert.equal(sent.student_id,'child-a');assert.equal(sent.session_id,'session-a');assert.equal(ctx.sessionId,null);
});

test('unsupported textbook source clears old book URL and link',()=>{
  const nodes=Object.fromEntries(['bookViewerCard','openBookPdf','bookStatus','bookMeta','bookHint','bookFrame'].map(id=>[id,{classList:{remove(){}},removeAttribute(k){this.removed=k}}]));
  const ctx={bookViewerState:{url:'previous-child-book'},ensureBookViewer(){},sourceForLesson:()=>null,$:s=>nodes[s.slice(1)]};
  vm.createContext(ctx);vm.runInContext(fn('frontend/book.js','showBookForLesson'),ctx);ctx.showBookForLesson({id:'new'});
  assert.equal(ctx.bookViewerState.url,'');assert.equal(nodes.openBookPdf.removed,'href');assert.equal(nodes.bookFrame.removed,'src');
});

test('choice question restores text input after a number question',()=>{
  const nodes=Object.fromEntries(['interactionQuestion','interactionPrompt','interactionChoices','interactionHint','q'].map(id=>[id,{type:'number',classList:{remove(){},add(){}},appendChild(){}}]));
  const ctx={currentInteraction:null,installInteractionPanel(){},$:s=>nodes[s.slice(1)],document:{createElement:()=>({})}};
  vm.createContext(ctx);vm.runInContext(fn('frontend/interaction.js','renderInteraction'),ctx);
  ctx.renderInteraction({type:'choice',prompt:'Choose a shape',options:['circle','square']});assert.equal(nodes.q.type,'text');
});

test('late chat response is not shown after switching learning context',async()=>{
  let release,shown=0;
  const nodes=Object.fromEntries(['q','send','status','subject'].map(id=>[id,{value:id==='subject'?'math':'hello'}]));
  const ctx={chatBusy:false,chatContextGeneration:1,current:()=>({id:'child-a'}),$:s=>nodes[s.slice(1)],$$:()=>[],stopVoice(){},clearInteraction(){},add(){shown++},activeLesson:null,curriculum:'maharashtra',activity:'teach',sessionId:null,
    setLearningControlsBusy(){},authFetch:()=>new Promise(resolve=>{release=resolve}),cleanTutorText:v=>v,renderInteraction(){}};
  vm.createContext(ctx);vm.runInContext(fn('frontend/interaction.js','sendText').split('\ninstallInteractionPanel();')[0],ctx);
  const pending=ctx.sendText('4',{display:false});ctx.chatContextGeneration=2;
  release({ok:true,json:async()=>({session_id:'session-a',text:'Private prior reply'})});await pending;
  assert.equal(shown,0);assert.equal(ctx.sessionId,null);assert.equal(ctx.chatBusy,false);
});

test('NIOS viewer uses discovered catalog source rather than static supplement',()=>{
  const ctx={current:()=>({grade:3}),$:()=>({value:'math'}),curriculum:'nios',subjectList:()=>[{id:'math',source_url:'https://cdn.nios.ac.in/math.pdf',source_title:'Official Maths',official:true,supplemental:false}]};
  vm.createContext(ctx);vm.runInContext(fn('frontend/book.js','sourceForLesson'),ctx);
  const source=ctx.sourceForLesson({order:1,title:'Numbers'});
  assert.equal(source.url,'https://cdn.nios.ac.in/math.pdf');assert.equal(source.official,true);assert.equal(source.supplemental,false);
});
