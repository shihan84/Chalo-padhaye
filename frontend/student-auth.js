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
    <button id="showStudentSetup" class="hidden">First-time student setup</button>
    <div id="studentSetup" class="student-setup hidden">
      <h3>Create student account</h3>
      <p class="small">Use an email the family controls, create a password, and enter the invite code generated from the Parent Dashboard.</p>
      <input id="studentSetupEmail" type="email" placeholder="Student login email"><br><br>
      <input id="studentSetupPassword" type="password" minlength="6" placeholder="Create password"><br><br>
      <input id="studentInviteCode" maxlength="12" placeholder="Invite code"><br><br>
      <button id="createStudentLogin" class="primary">Create & link student login</button>
    </div>
    <p id="loginStatus" class="status"></p>`;
  const style=document.createElement('style');style.textContent=`.login-tabs{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:16px}.student-setup{margin-top:15px;padding-top:15px;border-top:1px solid var(--line)}.student-login-card code{font-size:22px;letter-spacing:2px;font-weight:800;background:#f4f6fb;padding:7px 10px;border-radius:9px}.student-mode .studentbar{grid-template-columns:1fr}.student-mode #student{display:none}`;document.head.appendChild(style);
  $('#parentLoginTab').onclick=()=>setLoginMode('parent');
  $('#studentLoginTab').onclick=()=>setLoginMode('student');
  $('#showStudentSetup').onclick=()=>$('#studentSetup').classList.toggle('hidden');
  $('#createStudentLogin').onclick=createStudentLogin;
}

function setLoginMode(mode){
  cpLoginMode=mode;
  $('#parentLoginTab').classList.toggle('active',mode==='parent');
  $('#studentLoginTab').classList.toggle('active',mode==='student');
  $('#loginTitle').textContent=mode==='student'?'Student Login':'Parent Login';
  $('#loginHelp').textContent=mode==='student'?'Open only your own learning profile.':'Open your children\'s private learning profiles.';
  $('#email').placeholder=mode==='student'?'Student login email':'Parent email';
  $('#showStudentSetup').classList.toggle('hidden',mode!=='student');
  if(mode!=='student')$('#studentSetup').classList.add('hidden');
  $('#loginStatus').textContent='';
}

async function authUser(){
  const r=await fetch(`${CFG.supabase_url}/auth/v1/user`,{headers:{apikey:CFG.supabase_anon_key,Authorization:`Bearer ${accessToken}`}});
  if(!r.ok)throw new Error('Login session could not be verified.');
  return r.json();
}

async function claimStudentInvite(code){
  const r=await fetch(`${CFG.supabase_url}/rest/v1/rpc/claim_student_invite`,{method:'POST',headers:{apikey:CFG.supabase_anon_key,Authorization:`Bearer ${accessToken}`,'Content-Type':'application/json'},body:JSON.stringify({p_code:String(code||'').trim().toUpperCase()})});
  let j=null;try{j=await r.json()}catch{}
  if(!r.ok)throw new Error((j&&j.message)||'Invite code is invalid, expired, or student login migration is not installed.');
  return j;
}

async function createStudentLogin(){
  const email=$('#studentSetupEmail').value.trim(),password=$('#studentSetupPassword').value,code=$('#studentInviteCode').value.trim();
  if(!email||password.length<6||!code)return $('#loginStatus').textContent='Enter student email, password (6+ characters), and invite code.';
  $('#loginStatus').textContent='Creating student account...';
  try{
    const r=await fetch(`${CFG.supabase_url}/auth/v1/signup`,{method:'POST',headers:{apikey:CFG.supabase_anon_key,'Content-Type':'application/json'},body:JSON.stringify({email,password})});
    const j=await r.json();if(!r.ok)throw new Error(j.msg||j.error_description||'Could not create student account');
    localStorage.setItem('cp_pending_student_invite',code.toUpperCase());
    localStorage.setItem('cp_pending_student_email',email);
    if(j.access_token){
      storeSession(j);await claimStudentInvite(code);localStorage.removeItem('cp_pending_student_invite');localStorage.removeItem('cp_pending_student_email');
      cpLoginMode='student';await loadStudents();showLoggedInApp();
    }else{
      $('#loginStatus').textContent='Account created. Verify the student email if Supabase asks for confirmation, then use Student Login. The invite code is saved on this device.';
      $('#email').value=email;$('#password').value='';$('#studentSetup').classList.add('hidden');
    }
  }catch(e){$('#loginStatus').textContent=e.message}
}

async function maybeClaimPendingInvite(user){
  const code=localStorage.getItem('cp_pending_student_invite'),email=localStorage.getItem('cp_pending_student_email');
  if(!code||!user)return;
  if(email&&String(user.email||'').toLowerCase()!==email.toLowerCase())return;
  await claimStudentInvite(code);
  localStorage.removeItem('cp_pending_student_invite');localStorage.removeItem('cp_pending_student_email');
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
  const user=await authUser();cpAuthUserId=user.id||'';
  try{await maybeClaimPendingInvite(user)}catch(e){if(cpLoginMode==='student')throw e}
  let url=`${CFG.supabase_url}/rest/v1/students?select=id,full_name,grade,medium,board,student_user_id&order=created_at.asc`;
  let r=await authFetch(url,{headers:{apikey:CFG.supabase_anon_key}});
  if(!r.ok){
    // Keep the parent app usable before 006_student_login.sql is installed.
    r=await authFetch(`${CFG.supabase_url}/rest/v1/students?select=id,full_name,grade,medium,board&order=created_at.asc`,{headers:{apikey:CFG.supabase_anon_key}});
    if(!r.ok)throw new Error('Could not load student profiles');
  }
  students=await r.json();
  if(!students.length)throw new Error(cpLoginMode==='student'?'This login is not linked to a student profile yet. Use the parent-generated invite code.':'No student profiles found');
  cpAccountRole=students.some(s=>s.student_user_id&&s.student_user_id===cpAuthUserId)?'student':'parent';
  if(cpLoginMode==='student'&&cpAccountRole!=='student')throw new Error('This is a parent account. Use Parent Login.');
  if(cpLoginMode==='parent'&&cpAccountRole==='student')throw new Error('This is a student account. Use Student Login.');
  if(cpAccountRole==='student')students=students.filter(s=>s.student_user_id===cpAuthUserId);
  $('#student').innerHTML='';students.forEach(s=>$('#student').add(new Option(`${s.full_name} — Grade ${s.grade} — ${s.board||''}`,s.id)));
  curriculum=preferredSchoolCurriculum();updateModeUI();applyAccountRole();await fillSubjects();loadResume();
}

function showLoggedInApp(){
  $('#login').classList.add('hidden');$('#app').classList.remove('hidden');$('#logout').classList.remove('hidden');applyAccountRole();
}

async function login(){
  $('#loginStatus').textContent='Signing in...';
  try{
    const r=await fetch(`${CFG.supabase_url}/auth/v1/token?grant_type=password`,{method:'POST',headers:{apikey:CFG.supabase_anon_key,'Content-Type':'application/json'},body:JSON.stringify({email:$('#email').value.trim(),password:$('#password').value})});
    const j=await r.json();if(!r.ok)throw new Error(j.error_description||j.msg||'Login failed');storeSession(j);await loadStudents();showLoggedInApp();
  }catch(e){accessToken='';sessionStorage.removeItem('cp_token');$('#loginStatus').textContent=e.message}
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
    const r=await fetch(`${CFG.supabase_url}/rest/v1/rpc/create_student_invite`,{method:'POST',headers:{apikey:CFG.supabase_anon_key,Authorization:`Bearer ${accessToken}`,'Content-Type':'application/json'},body:JSON.stringify({p_student_id:p.id})});
    const j=await r.json();if(!r.ok)throw new Error(j.message||'Could not create invite. Run 006_student_login.sql first.');
    const row=Array.isArray(j)?j[0]:j,code=row&&row.invite_code;if(!code)throw new Error('Invite code was not returned.');
    out.innerHTML=`Student: <b>${esc(p.full_name)}</b><br><br>Invite code: <code>${esc(code)}</code><br><span class="small">Expires ${row.expires_at?new Date(row.expires_at).toLocaleString():'in 24 hours'}.</span>`;
  }catch(e){out.textContent=e.message}
}

installStudentLoginUI();ensureStudentLoginCard();
