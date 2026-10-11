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
