const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function setup(){
  const elements=new Map(),calls=[];
  const el=id=>{if(!elements.has(id))elements.set(id,{value:'',innerHTML:'',textContent:'',classList:{toggle(){},add(){},remove(){},contains(){return true}},add(o){this.value ||= o.value}});return elements.get(id)};
  const storage=new Map();
  const c=vm.createContext({console,Option:function(text,value){this.value=value},$:el,$$:()=>[],document:{body:{classList:{toggle(){}}}},sessionStorage:{setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},localStorage:{removeItem(){}},accessToken:'token',refreshToken:'',students:[],curriculum:'maharashtra',preferredSchoolCurriculum:()=> 'maharashtra',updateModeUI(){},fillSubjects:async()=>{},loadResume(){},showTab(){},toast(){},storeSession(j){c.accessToken=j.access_token},clearSession(){c.accessToken=''},esc:String,
    fetch:async(url,opts)=>{calls.push({url,opts});return {ok:true,json:async()=>({user:{id:'parent',role:'parent'},students:[]})}},authFetch:async(url,opts)=>c.fetch(url,opts)});
  let code=fs.readFileSync('frontend/student-auth.js','utf8').replace('installStudentLoginUI();ensureStudentLoginCard();installChildProfileUI();','');vm.runInContext(code,c);
  return {c,el,calls};
}
test('empty parent can enter app and create first profile',async()=>{
 const {c,el,calls}=setup();await c.loadStudents();assert.equal(c.students.length,0);
 el('#childName').value='A Child';el('#childGrade').value='3';el('#childBoard').value='CBSE';await c.createChild();
 const call=calls.find(x=>x.url==='/api/students'&&x.opts.method==='POST');assert.equal(JSON.parse(call.opts.body).full_name,'A Child');
});
test('student enrollment submits complete 32-character invite to MySQL',async()=>{
 const {c,el,calls}=setup();el('#studentInviteCode').value='A'.repeat(32);el('#studentSetupEmail').value='child@example.com';el('#studentSetupPassword').value='password123';await c.createStudentLogin();
 const call=calls.find(x=>x.url==='/api/auth/student/register');assert.equal(JSON.parse(call.opts.body).invite_code,'A'.repeat(32));
});
test('server error is shown to user',async()=>{
 const {c}=setup();c.fetch=async()=>({ok:false,json:async()=>({error:'Invite expired'})});await assert.rejects(c.mysqlRequest('/api/auth/student/register',{}),/Invite expired/);
});
test('all loaded browser code uses MySQL auth and logout revokes server token',()=>{
 for(const file of fs.readdirSync('frontend').filter(x=>x.endsWith('.js'))){const src=fs.readFileSync('frontend/'+file,'utf8');assert.ok(!src.includes('CFG.supabase_'),file);new vm.Script(src)}
 const extras=fs.readFileSync('frontend/extras.js','utf8');assert.ok(extras.includes("mysqlRequest('/api/auth/logout',{},true)"));
 const auth=fs.readFileSync('frontend/student-auth.js','utf8');assert.ok(auth.includes('maxlength="32"'));assert.ok(auth.includes('minlength="8"'));
});
function syllabusSetup(){
 const elements=new Map();
 const el=id=>{if(!elements.has(id)){const classes=new Set();elements.set(id,{value:'10',textContent:'',innerHTML:'',classes,classList:{add:x=>classes.add(x),remove:x=>classes.delete(x),toggle:(x,force)=>force?classes.add(x):classes.delete(x)}})}return elements.get(id)};
 const c=vm.createContext({URL,$:el,curriculum:'telangana',fetch:async()=>({ok:true,json:async()=>({notice:'Reference edition only',directory_url:'https://www.scert.telangana.gov.in/DisplayContent.aspx',syllabus_url:'javascript:alert(1)',subjects:[{label:'Mathematics',chapters:['<img src=x onerror=alert(1)>'],source_edition:'Archived book',source_url:'https://scert.telangana.gov.in/PDF/publication/ebooks/10EM_MAT.pdf'}]})})});
 vm.runInContext(fs.readFileSync('frontend/core.js','utf8').split('\n').find(x=>x.startsWith('function esc(')),c);
 vm.runInContext(fs.readFileSync('frontend/lessons.js','utf8'),c);
 return {c,el};
}
test('Telangana is labelled correctly and has a separate usable syllabus browser',async()=>{
 const {c,el}=syllabusSetup();c.updateModeUI();assert.ok(el('#telanganaMode').classes.has('active'));assert.match(el('#subtitle').textContent,/Telangana/);assert.ok(!el('#syllabusCard').classes.has('hidden'));
 await c.loadSyllabus();assert.match(el('#syllabusList').innerHTML,/reference units/);assert.match(el('#syllabusList').innerHTML,/&lt;img/);assert.ok(!el('#syllabusList').innerHTML.includes('<img'));assert.ok(!el('#syllabusSources').innerHTML.includes('javascript:'));
 c.curriculum='cbse';c.updateModeUI();assert.ok(el('#syllabusCard').classes.has('hidden'));assert.ok(el('#cbseMode').classes.has('active'));
});
test('official links reject unsafe schemes, hosts and credentials',()=>{
 const {c}=syllabusSetup();for(const u of ['javascript:alert(1)','https://evil.test/','https://scert.telangana.gov.in.evil.test/','https://a:b@scert.telangana.gov.in/'])assert.equal(c.scertLink(u,'Source'),'');
 assert.match(c.scertLink('https://scert.telangana.gov.in/PDF/book.pdf','Source'),/noopener noreferrer/);
});
test('changing class while a request is in flight keeps the newer syllabus',async()=>{
 const {c,el}=syllabusSetup();const pending=[];c.fetch=()=>new Promise(resolve=>pending.push(resolve));
 const first=c.loadSyllabus();el('#syllabusGrade').value='3';const second=c.loadSyllabus();
 const response=notice=>({ok:true,json:async()=>({notice,subjects:[]})});pending[1](response('Class 3'));await second;pending[0](response('Class 10'));await first;
 assert.equal(el('#syllabusNotice').textContent,'Class 3');
});
test('closing a session uses its original child after the selector changes',async()=>{
 const calls=[];const c=vm.createContext({sessionId:'old-session',sessionStudentId:'child-a',current:()=>({id:'child-b'}),authFetch:async(url,o)=>{calls.push(JSON.parse(o.body))}});
 vm.runInContext(fs.readFileSync('frontend/lessons.js','utf8').split('\n')[0],c);await c.endSession();
 assert.equal(calls[0].student_id,'child-a');assert.equal(c.sessionId,null);assert.equal(c.sessionStudentId,null);
});
test('weekly planner shows a server error instead of an empty goals list',async()=>{
 const elements=new Map(),el=id=>{if(!elements.has(id))elements.set(id,{innerHTML:'old',textContent:''});return elements.get(id)};
 const c=vm.createContext({$:el,weekRequest:0,current:()=>({id:'a',grade:3}),curriculum:'telangana',esc:String,authFetch:async()=>({ok:false,json:async()=>({error:'Session expired'})})});
 const line=fs.readFileSync('frontend/extras.js','utf8').split('\n').find(x=>x.startsWith('async function loadWeek('));vm.runInContext(line,c);await c.loadWeek();
 assert.match(el('#weekPlan').innerHTML,/Session expired/);assert.equal(el('#goalList').innerHTML,'');
});
test('a delayed tutor response cannot enter the newly selected child chat',async()=>{
 const elements=new Map(),el=id=>{if(!elements.has(id))elements.set(id,{value:id==='#subject'?'math':'',innerHTML:'',textContent:'',disabled:false,classList:{add(){},remove(){}},focus(){}});return elements.get(id)};
 let resolve;const calls=[],messages=[];
 const c=vm.createContext({$:el,$$:()=>[],chatBusy:false,contextGeneration:0,accessToken:'token',curriculum:'telangana',activity:'teach',activeLesson:null,sessionId:null,sessionStudentId:null,current:()=>({id:'child-a'}),stopVoice(){},add:t=>messages.push(t),authFetch:(u,o)=>{calls.push({u,body:JSON.parse(o.body)});return u==='/api/chat'?new Promise(r=>resolve=r):Promise.resolve({ok:true})}});
 vm.runInContext(fs.readFileSync('frontend/interaction.js','utf8').replace(/installInteractionPanel\(\);\s*$/,''),c);
 const pending=c.sendText('hello');c.current=()=>({id:'child-b'});c.contextGeneration++;
 resolve({ok:true,json:async()=>({session_id:'old-reply-session',text:'Late reply'})});await pending;
 assert.ok(!messages.includes('Late reply'));assert.equal(c.sessionId,null);assert.equal(calls.at(-1).body.student_id,'child-a');assert.equal(calls.at(-1).u,'/api/end-session');
});
function practiceSetup(){
 const elements=new Map();const el=id=>{if(!elements.has(id))elements.set(id,{value:id==='#practiceUnit'?'class5-fractions':'',innerHTML:'',textContent:'',disabled:false,querySelectorAll:()=>[],focus(){}});return elements.get(id)};
 const c=vm.createContext({$:el,current:()=>({id:'child',grade:5}),accessToken:'token',esc:String,stopVoice(){},authFetch:async()=>({ok:true,json:async()=>({unit:'class5-fractions',history:[],run:null})})});
 vm.runInContext(fs.readFileSync('frontend/practice.js','utf8'),c);return{c,el};
}
test('fraction bars show equal parts and reject oversized or invalid diagrams',()=>{
 const {c}=practiceSetup();const html=c.fractionBar({n:2,d:4});assert.equal((html.match(/fraction-piece/g)||[]).length,4);assert.equal((html.match(/fraction-piece shaded/g)||[]).length,2);assert.match(html,/2 shaded parts.*4 equal parts/);
 assert.equal(c.fractionBar({n:2,d:999}), '');assert.equal(c.fractionBar({n:6,d:4}),'');assert.equal(c.fractionBar({n:1,d:0}),'');assert.match(c.fractionBar({n:0,d:12,unknown:true}),/Unknown number/);
});
test('fraction progress describes specific weak skills using actual totals',()=>{
 const {c}=practiceSetup();const text=c.practiceProgressText([{status:'practised',unassisted:7,total:10,skills:{identify:{correct:1,total:2},compare:{correct:2,total:2}}}],'Class 5 Fractions');
 assert.match(text,/Recognise fractions 1\/2/);assert.match(text,/Compare fractions 2\/2/);assert.match(text,/Next: practise Recognise fractions/);assert.ok(!text.includes('Compare fractions.'));
});
test('switching practice unit ignores the older request response',async()=>{
 const {c,el}=practiceSetup();const pending=[];c.authFetch=()=>new Promise(r=>pending.push(r));
 const first=c.loadPractice('class5-fractions');el('#practiceUnit').value='class3-add-subtract';const second=c.loadPractice();
 const response=unit=>({ok:true,json:async()=>({unit,history:[],run:null})});pending[1](response('class3-add-subtract'));await second;pending[0](response('class5-fractions'));await first;
 assert.match(el('#practiceProgress').textContent,/Foundation Maths/);
});
