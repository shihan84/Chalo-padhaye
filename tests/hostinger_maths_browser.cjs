// Disposable MySQL fixture only; answers derived independently from visible prompts.
const {chromium}=require('playwright');const assert=require('node:assert/strict');const fs=require('node:fs');
function answer(q){
 const m=q.prompt.match(/a (cube|cuboid|cylinder|cone|sphere) have/);if(m)return String({cube:6,cuboid:6,cylinder:2,cone:1,sphere:0}[m[1]]);
 if(q.prompt.includes('resembles a ball'))return'sphere';if(q.prompt.includes('resembles a dice'))return'cube';if(q.prompt.includes('resembles a tin can'))return'cylinder';
 const r=q.prompt.match(/Write ([IVX]+) as/);if(r){const map={I:1,V:5,X:10};let n=0;for(let i=0;i<r[1].length;i++)n+=map[r[1][i]]<(map[r[1][i+1]]||0)?-map[r[1][i]]:map[r[1][i]];return String(n)}
 throw Error('Unexpected visible question: '+q.prompt);
}
(async()=>{const browser=await chromium.launch(),page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];page.on('pageerror',e=>errors.push(e.message));try{
 await page.goto('http://127.0.0.1:8080/');await page.locator('#email').fill('maths-browser@example.invalid');await page.locator('#password').fill('maths-browser-fixture');await page.locator('#parentName').fill('Maths Fixture');await page.locator('#registerParent').click();await page.locator('#createChild').waitFor({state:'visible'});
 for(const grade of [3,5]){
  if(grade===5)await page.getByRole('button',{name:'📊 Parent',exact:true}).click();
  await page.locator('#childName').fill('Maths Child '+grade);await page.locator('#childGrade').fill(String(grade));await page.locator('#childBoard').selectOption({label:'Maharashtra State Board'});await page.locator('#createChild').click();
  await page.locator('#student option').filter({hasText:'Maths Child '+grade}).waitFor({state:'attached'});await page.locator('#student').selectOption({label:`Maths Child ${grade} — Grade ${grade} — Maharashtra State Board`});
  await page.locator('#mathsSummary').filter({hasText:new RegExp('Maharashtra Class '+grade)}).waitFor();
  if(!(await page.locator('#mathsChapters').isVisible()))await page.getByText('Chapters and skill progress',{exact:true}).click();
  const buttons=page.locator('[data-maths-start]');assert.equal(await buttons.count(),grade===3?14:16);assert.ok(await buttons.nth(1).isDisabled());
  async function action(button){const waiting=page.waitForResponse(r=>r.url().includes('/api/maths-course')&&r.request().method()==='POST');await button.click();const r=await waiting;assert.equal(r.status(),200);const data=await r.json();await page.locator('#mathsStatus').filter({hasText:'Saved.'}).waitFor();return data}
  let data=await action(buttons.first());
  const q=data.run.question;
  if(q.answer_type==='choice'){const wrong=q.choices.find(c=>c!==answer(q));data=await action(page.locator('#mathsLesson [data-maths-choice]').nth(q.choices.indexOf(wrong)));}else{await page.locator('#mathsAnswer').fill('999');data=await action(page.locator('#mathsSubmit'))}
  assert.ok(data.run.question.hint&&!data.run.question.resolved);await page.locator('#mathsLesson').getByText(/Hint:/).waitFor();
  await page.reload();await page.locator('#mathsLesson').getByText(/Hint:/).waitFor();
  for(let i=0;i<30&&!data.run.completed;i++){
   const q=data.run.question;
   if(!q.resolved){const a=answer(q);if(q.answer_type==='choice')data=await action(page.locator('#mathsLesson [data-maths-choice]').nth(q.choices.indexOf(a)));else{await page.locator('#mathsAnswer').fill(a);data=await action(page.locator('#mathsSubmit'))}}
   data=await action(page.locator('#mathsNext'));
  }
  assert.ok(data.run.completed);assert.equal(data.chapters[0].best_score,100);assert.equal(data.chapters[1].status,'available');
  await page.reload();await page.locator('#mathsSummary').filter({hasText:'1/'}).waitFor();
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),'Guided maths overflows mobile');
  fs.mkdirSync('/tmp/chalo-browser-evidence',{recursive:true});await page.screenshot({path:`/tmp/chalo-browser-evidence/guided-maths-class${grade}.png`,fullPage:true});
  await page.getByRole('button',{name:'📊 Parent',exact:true}).click();await page.locator('#mathsParentSummary').filter({hasText:'1/'}).waitFor();
  await page.getByRole('button',{name:'📖 Learn',exact:true}).click();
 }
 assert.deepEqual(errors,[]);console.log('Guided Maths mobile: both grades, all chapter cards, first-wrong hint, reload continuation, independent checkpoint grading, next chapter unlock, parent summary and viewport passed');
}finally{await browser.close()}})().catch(e=>{console.error(e);process.exitCode=1});
