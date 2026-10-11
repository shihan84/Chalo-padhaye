// Synthetic accounts in the disposable CI database only.
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch();const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.goto('http://127.0.0.1:8080/');
  await page.locator('#registerParent').waitFor();
  await page.locator('#email').fill('browser-parent@example.invalid');await page.locator('#password').fill('browser-fixture-password');await page.locator('#parentName').fill('Browser Parent');await page.locator('#registerParent').click();
  await page.locator('#createChild').waitFor({state:'visible'});await page.locator('#childName').fill('Browser Child');await page.locator('#childGrade').fill('3');await page.locator('#childBoard').selectOption({label:'Telangana SSC'});await page.locator('#createChild').click();
  await page.locator('#practiceStart').waitFor({state:'visible'});await page.locator('#practiceStart').click();await page.locator('#practiceSubmit').waitFor();
  const first=await page.locator('#practiceBody h3').textContent();
  await page.locator('#practiceAnswer').fill('999');await page.locator('#practiceSubmit').click();await page.getByText(/Hint: Start|Hint: Add|Hint: Exchange/).waitFor();
  assert.equal(await page.locator('#practiceBody h3').textContent(),first,'Hint must keep same question');
  for(let i=0;i<10;i++){
   const prompt=await page.locator('#practiceBody h3').textContent(),m=prompt.match(/(\d+)\s*([+−])\s*(\d+)/);assert.ok(m,prompt);
   const answer=m[2]==='+'?Number(m[1])+Number(m[3]):Number(m[1])-Number(m[3]);
   await page.locator('#practiceAnswer').fill(String(answer));await page.locator('#practiceSubmit').click();await page.locator('#practiceNext').waitFor();
   await page.locator('#practiceNext').click();
   if(i<9)await page.locator('#practiceSubmit').waitFor();
  }
  await page.getByText('Practice complete!',{exact:true}).waitFor();assert.match(await page.locator('#practiceBody').textContent(),/9 without a hint/);
  await page.reload();await page.getByText('Practice complete!',{exact:true}).waitFor();
  await page.locator('#practiceStart').click();await page.locator('#practiceSubmit').waitFor();assert.notEqual(await page.locator('#practiceBody h3').textContent(),first);
  // Browser voice fallback without an external provider or paid request.
  await page.locator('#practiceRead').click();await page.locator('#practiceStop').click();
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+2),'Mobile viewport overflows');
  fs.mkdirSync('/tmp/chalo-browser-evidence',{recursive:true});await page.screenshot({path:'/tmp/chalo-browser-evidence/class3-mobile.png',fullPage:true});
  await page.getByRole('button',{name:'📊 Parent',exact:true}).click();await page.getByText(/Foundation Maths: mastered/).waitFor();
  await page.emulateMedia({media:'print'});await page.screenshot({path:'/tmp/chalo-browser-evidence/parent-print.png',fullPage:true});await page.emulateMedia({media:'screen'});
  await page.locator('#logout').click();await page.locator('#loginBtn').waitFor({state:'visible'});
  assert.deepEqual(errors,[],'Browser JavaScript errors');console.log('Mobile browser: registration, profile, hint, 10-question grading, reload, fresh retry, voice fallback, parent progress, print and logout passed');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
