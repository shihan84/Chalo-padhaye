let bookViewerState={url:'',viewerUrl:'',title:'',page:1,chapter:0,kind:'',official:true};

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
  card.innerHTML=`<div class="section-title"><div><h2>📖 Textbook Pages</h2><p id="bookMeta" class="sub">Official book pages for the selected chapter.</p></div><button id="hideBook">Hide</button></div><div id="bookHint" class="bookhint small"></div><div class="booktoolbar"><button id="bookPrev">◀ PDF page</button><label class="small">PDF page <input id="bookPage" type="number" min="1" value="1"></label><button id="bookNext">PDF page ▶</button><button id="teachBookPage" class="primary">Explain this page (pending)</button><button id="quizBookPage">Quiz this page (pending)</button><a id="openBookPdf" target="_blank" rel="noopener"><button>Open official PDF ↗</button></a><span id="bookStatus" class="bookstatus"></span></div><iframe id="bookFrame" class="bookframe" title="Official textbook page viewer" referrerpolicy="no-referrer"></iframe>`;
  const anchor=$('#lessonCard');if(anchor)anchor.insertAdjacentElement('afterend',card);else $('#learnPanel').prepend(card);
  $('#hideBook').onclick=()=>card.classList.add('hidden');
  $('#bookPrev').onclick=()=>setBookPage(Math.max(1,bookViewerState.page-1));
  $('#bookNext').onclick=()=>setBookPage(bookViewerState.page+1);
  $('#bookPage').onchange=e=>setBookPage(Math.max(1,Number(e.target.value||1)));
  $('#teachBookPage').onclick=()=>sendBookPrompt('teach');
  $('#quizBookPage').onclick=()=>sendBookPrompt('quiz');
  $('#bookFrame').onload=()=>{if(!bookViewerState.url)return;$('#bookStatus').textContent=`Book viewer loaded • reading focus page ${bookViewerState.page}.`};
  $('#bookFrame').onerror=()=>{$('#bookStatus').textContent='Embedded viewer could not load. Use Open official PDF.'};
}

function sourceForLesson(lesson){
  // A publisher catalogue is not a verified lesson-to-chapter mapping.
  return null;
}
function clearBookSource(){
  bookViewerState={url:'',title:'',page:1};
  $('#bookFrame').removeAttribute('src');$('#openBookPdf').removeAttribute('href');
  $('#bookStatus').textContent='';$('#bookPage').value=1;$('#openBookPdf').classList.add('hidden');
  ['teachBookPage','quizBookPage','bookPrev','bookNext','bookPage'].forEach(id=>$('#'+id).disabled=true);
}
function openLibraryBook(book){
  const safe=officialBookUrl(book.url);if(!safe||book.format!=='pdf')return;
  ensureBookViewer();clearBookSource();bookViewerState={...book,url:safe,page:1,chapterPdf:false};
  $('#bookViewerCard').classList.remove('hidden');$('#bookMeta').textContent=`${book.title} · ${book.edition}`;
  ['bookPrev','bookNext','bookPage'].forEach(id=>$('#'+id).disabled=false);
  $('#openBookPdf').classList.remove('hidden');setBookPage(1);$('#bookViewerCard').scrollIntoView({behavior:'smooth',block:'start'});
}

function setBookPage(page){
  if(!bookViewerState.url)return;bookViewerState.page=Math.min(10000,Math.max(1,Math.floor(Number(page)||1)));
  if($('#bookPage'))$('#bookPage').value=bookViewerState.page;
  const viewer=embeddedPdfViewer(bookViewerState.url,bookViewerState.page);
  const direct=`${bookViewerState.url}#page=${bookViewerState.page}`;
  if($('#bookFrame')){$('#bookStatus').textContent='Loading embedded book viewer…';$('#bookFrame').src=viewer}
  if($('#openBookPdf'))$('#openBookPdf').href=direct;
  if($('#bookHint'))$('#bookHint').innerHTML=`Showing <b>${esc(bookViewerState.title)}</b>. Reading focus: PDF page ${bookViewerState.page}. ${bookViewerState.chapterPdf?'This PDF is the selected NCERT chapter. If the embedded viewer opens on another page, use its own page control while keeping PDF page matched here.':'This is the full textbook; use the viewer page control and match PDF page here.'}`;
}

function showBookForLesson(lesson){
  ensureBookViewer();clearBookSource();const source=sourceForLesson(lesson);const card=$('#bookViewerCard');
  if(!source){card.classList.remove('hidden');$('#bookMeta').textContent='A browser-viewable official PDF is not mapped for this subject yet.';$('#bookHint').textContent='Use the Syllabus & Textbook Library to browse official sources. Textbook-grounded tutoring is pending.';$('#bookFrame').removeAttribute('src');return}
  bookViewerState={...source,page:1,title:source.title,url:source.url,viewerUrl:embeddedPdfViewer(source.url,1)};
  card.classList.remove('hidden');
  $('#bookMeta').innerHTML=`${source.official?'<span class="bookbadge">official source</span>':'<span class="bookbadge sup">supplemental source</span>'} ${esc(lesson.title)} <span class="small">• embedded viewer; official source link stays available</span>`;
  setBookPage(1);
}

function sendBookPrompt(){toast('Textbook-grounded tutoring is not connected yet.')}

(function wireBookViewer(){
  ensureBookViewer();clearBookSource();
  const original=window.beginStructuredLesson;
  if(typeof original==='function'){
    window.beginStructuredLesson=async function(lesson){const out=await original(lesson);showBookForLesson(activeLesson||lesson);return out};
  }
  const originalReset=window.resetChat;
  if(typeof originalReset==='function')window.resetChat=function(close=true){const out=originalReset(close);if(!activeLesson&&$('#bookViewerCard')){clearBookSource();$('#bookViewerCard').classList.add('hidden')}return out};
})();
