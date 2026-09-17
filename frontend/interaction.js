let currentInteraction=null;

function installInteractionPanel(){
  if($('#interactionPanel'))return;
  const chat=$('#chat');if(!chat)return;
  const panel=document.createElement('div');panel.id='interactionPanel';panel.className='interaction-panel';
  panel.innerHTML=`<div class="interaction-actions"><button data-help="Explain this more simply.">🪄 Explain simpler</button><button data-help="Show me one small example, then ask me one question.">💡 Show example</button><button data-help="I understand this part. Give me the next small step and one question.">👍 I understand</button><button data-help="I need help. Make this easier and guide me with one hint.">🛟 I need help</button></div><div id="interactionQuestion" class="interaction-question hidden"><div id="interactionPrompt"></div><div id="interactionChoices" class="interaction-choices"></div><div id="interactionHint" class="small"></div></div>`;
  chat.insertAdjacentElement('afterend',panel);
  const style=document.createElement('style');style.textContent=`.interaction-panel{margin:8px 0 12px;padding:12px;border:1px solid var(--line);border-radius:15px;background:#fbfcff}.interaction-actions,.interaction-choices{display:flex;gap:8px;flex-wrap:wrap}.interaction-actions button{font-size:12px;padding:8px 10px}.interaction-question{margin-top:11px;padding-top:11px;border-top:1px solid var(--line)}#interactionPrompt{font-weight:750;margin-bottom:9px}.interaction-choice{min-width:120px;padding:12px 16px;background:#eef4ff;border:1px solid #cbd9fb}.interaction-choice:hover{background:#dde9ff}.student-mode .interaction-actions button{font-size:13px}@media(max-width:650px){.interaction-actions{display:grid;grid-template-columns:1fr 1fr}.interaction-choices{display:grid;grid-template-columns:1fr 1fr}.interaction-choice{width:100%;min-width:0}}`;
  document.head.appendChild(style);
  panel.querySelectorAll('[data-help]').forEach(b=>b.onclick=()=>{if(!chatBusy)sendText(b.dataset.help,{display:false})});
}

function clearInteraction(){
  currentInteraction=null;const q=$('#interactionQuestion');if(q)q.classList.add('hidden');if($('#interactionChoices'))$('#interactionChoices').innerHTML='';
  if($('#q')){$('#q').type='text';$('#q').placeholder='Type or speak your answer...'}
}

function renderInteraction(interaction){
  installInteractionPanel();currentInteraction=interaction&&typeof interaction==='object'?interaction:null;
  const qbox=$('#interactionQuestion'),prompt=$('#interactionPrompt'),choices=$('#interactionChoices'),hint=$('#interactionHint');
  if(!currentInteraction||currentInteraction.type==='none'){qbox.classList.add('hidden');return}
  const type=currentInteraction.type||'short_answer';prompt.textContent=currentInteraction.prompt||'Your turn.';choices.innerHTML='';hint.textContent='';qbox.classList.remove('hidden');
  if(type==='choice'||type==='true_false'){
    (currentInteraction.options||[]).forEach(opt=>{const b=document.createElement('button');b.className='interaction-choice';b.textContent=opt;b.onclick=()=>{if(!chatBusy)sendText(opt,{display:true})};choices.appendChild(b)});
    hint.textContent='Tap one answer.';
  }else if(type==='number'){
    $('#q').type='number';$('#q').placeholder='Type the number...';hint.textContent='Type the number or say it using the microphone.';$('#q').focus();
  }else if(type==='voice'){
    $('#q').type='text';$('#q').placeholder='Say your answer or type it...';hint.textContent='Tap the microphone and answer aloud.';
  }else{
    $('#q').type='text';$('#q').placeholder='Type or speak a short answer...';hint.textContent='A short answer is enough.';$('#q').focus();
  }
}

function cleanTutorText(value){
  let text=String(value||'').trim();if(!text)return'';
  if(text.startsWith('{')){
    try{const parsed=JSON.parse(text);if(parsed&&parsed.reply)return String(parsed.reply).trim()}catch{}
    const m=text.match(/"reply"\s*:\s*"([^"}]*)/s);if(m&&m[1])text=m[1].replace(/\\n/g,' ').replace(/\\"/g,'"');
  }
  if(text.length<4)return'';return text;
}

async function sendText(value,opts={}){
  if(chatBusy)return;const p=current(),v=(value??$('#q').value).trim();if(!p||!v||!$('#subject').value)return;
  const display=opts.display!==false;chatBusy=true;stopVoice('');clearInteraction();if(display)add(v,'me');$('#q').value='';$('#send').disabled=true;$$('.chip').forEach(b=>b.disabled=true);$('#status').textContent='Tutor is thinking...';
  try{
    let backend=v;if(activeLesson)backend=`[Current structured chapter: ${activeLesson.title}. Current step: ${stepLabel(activeLesson.current_step)}. Stay strictly inside this chapter.]\n${v}`;
    const r=await authFetch('/api/chat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:backend,student_id:p.id,subject:$('#subject').value,curriculum,activity,session_id:sessionId})});
    const j=await r.json();if(!r.ok)throw new Error(j.detail||'Tutor request failed');sessionId=j.session_id||sessionId;lastStudentState=j.student_state||'neutral';lastDelivery=j.delivery||'normal';
    let spoken=cleanTutorText(j.text||'');const sync=await syncLessonStep(j);if(sync.testFinished)spoken=spoken.replace(/[^.!?।]*\?\s*$/,'').trim()||`Test saved: ${sync.score}%.`;
    if(!spoken)spoken='I could not finish that response. Please tap “Explain simpler” and I will retry this step.';
    lastTutorText=spoken;add(spoken,'bot');renderInteraction(j.interaction);$('#replay').disabled=!lastTutorText;
    $('#status').textContent=(j.sources&&j.sources[0]?`Source: ${j.sources[0]}`:'Learning saved.')+(j.supplemental?' • supplemental':'');
    if(j.assessment!=='not_answer')$('#assessment').textContent=`${j.assessment==='correct'?'✅ Correct':j.assessment==='partial'?'🟡 Partly correct':'🧩 Keep trying'} • ${j.topic||'Topic'} • ${String(j.student_state||'neutral').replace('_',' ')}`;
    if(lastTutorText)speakTutor(lastTutorText,lastStudentState,lastDelivery);
  }catch(e){add(e.message,'system');renderInteraction({type:'none'})}
  finally{chatBusy=false;$('#send').disabled=false;$$('.chip').forEach(b=>b.disabled=false)}
}

installInteractionPanel();
