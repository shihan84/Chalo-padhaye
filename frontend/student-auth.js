let cpLoginMode='parent',cpAccountRole='parent',cpAuthUserId='';

function installStudentLoginUI(){
  const box=$('#login');if(!box)return;
  box.innerHTML=`
    <div class="login-tabs"><button id="parentLoginTab" class="active">👨‍👩‍👧 Parent</button><button id="studentLoginTab">🎒 Student</button></div>
    <h2 id="loginTitle">Parent Login</h2>
    <p id="loginHelp" class="sub">Open your children's private learning profiles.</p>
    <input id="email" type="email" placeholder="Parent email" autocomplete="email"><br><br>
    <input id="password" type="password" placeholder="Password" autocomplete="current-password"><br><br>
    <button id="loginBtn" class="primary">Login</button>
    <div id="parentRegistration"><input id="parentName" maxlength="160" placeholder="Parent name (optional)"><button id="registerParent">Create parent account</button></div>
    <button id="showStudentSetup" class="hidden">First-time student setup</button>
    <div id="studentSetup" class="student-setup hidden">
      <h3>Create student account</h3>
      <p class="small">Use an email the family controls, create a password, and enter the invite code generated from the Parent Dashboard.</p>
      <input id="studentSetupEmail" type="email" placeholder="Student login email"><br><br>
      <input id="studentSetupPassword" type="password" minlength="8" placeholder="Create password"><br><br>
      <input id="studentInviteCode" maxlength="32" placeholder="Invite code"><br><br>
      <button id="createStudentLogin" class="primary">Create & link student login</button>
    </div>
    <p id="loginStatus" class="status"></p>`;
  const style=document.createElement('style');style.textContent=`.login-tabs{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:16px}.student-setup{margin-top:15px;padding-top:15px;border-top:1px solid var(--line)}.student-login-card code{font-size:22px;letter-spacing:2px;font-weight:800;background:#f4f6fb;padding:7px 10px;border-radius:9px}.student-mode .studentbar{grid-template-columns:1fr}.student-mode #student{display:none}`;document.head.appendChild(style);
  $('#parentLoginTab').onclick=()=>setLoginMode('parent');
  $('#studentLoginTab').onclick=()=>setLoginMode('student');
  $('#showStudentSetup').onclick=()=>$('#studentSetup').classList.toggle('hidden');
  $('#createStudentLogin').onclick=createStudentLogin;
  $('#registerParent').onclick=registerParent;
}

function setLoginMode(mode){
  cpLoginMode=mode;
  $('#parentRegistration').classList.toggle('hidden',mode!=='parent');
  $('#parentLoginTab').classList.toggle('active',mode==='parent');
  $('#studentLoginTab').classList.toggle('active',mode==='student');
  $('#loginTitle').textContent=mode==='student'?'Student Login':'Parent Login';
  $('#loginHelp').textContent=mode==='student'?'Open only your own learning profile.':'Open your children\'s private learning profiles.';
  $('#email').placeholder=mode==='student'?'Student login email':'Parent email';
  $('#showStudentSetup').classList.toggle('hidden',mode!=='student');
  if(mode!=='student')$('#studentSetup').classList.add('hidden');
  $('#loginStatus').textContent='';
}

async function mysqlRequest(path, body, authenticated=false){
  const opts=body===undefined?{}:{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)};
  const r=await (authenticated?authFetch(path,opts):fetch(path,opts));
  const j=await r.json();if(!r.ok)throw new Error(j.error||j.detail||'Request failed');return j;
}
async function authUser(){return (await mysqlRequest('/api/auth/me',undefined,true)).user}
async function createStudentLogin(){
  $('#loginStatus').textContent='Creating student account...';
  try{const j=await mysqlRequest('/api/auth/student/register',{email:$('#studentSetupEmail').value.trim(),password:$('#studentSetupPassword').value,invite_code:$('#studentInviteCode').value.trim()});storeSession(j);cpLoginMode='student';await loadStudents();showLoggedInApp()}catch(e){$('#loginStatus').textContent=e.message}
}
async function registerParent(){
  $('#loginStatus').textContent='Creating parent account...';
  try{const j=await mysqlRequest('/api/auth/register',{email:$('#email').value.trim(),password:$('#password').value,full_name:$('#parentName').value.trim()});storeSession(j);cpLoginMode='parent';await loadStudents();showLoggedInApp()}catch(e){$('#loginStatus').textContent=e.message}
}
async function createChild(){
  try{await mysqlRequest('/api/students',{full_name:$('#childName').value.trim(),grade:Number($('#childGrade').value),board:$('#childBoard').value,medium:'English'},true);$('#childName').value='';await loadStudents();showTab('learn');toast('Child profile created.')}catch(e){toast(e.message)}
}

function applyAccountRole(){
  document.body.classList.toggle('student-mode',cpAccountRole==='student');
  const parentNav=$('.nav button[data-tab="parent"]');if(parentNav)parentNav.classList.toggle('hidden',cpAccountRole==='student');
  if(cpAccountRole==='student'){
    $('#subtitle').textContent=`Student Learning • ${(current()||{}).full_name||''}`;
    if(!$('#parentPanel').classList.contains('hidden'))showTab('learn');
  }
}

async function loadStudents(){
  const user=await authUser();cpAuthUserId=user.id;cpAccountRole=user.role;
  const j=await mysqlRequest('/api/students',undefined,true);students=j.students||[];
  $('#student').innerHTML='';students.forEach(s=>$('#student').add(new Option(`${s.full_name} — Grade ${s.grade} — ${s.board||''}`,s.id)));
  $('#childProfileCard').classList.toggle('hidden',cpAccountRole!=='parent');
  if(cpAccountRole==='student'&&!students.length)throw new Error('This account has no linked learning profile.');
  curriculum=preferredSchoolCurriculum();updateModeUI();applyAccountRole();
  if(students.length){await fillSubjects();loadResume()}else{showTab('parent');toast('Create your first child profile to begin.')}
}

function showLoggedInApp(){
  $('#login').classList.add('hidden');$('#app').classList.remove('hidden');$('#logout').classList.remove('hidden');applyAccountRole();
}

async function login(){
  $('#loginStatus').textContent='Signing in...';
  try{const j=await mysqlRequest('/api/auth/login',{email:$('#email').value.trim(),password:$('#password').value});storeSession(j);await loadStudents();showLoggedInApp()}catch(e){clearSession();$('#loginStatus').textContent=e.message}
}

function ensureStudentLoginCard(){
  if($('#studentLoginCard'))return;
  const card=document.createElement('div');card.id='studentLoginCard';card.className='card student-login-card no-print';
  card.innerHTML=`<div class="section-title"><div><h2>Student Login</h2><p class="sub">Give the selected child a separate login without exposing other children or the Parent Dashboard.</p></div><button id="makeStudentInvite" class="primary">Generate invite code</button></div><div id="studentInviteResult" class="notice hidden"></div><p class="small">The code expires after 24 hours. On the child's device choose Student Login → First-time student setup, create a student email/password account, and enter this code.</p>`;
  $('#parentPanel').insertBefore(card,$('#parentPanel').firstChild);
  $('#makeStudentInvite').onclick=generateStudentInvite;
}

async function generateStudentInvite(){
  if(cpAccountRole!=='parent')return;
  const p=current();if(!p)return;
  const out=$('#studentInviteResult');out.classList.remove('hidden');out.textContent='Generating secure invite...';
  try{
    const row=await mysqlRequest(`/api/students/${encodeURIComponent(p.id)}/invite`,{},true),code=row.invite_code;
    if(!code)throw new Error('Invite code was not returned.');
    out.innerHTML=`Student: <b>${esc(p.full_name)}</b><br><br>Invite code: <code>${esc(code)}</code><br><span class="small">Expires ${row.expires_at?new Date(row.expires_at).toLocaleString():'in 24 hours'}.</span>`;
  }catch(e){out.textContent=e.message}
}

function installChildProfileUI(){
  const card=document.createElement('div');card.id='childProfileCard';card.className='card no-print';
  card.innerHTML=`<h2>Add child profile</h2><div class="formgrid"><input id="childName" maxlength="160" placeholder="Child name"><input id="childGrade" type="number" min="1" max="12" value="3"><select id="childBoard"><option>Maharashtra State Board</option><option>CBSE</option><option>Telangana SSC</option><option>NIOS</option></select></div><button id="createChild" class="primary">Create profile</button>`;
  $('#parentPanel').insertBefore(card,$('#parentPanel').firstChild);$('#createChild').onclick=createChild;
}
installStudentLoginUI();ensureStudentLoginCard();installChildProfileUI();
