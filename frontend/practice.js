let practiceData=null,practiceRequest=0,practiceBusy=false;
function practiceIsCurrent(request,student,token){return request===practiceRequest&&(current()||{}).id===student&&token===accessToken}
function placeValue(number){const tens=Math.floor(number/10),ones=number%10;return `<span class="number-block"><b>${number}</b><span class="place-value" aria-label="${tens} tens and ${ones} ones"><span class="tens">${'<i></i>'.repeat(tens)}</span><span class="ones">${'<i></i>'.repeat(ones)}</span></span><small>${tens} tens · ${ones} ones</small></span>`}
function renderPractice(){
 const box=$('#practiceBody'),run=practiceData&&practiceData.run,history=(practiceData&&practiceData.history)||[];
 $('#practiceStart').textContent=run?'Practice again with fresh questions':'Start fresh practice';
 $('#practiceRead').disabled=!run||run.completed;$('#practiceStart').disabled=practiceBusy;
 const last=history.at(-1);$('#practiceProgress').textContent=last?`Foundation Maths: ${last.status.replace('_',' ')} · ${last.unassisted}/${last.total} independently correct. Addition ${last.skills.addition.correct}/5; subtraction ${last.skills.subtraction.correct}/5.`:'Foundation Maths: no completed practice yet.';
 if(!run){box.innerHTML='<p>Ten short questions. Read one idea, try an answer, and get a hint if you need it. Progress saves as you go.</p>';return}
 if(run.completed){box.innerHTML=`<h3>Practice complete!</h3><p>${run.correct}/${run.total} correct, including ${run.unassisted} without a hint.</p><p>${run.unassisted>=8?'Ready for more practice.':'Keep practising. Ask your parent to help with the tricky questions.'}</p>`;return}
 const q=run.question;
 box.innerHTML=`<p class="small">Question ${run.index+1} of ${run.total} · ${esc(q.skill)}</p><p>${esc(q.concept)}</p><div class="math-visual">${placeValue(q.a)}<b class="math-op">${esc(q.op)}</b>${placeValue(q.b)}</div><h3>${esc(q.prompt)}</h3>${q.hint?`<p class="notice">Hint: ${esc(q.hint)}</p>`:''}${q.resolved?`<p>${esc(q.feedback)}</p><button id="practiceNext" class="primary">${run.index===run.total-1?'Finish practice':'Next question'}</button>`:'<label for="practiceAnswer">Your answer</label><div class="row"><input id="practiceAnswer" inputmode="numeric" pattern="[0-9]*" maxlength="3" autocomplete="off"><button id="practiceSubmit" class="primary">Check answer</button></div>'}`;
 if($('#practiceNext'))$('#practiceNext').onclick=()=>updatePractice('next');
 if($('#practiceSubmit')){$('#practiceSubmit').onclick=()=>updatePractice('answer');$('#practiceAnswer').onkeydown=e=>{if(e.key==='Enter')updatePractice('answer')};$('#practiceAnswer').focus()}
}
async function practiceCall(path,body){
 const p=current();if(!p)return;const request=++practiceRequest,token=accessToken;
 const r=await authFetch(path,body?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...body,student_id:p.id})}:{}),j=await r.json();
 if(!practiceIsCurrent(request,p.id,token))return;
 if(!r.ok)throw new Error(j.error||'Practice could not be loaded');
 practiceData=j;renderPractice();$('#practiceStatus').textContent='';
}
async function loadPractice(){practiceData=null;$('#practiceBody').innerHTML='<p>Loading practice...</p>';$('#practiceProgress').textContent='';const p=current();if(!p){practiceRequest++;return}try{await practiceCall(`/api/practice?student_id=${encodeURIComponent(p.id)}`)}catch(e){$('#practiceBody').textContent=e.message}}
async function startPractice(){if(practiceBusy)return;if(practiceData&&practiceData.run&&!practiceData.run.completed&&!window.confirm('Start fresh questions? Your current unfinished practice will be replaced.'))return;practiceBusy=true;stopVoice();try{await practiceCall('/api/practice/start',{})}catch(e){$('#practiceStatus').textContent=e.message}finally{practiceBusy=false;if(practiceData)renderPractice()}}
async function updatePractice(action){
 if(practiceBusy||!practiceData||!practiceData.run||practiceData.run.completed)return;
 const run=practiceData.run,answer=action==='answer'?$('#practiceAnswer').value.trim():undefined;
 if(action==='answer'&&!/^\d{1,3}$/.test(answer)){$('#practiceStatus').textContent='Enter a whole number from 0 to 999.';return}
 practiceBusy=true;stopVoice();if($('#practiceSubmit'))$('#practiceSubmit').disabled=true;if($('#practiceNext'))$('#practiceNext').disabled=true;
 try{await practiceCall('/api/practice/answer',{run_id:run.id,question_id:run.question.id,request_id:crypto.randomUUID(),action,...(answer===undefined?{}:{answer})})}catch(e){$('#practiceStatus').textContent=e.message}finally{practiceBusy=false;if(practiceData)renderPractice()}
}
async function readPractice(){
 const run=practiceData&&practiceData.run,p=current();if(!run||run.completed||!p)return;
 const q=run.question,text=`${q.concept} ${q.prompt.replace('−','minus').replace('+','plus')} ${q.hint||q.feedback||''}`;
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
$('#practiceStart').onclick=startPractice;$('#practiceRead').onclick=readPractice;$('#practiceStop').onclick=()=>stopVoice();
