<?php
declare(strict_types=1);
// Run against the isolated CI fixture server only.
$base='http://127.0.0.1:8080/api';
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
[$status]=call_api('POST','/auth/register',['email'=>'long@example.invalid','password'=>str_repeat('x',73)]);expect($status,422,'Reject silently truncated bcrypt password');
[$status]=call_api('POST','/chat',['message'=>str_repeat('x',70000)]);expect($status,413,'Bound body size before database access');
[$status]=call_api('GET','/students');expect($status,401,'Missing token denied');
[$status]=call_api('POST','/auth/logout',[],$tc);expect($status,200,'Logout student');
[$status]=call_api('GET','/auth/me',[],$tc);expect($status,401,'Revoked token denied');
echo "PHP HTTP integration regressions passed\n";
