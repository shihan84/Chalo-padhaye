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
[$status,$a]=call_api('POST','/auth/register',['email'=>'parent-a@example.invalid','password'=>'fixture-password-a']);expect($status,201,'Parent A registration');$ta=$a['access_token'];
[$status,$b]=call_api('POST','/auth/register',['email'=>'parent-b@example.invalid','password'=>'fixture-password-b']);expect($status,201,'Parent B registration');$tb=$b['access_token'];
[$status,$row]=call_api('POST','/students',['full_name'=>'Fixture Child','grade'=>3],$ta);expect($status,201,'Student creation');$sid=$row['student']['id'];
[$status]=call_api('GET','/students/'.$sid,[],$tb);expect($status,403,'Cross-parent profile denied');
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
// Exercise real lesson progression, replay and reviews against MySQL.
$defs=lesson_defs('maharashtra',3,'math');
$lesson=['student_id'=>$sid,'curriculum'=>'maharashtra','subject'=>'math','lesson_id'=>$defs[0]['id']];
[$status,$opened]=call_api('POST','/lessons/start',$lesson,$ta);expect($status,200,'Start structured lesson');
foreach(['learn'=>2,'practice'=>3,'revision'=>2] as $step=>$count){
    for($i=0;$i<$count;$i++){
        [$status,$result]=call_api('POST','/lessons/step',$lesson+['step_id'=>$step,'assessment'=>'correct'],$ta);
        expect($status,200,'Complete '.$step);
    }
}
[$status,$replay]=call_api('POST','/lessons/step',$lesson+['step_id'=>'learn','assessment'=>'correct'],$ta);expect($status,200,'Replay completed step');
if($replay['progress']['current_step']!=='test')throw new RuntimeException('Replay regressed chapter');
[$status]=call_api('POST','/lessons/test',$lesson+['question_count'=>1,'correct_count'=>1],$ta);expect($status,422,'Reject incomplete test');
[$status,$passed]=call_api('POST','/lessons/test',$lesson+['question_count'=>5,'correct_count'=>5],$ta);expect($status,200,'Pass full chapter test');
if($passed['progress']['status']!=='mastered')throw new RuntimeException('Passing chapter was not mastered');
[$status,$review]=call_api('POST','/lessons/test',$lesson+['question_count'=>5,'correct_count'=>0],$ta);expect($status,200,'Record failed review');
if($review['progress']['status']!=='mastered'||(int)$review['progress']['percent_complete']!==100)throw new RuntimeException('Failed review erased mastery');

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
db()->prepare('UPDATE auth_sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE token_hash=?')->execute([hash('sha256',$newToken)]);
[$status]=call_api('GET','/auth/me',[],$newToken);expect($status,401,'Expired session denied');
[$status]=call_api('POST','/auth/logout',[],$ta);expect($status,200,'Logout parent');
[$status]=call_api('GET','/auth/me',[],$ta);expect($status,401,'Parent logout revokes token');
echo "PHP HTTP integration regressions passed\n";
