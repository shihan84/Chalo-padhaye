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
  await page.getByRole('button',{name:'📖 Learn',exact:true}).click();
  const class3Resume=await page.locator('#practiceBody h3').textContent();
  await page.locator('#practiceUnit').selectOption('class5-fractions');await page.getByText('Ten short questions.',{exact:false}).waitFor();
  await page.locator('#practiceStart').click();await page.locator('#practiceSubmit').waitFor();
  const fractionFirst=await page.locator('.fraction-bar').getAttribute('aria-label');
  await page.locator('#practiceAnswer').fill('1/0');await page.locator('#practiceSubmit').click();await page.getByText(/The denominator must be positive/).waitFor();
  await page.locator('#practiceAnswer').fill('999');await page.locator('#practiceSubmit').click();await page.getByText(/Hint: Count/).waitFor();
  for(let i=0;i<10;i++){
   const bars=await page.locator('.fraction-bar').evaluateAll(nodes=>nodes.map(n=>({a:n.querySelectorAll('.shaded').length,d:n.children.length}))),prompt=await page.locator('#practiceBody h3').textContent();
   let answer;
   switch(i%5){
    case 0:answer=`${bars[0].a*2}/${bars[0].d*2}`;break;
    case 1:{const m=prompt.match(/(\d+)\/(\d+) = \?\/(\d+)/);assert.ok(m,prompt);answer=String(Number(m[1])*Number(m[3])/Number(m[2]));break}
    case 2:{const l=bars[0].a*bars[1].d,r=bars[1].a*bars[0].d;answer=l===r?'Equal to':l<r?'Less than':'Greater than';break}
    case 3:answer=`${bars[0].a+bars[1].a}/${bars[0].d}`;break;
    case 4:answer=`${bars[0].a-bars[1].a}/${bars[0].d}`;break;
   }
   if(i%5===2)await page.getByRole('button',{name:answer,exact:true}).click();
   else{await page.locator('#practiceAnswer').fill(answer);await page.locator('#practiceSubmit').click()}
   await page.locator('#practiceNext').waitFor();await page.locator('#practiceNext').click();
   if(i<9){if((i+1)%5===2)await page.getByRole('button',{name:'Less than',exact:true}).waitFor();else await page.locator('#practiceSubmit').waitFor()}
  }
  await page.getByText('Practice complete!',{exact:true}).waitFor();assert.match(await page.locator('#practiceBody').textContent(),/9 without a hint/);
  await page.reload();await page.locator('#practiceUnit').selectOption('class5-fractions');await page.getByText('Practice complete!',{exact:true}).waitFor();
  await page.getByRole('button',{name:'📊 Parent',exact:true}).click();await page.getByText(/Class 5 Fractions: mastered/).waitFor();
  assert.match(await page.locator('#practiceProgress').textContent(),/Next: practise Recognise fractions/);
  await page.getByRole('button',{name:'📖 Learn',exact:true}).click();await page.locator('#practiceStart').click();await page.locator('#practiceSubmit').waitFor();
  assert.notEqual(await page.locator('.fraction-bar').getAttribute('aria-label'),fractionFirst,'Fractions retry reused the shaded bar');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+2),'Fractions mobile viewport overflows');
  await page.screenshot({path:'/tmp/chalo-browser-evidence/class5-fractions-mobile.png',fullPage:true});
  await page.locator('#practiceRead').click();await page.locator('#practiceStop').click();
  await page.locator('#practiceUnit').selectOption('class3-add-subtract');await page.locator('#practiceSubmit').waitFor();assert.equal(await page.locator('#practiceBody h3').textContent(),class3Resume,'Switching units erased Class 3 resume');
  await page.locator('#logout').click();await page.locator('#loginBtn').waitFor({state:'visible'});
  assert.deepEqual(errors,[],'Browser JavaScript errors');console.log('Mobile browser: both Class 3 and Class 5 fractions, rational answers, comparison choices, invalid denominator, unit isolation, skill feedback, registration, profile, hint, 10-question grading, reload, fresh retry, voice fallback, parent progress, print and logout passed');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
