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
// Exact rational equality, not float rounding or string matching.
foreach([
 [['answer_type'=>'fraction','answer'=>[1,2]],'2/4',true],
 [['answer_type'=>'fraction','answer'=>[1,2]],'3/4',false],
 [['answer_type'=>'fraction','answer'=>[0,12]],'0',true],
 [['answer_type'=>'fraction','answer'=>[7,6]],'14/12',true],
 [['answer_type'=>'fraction','answer'=>[7,6]],'7/12',false],
 [['answer_type'=>'comparison','answer'=>'<'],'<',true],
 [['answer_type'=>'comparison','answer'=>'<'],'>',false],
] as [$q,$answer,$expected]){
 if(practice_answer_matches($q,$answer)!==$expected)throw new RuntimeException('Fraction equivalence/comparison was graded incorrectly');
}
foreach(['1/0','0/000','-1/2','0.5','1e2','1/2/3','1000/2','<img>'] as $bad){
 try{practice_answer_matches(['answer_type'=>'fraction','answer'=>[1,2]],$bad);throw new RuntimeException('Invalid fraction accepted');}catch(RequestFailure $e){if($e->status!==422)throw $e;}
}
$used=[];$seen=[];
for($i=0;$i<1000;$i++){
 $q=fraction_question($used,$i);$seen[$q['skill']]=true;
 if(in_array($q['signature'],$used,true))throw new RuntimeException('Fractions retry repeated a recent question');
 foreach($q['bars'] as $bar)if($bar['d']<2||$bar['d']>24||$bar['n']<0||$bar['n']>$bar['d'])throw new RuntimeException('Invalid fraction visual');
 $expected=match($q['skill']){
  'identify'=>[$q['a'],$q['d']],
  'equivalent'=>$q['a']*intdiv($q['target'],$q['d']),
  'compare'=>($q['a']*$q['other_d'])===($q['b']*$q['d'])?'=':(($q['a']*$q['other_d'])<($q['b']*$q['d'])?'<':'>'),
  'fraction_add'=>[$q['a']+$q['b'],$q['d']],
  'fraction_subtract'=>[$q['a']-$q['b'],$q['d']],
 };
 if($q['answer']!==$expected)throw new RuntimeException('Fraction generator produced a wrong answer key');
 $answer=is_array($expected)?($expected[0]*2).'/'.($expected[1]*2):(string)$expected;
 if(!practice_answer_matches($q,$answer))throw new RuntimeException('Correct generated fraction rejected');
 $used[]=$q['signature'];$used=array_slice($used,-100);
}
if(count($seen)!==5)throw new RuntimeException('Not all fraction skills were exercised');
$sid='fraction-fixture-'.uuid_v4();$old=practice_start($sid);$fraction=practice_start($sid,FRACTIONS_UNIT);
if(practice_read($sid)!==$old)throw new RuntimeException('Starting fractions erased Class 3 progress');
$q=$fraction['run']['question'];$payload=['unit'=>FRACTIONS_UNIT,'run_id'=>$fraction['run']['id'],'question_id'=>$q['id'],'request_id'=>uuid_v4(),'answer'=>'1/0'];
try{practice_update($sid,$payload);throw new RuntimeException('Zero denominator accepted');}catch(RequestFailure $e){if($e->status!==422)throw $e;}
if(practice_read($sid,FRACTIONS_UNIT)['run']['question']['tries']!==0)throw new RuntimeException('Invalid input consumed an attempt');
$payload['unit']=PRACTICE_UNIT;$payload['answer']='1';
try{practice_update($sid,$payload);throw new RuntimeException('Run accepted under wrong unit');}catch(RequestFailure $e){if($e->status!==409)throw $e;}
try{practice_unit('../class5');throw new RuntimeException('Unlisted unit accepted');}catch(RequestFailure $e){if($e->status!==422)throw $e;}
echo "Fractions generation, exact grading, unit isolation and saved Class 3 compatibility passed\n";
