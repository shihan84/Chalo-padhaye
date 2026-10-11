let practiceData=null,practiceRequest=0,practiceBusy=false,practiceStudentId=null,practicePending=null;
function practiceIsCurrent(request,student,token,unit){return request===practiceRequest&&(current()||{}).id===student&&token===accessToken&&unit===$('#practiceUnit').value}
function placeValue(number){const tens=Math.floor(number/10),ones=number%10;return `<span class="number-block"><b>${number}</b><span class="place-value" aria-label="${tens} tens and ${ones} ones"><span class="tens">${'<i></i>'.repeat(tens)}</span><span class="ones">${'<i></i>'.repeat(ones)}</span></span><small>${tens} tens · ${ones} ones</small></span>`}
const practiceSkillLabels={addition:'Addition',subtraction:'Subtraction',identify:'Recognise fractions',equivalent:'Equivalent fractions',compare:'Compare fractions',fraction_add:'Add fractions',fraction_subtract:'Subtract fractions'};
function fractionBar(bar){
 const count=Number(bar.d),shaded=Number(bar.n);if(!Number.isInteger(count)||count<1||count>24||!Number.isInteger(shaded)||shaded<0||shaded>count)return'';
 return `<div class="fraction-bar-group"><div class="fraction-bar" role="img" aria-label="${bar.unknown?'Unknown number of shaded parts':`${shaded} shaded parts`} out of ${count} equal parts" style="grid-template-columns:repeat(${count},minmax(0,1fr))">${Array.from({length:count},(_,i)=>`<span class="fraction-piece${i<shaded?' shaded':''}"></span>`).join('')}</div><span class="small">${bar.unknown?'? shaded parts':`${shaded} shaded parts`} · ${count} equal parts</span></div>`;
}
function practiceVisual(q){
 if(q.bars)return `<div class="fraction-visual">${q.bars.map(fractionBar).join(q.op?`<b class="fraction-op">${esc(q.op)}</b>`:'')}</div>`;
 return `<div class="math-visual">${placeValue(q.a)}<b class="math-op">${esc(q.op)}</b>${placeValue(q.b)}</div>`;
}
function practiceProgressText(history,title){
 const last=history.at(-1);if(!last)return `${title}: no completed practice yet.`;
 const skills=Object.entries(last.skills).map(([id,v])=>`${practiceSkillLabels[id]||id} ${v.correct}/${v.total}`).join('; ');
 const weak=Object.entries(last.skills).filter(([,v])=>v.correct<v.total).map(([id])=>practiceSkillLabels[id]||id);
 return `${title}: ${last.status.replace('_',' ')} · ${last.unassisted}/${last.total} independently correct. ${skills}.${weak.length?` Next: practise ${weak.join(', ')}.`:''}`;
}
function renderPractice(){
 const box=$('#practiceBody'),run=practiceData&&practiceData.run,history=(practiceData&&practiceData.history)||[];
 $('#practiceStart').textContent=run?'Practice again with fresh questions':'Start fresh practice';
 $('#practiceRead').disabled=!run||run.completed;$('#practiceStart').disabled=practiceBusy;
 const title=practiceData&&practiceData.unit==='class5-fractions'?'Class 5 Fractions':'Foundation Maths';$('#practiceProgress').textContent=practiceProgressText(history,title);
 if(!run){box.innerHTML='<p>Ten short questions. Read one idea, try an answer, and get a hint if you need it. Progress saves as you go.</p>';return}
 if(run.completed){box.innerHTML=`<h3>Practice complete!</h3><p>${run.correct}/${run.total} correct, including ${run.unassisted} without a hint.</p><p>${run.unassisted>=8?'Ready for more practice.':'Keep practising. Ask your parent to help with the tricky questions.'}</p>`;return}
 const q=run.question,answerType=q.answer_type||'number';
 box.innerHTML=`<p class="small">Question ${run.index+1} of ${run.total} · ${esc(practiceSkillLabels[q.skill]||q.skill)}</p><p>${esc(q.concept)}</p>${practiceVisual(q)}<h3>${esc(q.prompt)}</h3>${q.hint?`<p class="notice">Hint: ${esc(q.hint)}</p>`:''}${q.resolved?`<p>${esc(q.feedback)}</p><button id="practiceNext" class="primary">${run.index===run.total-1?'Finish practice':'Next question'}</button>`:answerType==='comparison'?'<div class="fraction-choices"><button data-fraction-answer="<" aria-label="Less than">&lt; Less than</button><button data-fraction-answer="=" aria-label="Equal to">= Equal to</button><button data-fraction-answer=">" aria-label="Greater than">&gt; Greater than</button></div>':`<label for="practiceAnswer">${answerType==='fraction'?'Your answer · use 3/4 or a whole number':'Your answer'}</label><div class="row"><input id="practiceAnswer" inputmode="${answerType==='fraction'?'text':'numeric'}" maxlength="${answerType==='fraction'?7:3}" autocomplete="off"><button id="practiceSubmit" class="primary">Check answer</button></div>`}`;
 box.querySelectorAll('[data-fraction-answer]').forEach(b=>b.onclick=()=>updatePractice('answer',b.dataset.fractionAnswer));
 if($('#practiceNext'))$('#practiceNext').onclick=()=>updatePractice('next');
 if($('#practiceSubmit')){$('#practiceSubmit').onclick=()=>updatePractice('answer');$('#practiceAnswer').onkeydown=e=>{if(e.key==='Enter')updatePractice('answer')};$('#practiceAnswer').focus()}
}
async function practiceCall(path,body){
 const p=current();if(!p)return;const request=++practiceRequest,token=accessToken,unit=$('#practiceUnit').value;
 try{const r=await authFetch(path,body?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...body,student_id:p.id,unit})}:{}),j=await r.json();
 if(!practiceIsCurrent(request,p.id,token,unit))return;
 if(!r.ok)throw new Error(j.error||'Practice could not be loaded');
 practiceData=j;renderPractice();$('#practiceStatus').textContent='';}catch(e){if(practiceIsCurrent(request,p.id,token,unit))throw e}
}
async function loadPractice(unit){
 practiceData=null;practicePending=null;stopVoice();$('#practiceBody').innerHTML='<p>Loading practice...</p>';$('#practiceProgress').textContent='';const p=current();
 if(!p){practiceRequest++;return}
 if(unit)$('#practiceUnit').value=unit;else if(practiceStudentId!==p.id)$('#practiceUnit').value=Number(p.grade)>=5?'class5-fractions':'class3-add-subtract';
 practiceStudentId=p.id;const selected=$('#practiceUnit').value;
 try{await practiceCall(`/api/practice?student_id=${encodeURIComponent(p.id)}&unit=${encodeURIComponent(selected)}`)}catch(e){$('#practiceBody').textContent=e.message}
}
async function startPractice(){if(practiceBusy)return;if(practiceData&&practiceData.run&&!practiceData.run.completed&&!window.confirm('Start fresh questions? Your current unfinished practice will be replaced.'))return;practiceBusy=true;stopVoice();try{await practiceCall('/api/practice/start',{})}catch(e){$('#practiceStatus').textContent=e.message}finally{practiceBusy=false;if(practiceData)renderPractice()}}
async function updatePractice(action,chosen){
 if(practiceBusy||!practiceData||!practiceData.run||practiceData.run.completed)return;
 const run=practiceData.run,answer=action==='answer'?(chosen??$('#practiceAnswer').value.trim()):undefined,type=run.question.answer_type||'number';
 if(action==='answer'){
   const valid=type==='comparison'?['<','=','>'].includes(answer):type==='fraction'?/^[0-9]{1,3}(?:\/[0-9]{1,3})?$/.test(answer)&&(!answer.includes('/')||Number(answer.split('/')[1])>0):/^[0-9]{1,3}$/.test(answer);
   if(!valid){$('#practiceStatus').textContent=type==='fraction'?'Enter a fraction such as 3/4, or a whole number. The denominator must be positive.':'Enter a whole number from 0 to 999.';return}
 }
 const intent={student_id:current().id,unit:practiceData.unit,run_id:run.id,question_id:run.question.id,action,...(answer===undefined?{}:{answer})},signature=JSON.stringify(intent);
 if(!practicePending||practicePending.signature!==signature)practicePending={signature,payload:{...intent,request_id:crypto.randomUUID()}};
 const payload=practicePending.payload;

 practiceBusy=true;stopVoice();$('#practiceBody').querySelectorAll('[data-fraction-answer]').forEach(b=>b.disabled=true);if($('#practiceSubmit'))$('#practiceSubmit').disabled=true;if($('#practiceNext'))$('#practiceNext').disabled=true;
 try{await practiceCall('/api/practice/answer',payload);if(practicePending&&practicePending.payload===payload)practicePending=null}catch(e){$('#practiceStatus').textContent=e.message}finally{practiceBusy=false;if(practiceData)renderPractice()}
}
async function readPractice(){
 const run=practiceData&&practiceData.run,p=current();if(!run||run.completed||!p)return;
 const q=run.question,text=`${q.concept} ${q.speech||q.prompt.replace('−','minus').replace('+','plus')} ${q.hint||q.feedback||''}`;
 stopVoice();lastTutorText=text;
 if(!CFG.custom_voice){speakTutor(text,'neutral','slow',true);return}
 const generation=voiceGeneration,student=p.id;voiceRequest=new AbortController();$('#practiceStatus').textContent='Preparing voice...';
 try{
   const r=await authFetch('/api/tts',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({student_id:student,text}),signal:voiceRequest.signal});
   if(!r.ok)throw new Error('Custom voice unavailable');const blob=await r.blob();if(generation!==voiceGeneration||(current()||{}).id!==student)return;
   voiceAudio=new Audio(URL.createObjectURL(blob));voiceAudio.playbackRate=effectiveRate('neutral','slow');voiceBusy=true;$('#stopVoice').disabled=false;
   voiceAudio.onended=()=>{if(generation===voiceGeneration){stopVoice();$('#practiceStatus').textContent='Your turn.'}};
   await voiceAudio.play();$('#practiceStatus').textContent='Reading aloud...';
 }catch(e){if(generation!==voiceGeneration)return;stopVoice();$('#practiceStatus').textContent='Using browser voice.';speakTutor(text,'neutral','slow',true)}
}
$('#practiceUnit').onchange=()=>loadPractice();$('#practiceStart').onclick=startPractice;$('#practiceRead').onclick=readPractice;$('#practiceStop').onclick=()=>stopVoice();
