<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/php/maths_course.php';
function maths_expect(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function maths_fail(callable $fn,int $status):void{try{$fn();throw new RuntimeException('Expected rejection');}catch(RequestFailure $e){maths_expect($e->status===$status,'Wrong rejection status');}}
function maths_hidden(array $student):array{return private_state('maths_course',$student['id'].':'.$student['grade'],fn(array &$s):array=>$s['run']['question']);}
function maths_fixture_answer(array $q):string{return is_array($q['answer'])?$q['answer'][0].'/'.$q['answer'][1]:(string)$q['answer'];}
$all=[];
foreach([3,5] as $grade){
 $student=['id'=>'maths-fixture-'.uuid_v4(),'grade'=>$grade,'parent_id'=>'maths-parent-'.uuid_v4()];$course=maths_course_definition($student);maths_expect(count($course['chapters'])===($grade===3?14:16),'Chapter coverage mismatch');
 foreach($course['chapters'] as $chapter)foreach($chapter['skills'] as $skill){$all[$skill]=true;for($i=0;$i<30;$i++){$q=maths_question($skill,$grade);maths_expect(maths_answer_matches($q,maths_fixture_answer($q)),'Generator rejects its answer');maths_expect(substr_count($q['prompt'],'?')<=1,'Multiple learner questions');}}
 $state=maths_state($student);$first=$course['chapters'][0]['id'];$second=$course['chapters'][1]['id'];
 maths_fail(fn()=>maths_state($student,['action'=>'start','chapter_id'=>$second,'request_id'=>uuid_v4()]),403);
 $state=maths_state($student,['action'=>'start','chapter_id'=>$first,'request_id'=>uuid_v4()]);$q=maths_hidden($student);
 maths_expect(!isset($state['run']['question']['answer'])&&!isset($state['run']['question']['hint']),'Answer or early hint leaked');
 $bad=$q['answer_type']==='choice'?current(array_values(array_diff($q['choices'],[(string)$q['answer']]))):'999999';
 $body=['action'=>'answer','run_id'=>$state['run']['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'answer'=>$bad];
 $state=maths_state($student,$body);maths_expect(!$state['run']['question']['resolved']&&isset($state['run']['question']['hint']),'First wrong needs a hint');
 maths_fail(fn()=>maths_state($student,['action'=>'next','run_id'=>$state['run']['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4()]),409);
 maths_state($student,$body);maths_expect(maths_hidden($student)['tries']===1,'Duplicate answer consumed another attempt');
 maths_fail(fn()=>maths_state($student,array_replace($body,['answer'=>maths_fixture_answer($q)])),409);
 maths_fail(fn()=>maths_state($student,array_replace($body,['request_id'=>uuid_v4(),'answer'=>['malformed']])),422);
 // Complete guided learning, practice and the checkpoint using server-side fixture keys only.
 for($i=0;$i<40&&!$state['run']['completed'];$i++){
  if(!$state['run']['question']['resolved']){$q=maths_hidden($student);$state=maths_state($student,['action'=>'answer','run_id'=>$state['run']['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'answer'=>maths_fixture_answer($q),'score'=>100,'assessment'=>'correct']);}
  $state=maths_state($student,['action'=>'next','run_id'=>$state['run']['id'],'question_id'=>$state['run']['question']['id'],'request_id'=>uuid_v4()]);
 }
 maths_expect($state['run']['completed']&&$state['chapters'][0]['best_score']===100&&$state['chapters'][1]['status']==='available','Checkpoint did not unlock next chapter');
 maths_expect(maths_state($student)===$state,'Reload changed saved progress');
 $other=['id'=>'maths-other-'.uuid_v4(),'grade'=>$grade,'parent_id'=>$student['parent_id']];maths_expect(maths_state($other)['mastered_checkpoints']===0,'Progress leaked between children');
 maths_state($student,['action'=>'revision','chapter_id'=>$first,'request_id'=>uuid_v4()]);maths_expect(maths_state($student)['chapters'][0]['status']==='checkpoint_passed','Revision erased checkpoint');
 // Failed tests must revise; assisted correct answers are not independent.
 $state=maths_state($student,['action'=>'start','chapter_id'=>$second,'request_id'=>uuid_v4()]);
 for($i=0;$i<30;$i++){
  $q=maths_hidden($student);
  if($state['run']['stage']==='test'){
   $wrong=$q['answer_type']==='choice'?current(array_values(array_diff($q['choices'],[(string)$q['answer']]))):'999999';
   $state=maths_state($student,['action'=>'answer','run_id'=>$state['run']['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'answer'=>$wrong]);
  }
  $state=maths_state($student,['action'=>'answer','run_id'=>$state['run']['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'answer'=>maths_fixture_answer($q)]);
  $stage=$state['run']['stage'];$state=maths_state($student,['action'=>'next','run_id'=>$state['run']['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4()]);
  if($stage==='test'&&$state['run']['stage']==='revision')break;
 }
 maths_expect($state['run']['stage']==='revision'&&$state['chapters'][1]['best_score']===0&&$state['chapters'][2]['status']==='locked','Assisted answers manufactured mastery');
}
foreach(['0.50','1/2','2/4'] as $answer)maths_expect(maths_answer_matches(['answer_type'=>'rational','answer'=>[1,2]],$answer),'Exact equivalent rejected');
foreach(['1/0','1e2','-1','1000000000000000','<script>'] as $bad)maths_fail(fn()=>maths_answer_matches(['answer_type'=>'rational','answer'=>[1,2]],$bad),422);
maths_fail(fn()=>maths_course_definition(['grade'=>4]),422);
echo 'Maths course generation, exact grading, hidden keys, hints, replay protection, progress, chapter locks, failed-test revision and child isolation passed; '.count($all)." skill generators exercised\n";
// Independent visible-prompt oracles catch answer-key mistakes, not just self-consistency.
foreach([3,5] as $grade)foreach(['add','sub','multiply','divide','fraction','fraction_add','length','weight','capacity','table_total','perimeter','area','decimal','roman'] as $skill)for($i=0;$i<30;$i++){
 $q=maths_question($skill,$grade);$p=$q['prompt'];preg_match_all('/\d+/',$p,$match);$v=array_map('intval',$match[0]);
 $expected=match($skill){
  'add'=>$v[0]+$v[1], 'sub'=>$v[0]-$v[1], 'multiply'=>$v[0]*$v[1], 'divide'=>intdiv($v[0],$v[1]),
  'fraction'=>[$v[0],$v[1]],'fraction_add'=>[$v[0]+$v[2],$v[1]],
  'length'=>$v[0]*100,'weight','capacity'=>$v[0]*1000,
  'table_total'=>array_sum($q['visual']['values'])*($q['visual']['scale']??1),
  'perimeter'=>($v[0]+$v[1])*2,'area'=>$v[0]*$v[1], 'decimal'=>[$v[0]*10+$v[1],100],
  'roman'=>null,
 };
 if($skill==='roman'){
  preg_match('/Write ([IVX]+) as/',$p,$m);$letters=['I'=>1,'V'=>5,'X'=>10];$expected=0;
  for($j=0;$j<strlen($m[1]);$j++){$now=$letters[$m[1][$j]];$next=$letters[$m[1][$j+1]??'I'];$expected+=$j+1<strlen($m[1])&&$now<$next?-$now:$now;}
 }
 maths_expect($q['answer']===$expected,'Visible prompt answer key incorrect: '.$skill);
}
echo "Independent arithmetic, units, pictograph key, perimeter/area, rational, decimal and Roman prompt oracles passed\n";

foreach([3,5] as $grade)for($i=0;$i<100;$i++){
 $q=maths_question('place',$grade);preg_match('/digit (\d) in the (tens|hundreds|thousands|ten-thousands) place of (\d+)/',$q['prompt'],$m);
 $base=['tens'=>10,'hundreds'=>100,'thousands'=>1000,'ten-thousands'=>10000][$m[2]];$number=(int)$m[3];
 maths_expect($q['answer']===intdiv($number,$base)%10*$base,'Place-value answer does not match visible digit');
 maths_expect($number<($grade===3?1000:100000),'Place value exceeds selected number range');
}
echo "Grade-bounded place-value prompt oracle passed\n";
