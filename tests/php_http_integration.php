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
echo "PHP HTTP integration regressions passed\n";
