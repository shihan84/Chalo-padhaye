<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/php/practice.php';
$used=[];
for($i=0;$i<1000;$i++){
 $q=practice_question($used,$i);
 if(in_array($q['signature'],$used,true))throw new RuntimeException('Repeated recent question');
 if($q['answer']!==($q['op']==='+'?$q['a']+$q['b']:$q['a']-$q['b'])||$q['answer']<0)throw new RuntimeException('Incorrect answer key');
 $used[]= $q['signature'];$used=array_slice($used,-100);
}
$key='fixture-'.uuid_v4();
consume_limit('fixture',$key,2,60);consume_limit('fixture',$key,2,60);
try{consume_limit('fixture',$key,2,60);throw new RuntimeException('Quota bypass');}catch(RequestFailure $e){if($e->status!==429)throw $e;}
private_state('limits','fixture:'.$key,function(array &$s):void{$s['reset']=time()-1;});
consume_limit('fixture',$key,2,60);
$sid='fixture-'.uuid_v4();$p=practice_start($sid);$run=$p['run'];$q=$run['question'];
try{practice_update($sid,['run_id'=>$run['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'action'=>'next']);throw new RuntimeException('Skipped unresolved question');}catch(RequestFailure $e){if($e->status!==409)throw $e;}
for($i=0;$i<2;$i++)$p=practice_update($sid,['run_id'=>$run['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'answer'=>'999']);
if(!$p['run']['question']['resolved']||$p['run']['correct']!==0||$p['run']['unassisted']!==0)throw new RuntimeException('Wrong answers manufactured mastery');
private_state('practice',$sid,function(array &$s):void{$s['run']['expires']=time()-1;});
if(practice_read($sid)['run']!==null)throw new RuntimeException('Expired practice was resumed');
echo "Foundation generation, quotas and grading regressions passed\n";
