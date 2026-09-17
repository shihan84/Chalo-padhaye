let bookViewerState={url:'',viewerUrl:'',title:'',page:1,chapter:0,kind:'',official:true};

const BOOK_SOURCES={
  maharashtra:{
    3:{math:{title:'Mathematics - Standard Three',url:'https://books.ebalbharati.in/pdfs/303020004.pdf'},english:{title:'English Balbharati - Standard Three',url:'https://books.ebalbharati.in/pdfs/303020001.pdf'}},
    5:{evs1:{title:'Environmental Studies Part One - Standard Five',url:'https://books.ebalbharati.in/pdfs/503000541.pdf'},evs2:{title:'Environmental Studies Part Two - Standard Five',url:'https://books.ebalbharati.in/pdfs/503000542.pdf'},math:{title:'Mathematics - Standard Five',url:'https://books.ebalbharati.in/pdfs/503020004.pdf'},english:{title:'English Balbharati - Standard Five',url:'https://books.ebalbharati.in/pdfs/503020001.pdf'}}
  },
  nios:{
    3:{evs:{title:'NIOS OBE Level A - Environmental Studies',url:'https://cdn.nios.ac.in/cms/documents/2020/Jul/09/EVS_Level_A_english_medium.pdf'},computer:{title:'NIOS OBE Level A - Basic Computer Skills',url:'https://cdn.nios.ac.in/cms/documents/2020/Jul/09/Basic_Computer-skills_Level_A_english_medium.pdf'},math:{title:'Supplemental Mathematics - Balbharati Standard Three',url:'https://books.ebalbharati.in/pdfs/303020004.pdf',supplemental:true},english:{title:'Supplemental English Practice - Balbharati Standard Three',url:'https://books.ebalbharati.in/pdfs/303020001.pdf',supplemental:true}},
    5:{math:{title:'Supplemental Mathematics - Balbharati Standard Five',url:'https://books.ebalbharati.in/pdfs/503020004.pdf',supplemental:true},english:{title:'Supplemental English Practice - Balbharati Standard Five',url:'https://books.ebalbharati.in/pdfs/503020001.pdf',supplemental:true},evs:{title:'Supplemental EVS - Balbharati Standard Five Part One',url:'https://books.ebalbharati.in/pdfs/503000541.pdf',supplemental:true}}
  }
};

const CBSE_PREFIXES={
  1:{math:'aejm1',english:'aemr1'},
  9:{math:'iemh1',science:'iesc1',english:'iebe1',social_science:'iest1'},
  10:{math:'jemh1',science:'jesc1',english:'jeff1',history:'jess3',geography:'jess1',civics:'jess4',economics:'jess2'}
};

function embeddedPdfViewer(url,page=1){
  // NCERT/Balbharati can block cross-site PDF framing. Google Viewer fetches the
  // public official PDF itself, so the book can stay visible inside the lesson.
  return `https://docs.google.com/gview?embedded=1&url=${encodeURIComponent(url)}#page=${Math.max(1,Number(page||1))}`;
}

function ensureBookViewer(){
  if($('#bookViewerCard'))return;
  const style=document.createElement('style');
  style.textContent=`.bookviewer{margin-top:12px}.booktoolbar{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin:10px 0}.booktoolbar input{width:82px}.bookframe{width:100%;height:64vh;border:1px solid var(--line);border-radius:14px;background:#f7f8fb}.bookhint{padding:10px 12px;background:#f7faff;border-radius:12px;margin:8px 0}.bookbadge{display:inline-block;font-size:11px;padding:3px 7px;border-radius:999px;background:#e7f8ed;color:#27613a;margin-left:6px}.bookbadge.sup{background:#fff0d8;color:#885b00}.bookstatus{font-size:12px;color:var(--muted)}@media(max-width:850px){.bookframe{height:55vh}.booktoolbar button{width:auto!important}}`;
  document.head.appendChild(style);
  const card=document.createElement('div');
  card.id='bookViewerCard';card.className='card bookviewer hidden';
  card.innerHTML=`<div class="section-title"><div><h2>📖 Textbook Pages</h2><p id="bookMeta" class="sub">Official book pages for the selected chapter.</p></div><button id="hideBook">Hide</button></div><div id="bookHint" class="bookhint small"></div><div class="booktoolbar"><button id="bookPrev">◀ Tutor page</button><label class="small">Tutor page <input id="bookPage" type="number" min="1" value="1"></label><button id="bookNext">Tutor page ▶</button><button id="teachBookPage" class="primary">Tutor: explain this page</button><button id="quizBookPage">Ask me from this page</button><a id="openBookPdf" target="_blank" rel="noopener"><button>Open official PDF ↗</button></a><span id="bookStatus" class="bookstatus"></span></div><iframe id="bookFrame" class="bookframe" title="Official textbook page viewer" referrerpolicy="no-referrer"></iframe>`;
  const anchor=$('#lessonCard');if(anchor)anchor.insertAdjacentElement('afterend',card);else $('#learnPanel').prepend(card);
  $('#hideBook').onclick=()=>card.classList.add('hidden');
  $('#bookPrev').onclick=()=>setBookPage(Math.max(1,bookViewerState.page-1));
  $('#bookNext').onclick=()=>setBookPage(bookViewerState.page+1);
  $('#bookPage').onchange=e=>setBookPage(Math.max(1,Number(e.target.value||1)));
  $('#teachBookPage').onclick=()=>sendBookPrompt('teach');
  $('#quizBookPage').onclick=()=>sendBookPrompt('quiz');
  $('#bookFrame').onload=()=>{$('#bookStatus').textContent=`Book viewer loaded • tutor focus page ${bookViewerState.page}.`};
  $('#bookFrame').onerror=()=>{$('#bookStatus').textContent='Embedded viewer could not load. Use Open official PDF.'};
}

function sourceForLesson(lesson){
  const p=current(),subject=$('#subject').value;if(!p||!lesson||!subject)return null;
  const grade=Number(p.grade);
  if(curriculum==='cbse'){
    const prefix=(CBSE_PREFIXES[grade]||{})[subject];if(!prefix)return null;
    const chapter=Number(lesson.order||1);
    return {title:`${subjectLabel(subject,'cbse',grade)} — ${lesson.title}`,url:`https://www.ncert.nic.in/textbook/pdf/${prefix}${String(chapter).padStart(2,'0')}.pdf`,chapter,grade,subject,official:true,chapterPdf:true};
  }
  const row=(((BOOK_SOURCES[curriculum]||{})[grade]||{})[subject]);
  if(!row)return null;
  return {...row,chapter:Number(lesson.order||1),grade,subject,official:!row.supplemental,chapterPdf:false};
}

function setBookPage(page){
  bookViewerState.page=Math.max(1,Number(page||1));
  if($('#bookPage'))$('#bookPage').value=bookViewerState.page;
  const viewer=embeddedPdfViewer(bookViewerState.url,bookViewerState.page);
  const direct=`${bookViewerState.url}#page=${bookViewerState.page}`;
  if($('#bookFrame')){$('#bookStatus').textContent='Loading embedded book viewer…';$('#bookFrame').src=viewer}
  if($('#openBookPdf'))$('#openBookPdf').href=direct;
  if($('#bookHint'))$('#bookHint').innerHTML=`Showing <b>${esc(bookViewerState.title)}</b>. Tutor focus: PDF page ${bookViewerState.page}. ${bookViewerState.chapterPdf?'This PDF is the selected NCERT chapter. If the embedded viewer opens on another page, use its own page control while keeping Tutor page matched here.':'This is the full textbook; use the viewer page control and match Tutor page here.'}`;
}

function showBookForLesson(lesson){
  ensureBookViewer();const source=sourceForLesson(lesson);const card=$('#bookViewerCard');
  if(!source){card.classList.remove('hidden');$('#bookMeta').textContent='A browser-viewable official PDF is not mapped for this subject yet.';$('#bookHint').textContent='The tutor can still teach from indexed lesson material.';$('#bookFrame').removeAttribute('src');return}
  bookViewerState={...source,page:1,title:source.title,url:source.url,viewerUrl:embeddedPdfViewer(source.url,1)};
  card.classList.remove('hidden');
  $('#bookMeta').innerHTML=`${source.official?'<span class="bookbadge">official source</span>':'<span class="bookbadge sup">supplemental source</span>'} ${esc(lesson.title)} <span class="small">• embedded viewer; official source link stays available</span>`;
  setBookPage(1);
}

function sendBookPrompt(mode){
  if(!activeLesson||!bookViewerState.url)return;
  const page=bookViewerState.page,title=activeLesson.title,book=bookViewerState.title,chapter=bookViewerState.chapter||Number(activeLesson.order||1);
  const marker=`[Textbook page: ${page}; Chapter number: ${chapter}]`;
  const prompt=mode==='quiz'
    ?`${marker} Use ONLY the selected textbook page when it contains enough information. The chapter is "${title}" in ${book}. Ask exactly one question from this page. Do not give the answer first.`
    :`${marker} Use ONLY the selected textbook page when it contains enough information. The chapter is "${title}" in ${book}. Teach one small concept visible in the page text, keep it short, then ask exactly one checking question.`;
  showTab('learn');sendText(prompt,{display:false});
}

(function wireBookViewer(){
  ensureBookViewer();
  const original=window.beginStructuredLesson;
  if(typeof original==='function'){
    window.beginStructuredLesson=async function(lesson){const out=await original(lesson);showBookForLesson(activeLesson||lesson);return out};
  }
  const originalReset=window.resetChat;
  if(typeof originalReset==='function')window.resetChat=function(close=true){const out=originalReset(close);if(!activeLesson&&$('#bookViewerCard'))$('#bookViewerCard').classList.add('hidden');return out};
})();
