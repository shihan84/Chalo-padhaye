let mathsData=null,mathsRequest=0,mathsBusy=false,mathsPending=null;
function mathsCurrent(request,sid,token){return request===mathsRequest&&(current()||{}).id===sid&&accessToken===token&&curriculum==='maharashtra'}
function mathsVisual(v){
 if(!v)return'';
 if(v.kind==='fraction')return fractionBar(v)+(v.other!==undefined?'<b>+</b>'+fractionBar({n:v.other,d:v.d}):'');
 if(v.kind==='table')return `<table class="maths-table"><caption>Fruit counts</caption><tbody>${Object.entries(v.values).map(([k,n])=>`<tr><th scope="row">${esc(k)}</th><td>${esc(n)}</td></tr>`).join('')}</tbody></table>`;
 if(v.kind==='pictograph')return `<table class="maths-table"><caption>Fruit pictograph · one dot = ${esc(v.scale)} fruits</caption><tbody>${Object.entries(v.values).map(([k,n])=>`<tr><th scope="row">${esc(k)}</th><td aria-label="${esc(n)} dots">${'● '.repeat(Math.max(0,Math.min(20,Number(n)||0)))}</td></tr>`).join('')}</tbody></table>`;
 if(v.kind==='position')return `<div class="maths-position" role="img" aria-label="Left to right: ${esc(v.labels.join(', '))}">${v.labels.map(x=>`<span>${esc(x)}</span>`).join('')}</div>`;
 if(v.kind==='polygon'){
  const n=Number(v.sides);if(!Number.isInteger(n)||n<3||n>8)return'';
  const points=Array.from({length:n},(_,i)=>`${60+45*Math.cos(-Math.PI/2+i*2*Math.PI/n)},${60+45*Math.sin(-Math.PI/2+i*2*Math.PI/n)}`).join(' ');
  return `<svg class="maths-diagram" viewBox="0 0 120 120" role="img" aria-label="Polygon with ${n} straight sides"><polygon points="${points}" fill="#dceaff" stroke="#2355a4" stroke-width="3"/></svg>`;
 }
 if(v.kind==='rectangle')return `<svg class="maths-diagram" viewBox="0 0 170 110" role="img" aria-label="Rectangle, length ${esc(v.a)} cm, width ${esc(v.b)} cm"><rect x="20" y="25" width="120" height="60" fill="#dceaff" stroke="#2355a4" stroke-width="3"/><text x="65" y="18">${esc(v.a)} cm</text><text x="135" y="100">${esc(v.b)} cm</text></svg>`;
 if(v.kind==='clock'||v.kind==='angle'){
  const degrees=v.kind==='clock'?Number(v.minute)*30:Number(v.degrees);if(!Number.isFinite(degrees)||degrees<0||degrees>360)return'';
  const rad=degrees*Math.PI/180,x=v.kind==='clock'?60+42*Math.sin(rad):60+42*Math.cos(rad),y=v.kind==='clock'?60-42*Math.cos(rad):60-42*Math.sin(rad);
  return `<svg class="maths-diagram" viewBox="0 0 120 120" role="img" aria-label="${v.kind==='clock'?'Minute hand pointing to '+esc(v.minute):esc(degrees)+' degree angle'}">${v.kind==='clock'?'<circle cx="60" cy="60" r="50" fill="#fff" stroke="#2355a4"/>': '<line x1="60" y1="60" x2="108" y2="60" stroke="#2355a4" stroke-width="3"/>'}<line x1="60" y1="60" x2="${x}" y2="${y}" stroke="#2355a4" stroke-width="3"/></svg>`;
 }
 return'';
}
function mathsSummary(data){if(!data)return'Guided mathematics is available for Maharashtra Classes 3 and 5.';const weak=data.chapters.filter(c=>c.weak_skills.length).map(c=>c.title);return `Maharashtra Class ${data.grade}: ${data.mastered_checkpoints}/${data.total_chapters} chapter skill checkpoints passed. ${weak.length?'Revision suggested: '+weak.slice(0,3).join(', ')+'.':'Each checkpoint covers the listed skills, not every textbook exercise.'}`}
function renderMaths(){
 const d=mathsData;if(!d)return;const run=d.run,chapter=run&&d.chapters.find(c=>c.id===run.chapter_id);
 $('#mathsSummary').textContent=mathsSummary(d);$('#mathsScope').textContent=d.scope+' '+d.edition;
 $('#mathsChapters').innerHTML=d.chapters.map(c=>`<div class="maths-chapter"><b>${esc(c.title)}</b><p class="small">${esc(c.status.replaceAll('_',' '))} · Skills: ${c.skills.map(x=>esc(x.replaceAll('_',' '))).join(', ')}${c.best_score!==null?' · Best checkpoint '+c.best_score+'%':''}</p><button data-maths-start="${esc(c.id)}" ${c.status==='locked'||mathsBusy?'disabled':''}>${run&&!run.completed&&run.chapter_id===c.id?'Continue':c.status==='checkpoint_passed'?'Practise again':'Start'}</button>${c.weak_skills.length?`<button data-maths-revise="${esc(c.id)}" ${mathsBusy?'disabled':''}>Revise weak skills</button>`:''}</div>`).join('');
 if(!run){$('#mathsLesson').innerHTML='<p>Start with the first chapter. Learn one idea, practise it, then take a five-question skill checkpoint. Four independent answers pass the checkpoint and unlock the next chapter.</p>';return}
 const source=officialBookLink(d.source_url+'#page='+chapter.pdf_page,`Textbook reference · PDF pages ${chapter.pdf_page}–${chapter.end_page}`);
 if(run.completed){$('#mathsLesson').innerHTML=`<h3>${esc(chapter.title)}</h3><p>${esc(run.stage_feedback)}</p><p>${run.revision_only?'Revision saved.':'Skill checkpoint passed. The next chapter is available.'}</p>${source}`;return}
 const q=run.question;
 $('#mathsLesson').innerHTML=`<h3>${esc(chapter.title)}</h3><p class="small">${esc(run.stage)} · Question ${run.index+1}/${run.total} · ${esc(q.skill.replaceAll('_',' '))}</p>${run.stage_feedback?`<p class="notice">${esc(run.stage_feedback)}</p>`:''}${q.concept?`<p>${esc(q.concept)}</p>`:''}${mathsVisual(q.visual)}<h4>${esc(q.prompt)}</h4>${q.hint?`<p class="notice">Hint: ${esc(q.hint)}</p>`:''}${q.resolved?`<p>${esc(q.feedback)}</p><button id="mathsNext" class="primary" ${mathsBusy?'disabled':''}>Next step</button>`:q.answer_type==='choice'?`<div class="maths-choices">${q.choices.map((x,i)=>`<button data-maths-choice="${i}" ${mathsBusy?'disabled':''}>${esc(x)}</button>`).join('')}</div>`:`<label for="mathsAnswer">Your answer</label><div class="row"><input id="mathsAnswer" maxlength="24" inputmode="${q.answer_type==='number'?'numeric':'decimal'}" autocomplete="off" ${mathsBusy?'disabled':''}><button id="mathsSubmit" class="primary" ${mathsBusy?'disabled':''}>Check answer</button></div>`}<p class="small">${source} · Independently authored question aligned to this chapter.</p>`;
 $('#mathsLesson').querySelectorAll('[data-maths-choice]').forEach(b=>b.onclick=()=>mathsAction('answer',q.choices[Number(b.dataset.mathsChoice)]));
 if($('#mathsSubmit')){$('#mathsSubmit').onclick=()=>mathsAction('answer',$('#mathsAnswer').value);$('#mathsAnswer').onkeydown=e=>{if(e.key==='Enter')mathsAction('answer',$('#mathsAnswer').value)}}
 if($('#mathsNext'))$('#mathsNext').onclick=()=>mathsAction('next');
}
async function loadMathsCourse(){
 const request=++mathsRequest,p=current(),token=accessToken;mathsData=null;mathsBusy=false;mathsPending=null;
 const available=p&&[3,5].includes(Number(p.grade))&&curriculum==='maharashtra';$('#mathsCourseCard').classList.toggle('hidden',!available);$('#mathsLesson').innerHTML='';$('#mathsChapters').innerHTML='';$('#mathsStatus').textContent='';if(!available)return;
 $('#mathsSummary').textContent='Loading saved mathematics…';
 try{const r=await authFetch('/api/maths-course?student_id='+encodeURIComponent(p.id)),j=await r.json();if(!mathsCurrent(request,p.id,token))return;if(!r.ok)throw Error(j.error||'Could not load mathematics');mathsData=j;renderMaths()}
 catch(e){if(mathsCurrent(request,p.id,token)){$('#mathsSummary').textContent='Mathematics could not be loaded.';$('#mathsStatus').textContent=e.message}}
}
async function mathsAction(action,value){
 if(mathsBusy||!mathsData)return;const p=current(),token=accessToken,request=++mathsRequest,run=mathsData.run;
 const body={student_id:p.id,action};if(action==='start'||action==='revision')body.chapter_id=value;else Object.assign(body,{run_id:run.id,question_id:run.question.id,...(action==='answer'?{answer:value}:{})});
 const signature=JSON.stringify(body);if(!mathsPending||mathsPending.signature!==signature)mathsPending={signature,body:{...body,request_id:crypto.randomUUID()}};
 mathsBusy=true;stopVoice();renderMaths();$('#mathsStatus').textContent='Saving…';
 try{const r=await authFetch('/api/maths-course',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(mathsPending.body)}),j=await r.json();if(!mathsCurrent(request,p.id,token))return;if(!r.ok)throw Error(j.error||'Could not save this answer');mathsData=j;mathsPending=null;$('#mathsStatus').textContent='Saved.';}
 catch(e){if(mathsCurrent(request,p.id,token))$('#mathsStatus').textContent=e.message+' Use Refresh to load the saved lesson if needed.'}
 finally{if(mathsCurrent(request,p.id,token)){mathsBusy=false;renderMaths()}}
}
$('#mathsChapters').onclick=e=>{const b=e.target.closest('[data-maths-start],[data-maths-revise]');if(b&&!b.disabled)mathsAction(b.dataset.mathsStart?'start':'revision',b.dataset.mathsStart||b.dataset.mathsRevise)};
$('#mathsRefresh').onclick=loadMathsCourse;
$('#mathsRead').onclick=()=>{const q=mathsData?.run?.question;if(q){const text=[q.concept,q.prompt,q.hint,q.feedback].filter(Boolean).join(' ');speakTutor(text,'neutral','slow',true)}};
$('#mathsStop').onclick=()=>stopVoice();
