<?php
declare(strict_types=1);
// Run against the isolated CI fixture server only.
$base='http://127.0.0.1:8080/api';
require_once dirname(__DIR__).'/php/lessons.php';
function call_api(string $method,string $path,array $body=[],?string $token=null): array {
    global $base;
    $headers=['Content-Type: application/json'];if($token)$headers[]='Authorization: Bearer '.$token;
    $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>json_encode($body),'ignore_errors'=>true,'timeout'=>10]]);
    $raw=file_get_contents($base.$path,false,$ctx);
    $line=$http_response_header[0]??'';preg_match('/\s(\d{3})\s/',$line,$m);
    return [(int)($m[1]??0),json_decode($raw?:'',true)];
}
function expect(int $actual,int $expected,string $name): void {if($actual!==$expected)throw new RuntimeException("$name: got $actual, expected $expected");}
[$status,$syllabus]=call_api('GET','/syllabus?curriculum=telangana&grade=10');expect($status,200,'Public SSC syllabus');
if(($syllabus['label']??'')!=='Telangana SSC Class 10'||count($syllabus['subjects']??[])<6)throw new RuntimeException('SSC subjects should be exposed independently of tutor availability');
foreach (['11','0','3abc','10.5','../10'] as $grade) {[$status]=call_api('GET','/syllabus?grade='.rawurlencode($grade));expect($status,422,'Reject unsupported syllabus grade');}
[$status]=call_api('GET','/syllabus?curriculum=cbse&grade=10');expect($status,422,'No incorrect cross-board syllabus');
foreach (['maharashtra'=>12,'cbse'=>12,'telangana'=>10,'nios'=>12] as $board=>$max) {
    for ($grade=1;$grade<=$max;$grade++) {
        [$status,$library]=call_api('GET','/curriculum-library?curriculum='.$board.'&grade='.$grade);
        expect($status,200,'Public library '.$board.' '.$grade);
        if ($library['curriculum']!==$board||$library['grade']!==$grade||$library['tutor_available']!==($board==='maharashtra'&&$grade===3)||!count($library['pending'])) throw new RuntimeException('Library provenance/availability mismatch');
    }
}
foreach (['curriculum=evil&grade=3','curriculum=cbse&grade=13','curriculum=telangana&grade=11','grade=3abc','grade=../3'] as $query) {[$status]=call_api('GET','/curriculum-library?'.$query);expect($status,422,'Reject invalid library selector');}
[$status,$library]=call_api('GET','/curriculum-library?curriculum=cbse&grade=5');
if (!in_array('Santoor',array_column($library['books'],'title'),true)) throw new RuntimeException('Current NCERT Class 5 English should be mapped');
[$status,$library]=call_api('GET','/curriculum-library?curriculum=nios&grade=5');
if (!str_contains($library['label'],'Level B')||count($library['books'])!==2) throw new RuntimeException('NIOS must use Level B official books, not Balbharati supplements');
[$status,$a]=call_api('POST','/auth/register',['email'=>'parent-a@example.invalid','password'=>'fixture-password-a']);expect($status,201,'Parent A registration');$ta=$a['access_token'];
[$status,$b]=call_api('POST','/auth/register',['email'=>'parent-b@example.invalid','password'=>'fixture-password-b']);expect($status,201,'Parent B registration');$tb=$b['access_token'];
[$status,$row]=call_api('POST','/students',['full_name'=>'Fixture Child','grade'=>3],$ta);expect($status,201,'Student creation');$sid=$row['student']['id'];
[$status]=call_api('GET','/students/'.$sid,[],$tb);expect($status,403,'Cross-parent profile denied');
// Guided course uses authenticated family ownership and server-generated state.
[$status]=call_api('GET','/maths-course?student_id='.$sid);expect($status,401,'Guided course requires auth');
[$status]=call_api('GET','/maths-course?student_id='.$sid,[],$tb);expect($status,403,'Guided course family isolation');
[$status,$course]=call_api('GET','/maths-course?student_id='.$sid,[],$ta);expect($status,200,'Guided Class 3 course');
if(count($course['chapters'])!==14||$course['chapters'][1]['status']!=='locked')throw new RuntimeException('Guided chapter coverage/locks');
$guided=['student_id'=>$sid,'action'=>'start','chapter_id'=>$course['chapters'][0]['id'],'request_id'=>uuid_v4()];
[$status,$course]=call_api('POST','/maths-course',$guided,$ta);expect($status,200,'Start guided course');
if(isset($course['run']['question']['answer']))throw new RuntimeException('Guided key leaked');
[$status]=call_api('POST','/maths-course',$guided,$tb);expect($status,403,'Guided mutation family isolation');
[$status]=call_api('POST','/maths-course',array_replace($guided,['request_id'=>uuid_v4(),'chapter_id'=>'mh5-math-01']),$ta);expect($status,422,'Guided wrong grade rejected');
[$status]=call_api('POST','/maths-course',['student_id'=>$sid,'action'=>'answer','request_id'=>uuid_v4(),'run_id'=>$course['run']['id'],'question_id'=>$course['run']['question']['id'],'answer'=>[], 'score'=>100],$ta);expect($status,422,'Malformed guided answer cannot manufacture score');
[$status,$invite]=call_api('POST','/students/'.$sid.'/invite',[],$ta);expect($status,201,'Create invite');if(strlen($invite['invite_code'])!==32)throw new RuntimeException('Invite should be 128-bit hex');
[$status,$child]=call_api('POST','/auth/student/register',['email'=>'student@example.invalid','password'=>'fixture-password-c','invite_code'=>$invite['invite_code']]);expect($status,201,'Claim invite');$tc=$child['access_token'];
[$status]=call_api('POST','/students/'.$sid.'/invite',[],$ta);expect($status,409,'Linked account cannot be implicitly replaced');
[$status]=call_api('POST','/portfolio',['student_id'=>$sid,'title'=>'Unauthorized edit'], $tc);expect($status,403,'Student portfolio edit denied');
[$status]=call_api('GET','/portfolio?student_id='.$sid,[],$tb);expect($status,403,'Cross-parent portfolio denied');
[$status,$chat]=call_api('POST','/chat',['student_id'=>$sid,'message'=>'hello','subject'=>'math','curriculum'=>'maharashtra'],$ta);expect($status,200,'Start session');$session=$chat['session_id'];
if(($chat['error']??'')!=='textbook_retrieval_unavailable')throw new RuntimeException('Tutor must fail closed without textbook sources');
[$status]=call_api('POST','/chat',['student_id'=>$sid,'session_id'=>$session,'message'=>'4','subject'=>'math','curriculum'=>'nios'],$ta);expect($status,403,'Cross-curriculum session denied');
[$status]=call_api('POST','/end-session',['student_id'=>$sid,'session_id'=>$session],$ta);expect($status,200,'Close session');
[$status]=call_api('POST','/chat',['student_id'=>$sid,'session_id'=>$session,'message'=>'4','subject'=>'math','curriculum'=>'maharashtra'],$ta);expect($status,403,'Closed session denied');
// Grounded page reading: fixture text only, no real provider calls.
$manifest=json_decode(file_get_contents(dirname(__DIR__).'/data/textbook_manifest.json'),true)['books'];
$book=current(array_filter($manifest,fn($b)=>str_ends_with($b['url'],'/303060004.pdf')));
$pageBody=['student_id'=>$sid,'book_id'=>$book['id'],'page'=>13,'action'=>'quiz'];
[$status]=call_api('POST','/book-tutor',$pageBody);expect($status,401,'Page tutor needs authentication');
[$status]=call_api('POST','/book-tutor',$pageBody,$tb);expect($status,403,'Page tutor family isolation');
[$status]=call_api('POST','/book-tutor',array_replace($pageBody,['book_id'=>'../../etc/passwd']),$ta);expect($status,422,'Page tutor rejects arbitrary source');
[$status]=call_api('POST','/book-tutor',array_replace($pageBody,['page'=>9999]),$ta);expect($status,422,'Page tutor bounds pages');
[$status,$pageLesson]=call_api('POST','/book-tutor',$pageBody,$ta);expect($status,200,'Grounded page question');
if(isset($pageLesson['answer'])||$pageLesson['sources'][0]['pdf_page']!==13)throw new RuntimeException('Page answer leaked or wrong citation');
$answerBody=array_replace($pageBody,['action'=>'answer','session_id'=>$pageLesson['session_id'],'answer'=>'wrong']);
[$status,$hint]=call_api('POST','/book-tutor',$answerBody,$ta);expect($status,200,'Reading hint');
if($hint['complete']||!str_contains($hint['text'],'Hint:'))throw new RuntimeException('First wrong reading answer should retain question');
[$status]=call_api('POST','/book-tutor',array_replace($answerBody,['page'=>14]),$ta);expect($status,409,'Reading page scope');
[$status,$correct]=call_api('POST','/book-tutor',array_replace($answerBody,['answer'=>'sides']),$ta);expect($status,200,'Server validates reading word');
if(!$correct['complete']||$correct['assessment']!=='correct')throw new RuntimeException('Reading exact word should match');
$q=db()->prepare('SELECT COUNT(*) FROM student_progress WHERE student_id=?');$q->execute([$sid]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Reading must not award subject mastery');
$bookRoot=dirname(__DIR__).'/runtime/book_sessions';$filesBefore=count(glob($bookRoot.'/*.json')?:[]);
[$status,$newCheck]=call_api('POST','/book-tutor',$pageBody,$ta);expect($status,200,'Replace child reading check');
[$status]=call_api('POST','/book-tutor',array_replace($answerBody,['answer'=>'sides']),$ta);expect($status,409,'Replaced reading token rejected');
if(count(glob($bookRoot.'/*.json')?:[])!==$filesBefore)throw new RuntimeException('Reading retries must not grow storage');
// Browsers cannot forge board mastery by sending assessment or score counts.
$defs=lesson_defs('maharashtra',3,'math');
$lesson=['student_id'=>$sid,'curriculum'=>'maharashtra','subject'=>'math','lesson_id'=>$defs[0]['id']];
[$status]=call_api('POST','/lessons/step',$lesson+['step_id'=>'learn','assessment'=>'correct'],$ta);expect($status,409,'Reject client assessment');
[$status]=call_api('POST','/lessons/test',$lesson+['question_count'=>5,'correct_count'=>5],$ta);expect($status,409,'Reject client score');

// Independent foreign keys must not allow a child to point at another child's progress.
[$status,$other]=call_api('POST','/students',['full_name'=>'Fixture B Child','grade'=>3],$tb);expect($status,201,'Create second-parent child');
$otherId=$other['student']['id'];$pid=uuid_v4();
db()->prepare("INSERT INTO lesson_progress(id,student_id,curriculum,subject,lesson_id,lesson_title) VALUES(?,?,'maharashtra','math','fixture','Fixture')")->execute([$pid,$otherId]);
foreach([
    ["INSERT INTO lesson_step_records(id,lesson_progress_id,student_id,step_id,step_order,step_title) VALUES(?,?,?,'learn',1,'Learn')", [uuid_v4(),$pid,$sid]],
    ['INSERT INTO lesson_test_attempts(id,lesson_progress_id,student_id,attempt_no,score) VALUES(?,?,?,1,100)', [uuid_v4(),$pid,$sid]],
] as [$sql,$params]){
    try{db()->prepare($sql)->execute($params);throw new RuntimeException('Cross-child progress link accepted');}
    catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}
}
[$status]=call_api('POST','/auth/register',['email'=>'long@example.invalid','password'=>str_repeat('x',73)]);expect($status,422,'Reject silently truncated bcrypt password');
[$status]=call_api('POST','/chat',['message'=>str_repeat('x',70000)]);expect($status,413,'Bound body size before database access');
[$status]=call_api('GET','/students');expect($status,401,'Missing token denied');
[$status]=call_api('POST','/auth/logout',[],$tc);expect($status,200,'Logout student');
[$status]=call_api('GET','/auth/me',[],$tc);expect($status,401,'Revoked token denied');
// Broader family workflows run only in the disposable CI database.
[$status]=call_api('POST','/auth/login',['email'=>'parent-a@example.invalid','password'=>'wrong-password']);expect($status,401,'Reject wrong password');
[$status,$login]=call_api('POST','/auth/login',['email'=>'parent-a@example.invalid','password'=>'fixture-password-a']);expect($status,200,'Parent login');$newToken=$login['access_token'];
[$status,$me]=call_api('GET','/auth/me',[],$newToken);expect($status,200,'Parent session identity');
if(($me['user']['role']??'')!=='parent')throw new RuntimeException('Incorrect login role');
[$status]=call_api('POST','/auth/register',['email'=>'parent-a@example.invalid','password'=>'fixture-password-a']);expect($status,409,'Duplicate registration');
[$status]=call_api('POST','/students',['full_name'=>'Invalid','grade'=>13],$ta);expect($status,422,'Reject invalid child grade');
[$status,$profiles]=call_api('GET','/students',[],$ta);expect($status,200,'Parent profile list');
foreach($profiles['students'] as $profile)if($profile['id']===$otherId)throw new RuntimeException('Other family leaked in profile list');
[$status,$changed]=call_api('PATCH','/students/'.$sid,['grade'=>5,'board'=>'Telangana SSC'],$ta);expect($status,200,'Update child board and grade');
if((int)$changed['student']['grade']!==5||$changed['student']['board']!=='Telangana SSC')throw new RuntimeException('Child changes were not saved');
[$status]=call_api('PATCH','/students/'.$sid,['grade'=>10],$tb);expect($status,403,'Cross-family child update denied');
[$status,$record]=call_api('POST','/portfolio',['student_id'=>$sid,'title'=>'Goal: Read together','record_type'=>'reading','curriculum'=>'general','status'=>'planned','minutes'=>15,'due_date'=>gmdate('Y-m-d')],$ta);expect($status,201,'Save planned portfolio goal');$recordId=$record['record']['id'];
[$status,$portfolio]=call_api('GET','/portfolio?student_id='.$sid,[],$ta);expect($status,200,'Read portfolio');
if(!in_array($recordId,array_column($portfolio['records'],'id'),true))throw new RuntimeException('Saved record missing from portfolio');
[$status,$plan]=call_api('GET','/daily-plan?student_id='.$sid.'&curriculum=telangana',[],$ta);expect($status,200,'Telangana daily plan');
if(!in_array($recordId,array_column($plan['items'],'record_id'),true))throw new RuntimeException('Planned activity missing from daily plan');
[$status]=call_api('PATCH','/portfolio/'.$recordId,['student_id'=>$sid,'status'=>'completed'],$tb);expect($status,403,'Cross-family goal completion denied');
[$status,$done]=call_api('PATCH','/portfolio/'.$recordId,['student_id'=>$sid,'status'=>'completed'],$ta);expect($status,200,'Complete portfolio goal');
if($done['record']['status']!=='completed')throw new RuntimeException('Goal completion not saved');
[$status]=call_api('POST','/portfolio',['student_id'=>$sid,'title'=>'Invalid date','occurred_on'=>'2026-02-30'],$ta);expect($status,422,'Reject invalid portfolio date');
[$status,$dashboard]=call_api('GET','/dashboard?student_id='.$sid,[],$ta);expect($status,200,'Load parent dashboard');
if((int)$dashboard['week']['offline_minutes']<15)throw new RuntimeException('Completed reading missing from dashboard metrics');
foreach(['maharashtra','nios','cbse','telangana'] as $curr){
 [$status,$roadmap]=call_api('GET','/roadmap?student_id='.$sid.'&curriculum='.$curr,[],$ta);expect($status,200,'Load '.$curr.' roadmap');
 if($roadmap['curriculum']!==$curr)throw new RuntimeException('Wrong roadmap curriculum');
 [$status,$day]=call_api('GET','/daily-plan?student_id='.$sid.'&curriculum='.$curr,[],$ta);expect($status,200,'Load '.$curr.' daily plan');
 foreach($day['items'] as $item)if($item['kind']==='lesson')throw new RuntimeException('Ungrounded lesson appeared in daily plan');
}
[$status]=call_api('GET','/dashboard?student_id='.$sid,[],$tb);expect($status,403,'Cross-family dashboard denied');
[$status]=call_api('GET','/roadmap?student_id='.$sid.'&curriculum=telangana',[],$tb);expect($status,403,'Cross-family roadmap denied');
[$status]=call_api('GET','/daily-plan?student_id='.$sid.'&curriculum=telangana',[],$tb);expect($status,403,'Cross-family daily plan denied');
// Server-owned foundation assessment, hint-first feedback, idempotency and retries.
[$status]=call_api('GET','/practice?student_id='.$sid,[],$tb);expect($status,403,'Other family cannot read practice');
[$status]=call_api('POST','/practice/start',['student_id'=>$sid],$tb);expect($status,403,'Other family cannot reset practice');
[$status,$practice]=call_api('POST','/practice/start',['student_id'=>$sid],$ta);expect($status,200,'Start foundation unit');
$firstSignature=$practice['run']['question']['prompt'];$oldRun=$practice['run']['id'];$signatures=[];
for($index=0;$index<10;$index++){
 $run=$practice['run'];$q=$run['question'];$signatures[]=$q['prompt'];
 if(array_key_exists('answer',$q))throw new RuntimeException('Answer key leaked before attempt');
 $expected=$q['op']==='+'?$q['a']+$q['b']:$q['a']-$q['b'];
 $payload=['student_id'=>$sid,'run_id'=>$run['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'action'=>'answer','answer'=>(string)$expected,'correct_count'=>10,'assessment'=>'correct'];
 if($index===0){
   $wrong=$payload;$wrong['answer']='999';
   [$status,$hint]=call_api('POST','/practice/answer',$wrong,$ta);expect($status,200,'First wrong answer receives hint');
   if(empty($hint['run']['question']['hint'])||$hint['run']['question']['resolved']||isset($hint['run']['question']['answer']))throw new RuntimeException('Hint-first rule broken');
   [$status,$duplicate]=call_api('POST','/practice/answer',$wrong,$ta);expect($status,200,'Retry identical network request');
   if($duplicate!==$hint)throw new RuntimeException('Duplicate answer counted twice');
   [$status]=call_api('POST','/practice/answer',$payload,$ta);expect($status,409,'Reject reusing request ID with altered answer');
   $payload['request_id']=uuid_v4();
 }
 [$status,$practice]=call_api('POST','/practice/answer',$payload,$ta);expect($status,200,'Grade computed answer');
 if(!$practice['run']['question']['resolved'])throw new RuntimeException('Correct answer did not resolve question');
 [$status]=call_api('POST','/practice/answer',$payload+[], $tb);expect($status,403,'Other family cannot submit answer');
 $next=['student_id'=>$sid,'run_id'=>$run['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'action'=>'next'];
 [$status,$practice]=call_api('POST','/practice/answer',$next,$ta);expect($status,200,'Advance resolved question');
 [$status,$duplicate]=call_api('POST','/practice/answer',$next,$ta);expect($status,200,'Idempotent next');
 if($duplicate!==$practice)throw new RuntimeException('Duplicate next skipped a question');
}
if(!$practice['run']['completed']||$practice['run']['correct']!==10||$practice['run']['unassisted']!==9||count($practice['history'])!==1)throw new RuntimeException('Practice grading/history incorrect');
[$status,$resumed]=call_api('GET','/practice?student_id='.$sid,[],$ta);expect($status,200,'Reload saved practice');
if($resumed!==$practice)throw new RuntimeException('Practice state did not survive request');
[$status,$fresh]=call_api('POST','/practice/start',['student_id'=>$sid],$ta);expect($status,200,'Fresh retry');
if($fresh['run']['id']===$oldRun||in_array($fresh['run']['question']['prompt'],$signatures,true))throw new RuntimeException('Practice again reused old questions');
[$status]=call_api('POST','/practice/answer',$next,$ta);expect($status,409,'Reject obsolete run');
// Class 5 fractions have independent runs; answer payloads never select the question key.
[$status]=call_api('GET','/practice?student_id='.$sid.'&unit=class5-fractions',[],$tb);expect($status,403,'Other family cannot read fractions');
[$status]=call_api('GET','/practice?student_id='.$sid.'&unit=bogus',[],$ta);expect($status,422,'Reject unknown unit');
[$status,$fraction]=call_api('POST','/practice/start',['student_id'=>$sid,'unit'=>'class5-fractions'],$ta);expect($status,200,'Start fractions');
[$status,$kept]=call_api('GET','/practice?student_id='.$sid,[],$ta);expect($status,200,'Existing Class 3 still available');
if($kept!==$fresh)throw new RuntimeException('Fractions overwrote Class 3 state');
$fractionSignatures=[];
for($index=0;$index<10;$index++){
 $run=$fraction['run'];$q=$run['question'];$fractionSignatures[]=json_encode($q['bars']);
 if(isset($q['answer'])||isset($q['signature']))throw new RuntimeException('Private answer fields leaked');
 $answer=match($q['skill']){
  'identify'=>($q['a']*2).'/'.($q['d']*2),
  'equivalent'=>(string)($q['a']*intdiv($q['target'],$q['d'])),
  'compare'=>($q['a']*$q['other_d'])===($q['b']*$q['d'])?'=':(($q['a']*$q['other_d'])<($q['b']*$q['d'])?'<':'>'),
  'fraction_add'=>($q['a']+$q['b']).'/'.$q['d'],
  'fraction_subtract'=>($q['a']-$q['b']).'/'.$q['d'],
 };
 $payload=['student_id'=>$sid,'unit'=>'class5-fractions','run_id'=>$run['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'answer'=>$answer];
 if($index===0){
  $bad=$payload;$bad['answer']='1/0';[$status]=call_api('POST','/practice/answer',$bad,$ta);expect($status,422,'Reject zero denominator');
  $wrong=$payload;$wrong['answer']='999';$wrong['request_id']=uuid_v4();
  [$status,$hint]=call_api('POST','/practice/answer',$wrong,$ta);expect($status,200,'Fraction hint after first wrong answer');
  if(empty($hint['run']['question']['hint'])||$hint['run']['question']['resolved']||$hint['run']['question']['tries']!==1)throw new RuntimeException('Fractions hint/input validation failed');
  [$status]=call_api('POST','/practice/answer',$payload,$tb);expect($status,403,'Other family cannot grade fractions');
  $wrongUnit=$payload;$wrongUnit['unit']='class3-add-subtract';[$status]=call_api('POST','/practice/answer',$wrongUnit,$ta);expect($status,409,'Prevent run substitution between units');
 }
 [$status,$fraction]=call_api('POST','/practice/answer',$payload,$ta);expect($status,200,'Grade fraction skill');
 [$status,$same]=call_api('POST','/practice/answer',$payload,$ta);expect($status,200,'Idempotent fraction answer');
 if($same!==$fraction)throw new RuntimeException('Fraction answer replay changed the score');
 if(!$fraction['run']['question']['resolved'])throw new RuntimeException('Correct fraction not accepted');
 [$status,$fraction]=call_api('POST','/practice/answer',['student_id'=>$sid,'unit'=>'class5-fractions','run_id'=>$run['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'action'=>'next'],$ta);expect($status,200,'Advance fraction question');
}
$summary=$fraction['history'][0];
if(!$fraction['run']['completed']||$summary['unassisted']!==9||count($summary['skills'])!==5)throw new RuntimeException('Fractions summary incorrect');
foreach($summary['skills'] as $skill)if($skill['total']!==2)throw new RuntimeException('Fractions diagnostic is unbalanced');
[$status,$saved]=call_api('GET','/practice?student_id='.$sid.'&unit=class5-fractions',[],$ta);expect($status,200,'Resume saved fractions');
if($saved!==$fraction)throw new RuntimeException('Fractions result was not saved');
[$status,$again]=call_api('POST','/practice/start',['student_id'=>$sid,'unit'=>'class5-fractions'],$ta);expect($status,200,'Fresh fraction retry');
if(in_array(json_encode($again['run']['question']['bars']),$fractionSignatures,true))throw new RuntimeException('Fractions retry repeated an old shaded question');
[$status,$plan]=call_api('GET','/daily-plan?student_id='.$sid.'&curriculum=telangana',[],$ta);expect($status,200,'Grade 5 plan');
$blocks=array_values(array_filter($plan['items'],fn($x)=>$x['kind']==='practice'));
if(($blocks[0]['unit']??'')!=='class5-fractions')throw new RuntimeException('Grade 5 plan did not choose fractions');
[$status]=call_api('POST','/tts',['student_id'=>$sid,'text'=>'hello'],$tb);expect($status,403,'Other family cannot spend voice budget');
db()->prepare('UPDATE auth_sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE token_hash=?')->execute([hash('sha256',$newToken)]);
[$status]=call_api('GET','/auth/me',[],$newToken);expect($status,401,'Expired session denied');
[$status]=call_api('POST','/auth/logout',[],$ta);expect($status,200,'Logout parent');
[$status]=call_api('GET','/auth/me',[],$ta);expect($status,401,'Parent logout revokes token');
for($i=0;$i<31;$i++) { [$status]=call_api('POST','/auth/login',['email'=>'rate-test@example.invalid','password'=>'wrong']); }
expect($status,429,'Throttle repeated logins');
echo "PHP HTTP integration regressions passed\n";
