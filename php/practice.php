<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_once __DIR__.'/private_state.php';
require_once __DIR__.'/fractions.php';

const PRACTICE_UNIT='class3-add-subtract';
const FRACTIONS_UNIT='class5-fractions';
function practice_unit(mixed $unit=PRACTICE_UNIT): string {
    if(!is_string($unit)||!in_array($unit,[PRACTICE_UNIT,FRACTIONS_UNIT],true))throw new RequestFailure(422,'Choose an available practice unit');
    return $unit;
}
function practice_key(string $sid,string $unit): string {
    // Keep the original key so already-saved Class 3 progress remains intact.
    return $unit===PRACTICE_UNIT?$sid:$sid.':'.$unit;
}
function practice_answer_matches(array $q,string $answer): bool {
    $type=$q['answer_type']??'number';
    if($type==='fraction'){
        $value=fraction_value($answer);if(!$value)throw new RequestFailure(422,'Enter a fraction such as 3/4, or a whole number. The denominator must be positive');
        return $value[0]*$q['answer'][1]===$q['answer'][0]*$value[1];
    }
    if($type==='comparison'){
        if(!in_array($answer,['<','=','>'],true))throw new RequestFailure(422,'Choose less than, equal to or greater than');
        return $answer===$q['answer'];
    }
    if(!preg_match('/^[0-9]{1,3}$/',$answer))throw new RequestFailure(422,'Enter a whole number from 0 to 999');
    return (int)$answer===$q['answer'];
}

function practice_question(array $used,int $index): array {
    // Alternate addition/subtraction. Never repeat an expression within the last 100 questions.
    for($tries=0;$tries<500;$tries++) {
        $a=random_int(12,89);$b=random_int(11,89);$op=$index%2===0?'+':'−';
        if($op==='−' && $a<$b) [$a,$b]=[$b,$a];
        $signature="$a $op $b";
        if(in_array($signature,$used,true))continue;
        $regroup=$op==='+'?($a%10+$b%10>=10):($a%10<$b%10);
        return ['id'=>uuid_v4(),'a'=>$a,'b'=>$b,'op'=>$op,'signature'=>$signature,
            'answer'=>$op==='+'?$a+$b:$a-$b,'skill'=>$op==='+'?'addition':'subtraction',
            'regroup'=>$regroup,'tries'=>0,'resolved'=>false,'correct'=>false];
    }
    throw new RequestFailure(503,'Please try a fresh practice later');
}
function practice_public(array $s,string $unit=PRACTICE_UNIT): array {
    $run=$s['run']??null;
    $result=['unit'=>$unit,'title'=>$unit===FRACTIONS_UNIT?'Fractions: parts, equivalents, comparison and arithmetic':'Two-digit addition and subtraction',
        'label'=>($unit===FRACTIONS_UNIT?'Class 5':'Class 3').' foundation / admission practice — independently authored, not an official board lesson',
        'history'=>$s['history']??[]];
    if(!$run || ($run['expires']??0)<time()) return $result+['run'=>null];
    $i=$run['index'];$q=$run['questions'][$i]??null;
    $view=['id'=>$run['id'],'index'=>$i,'total'=>count($run['questions']),'completed'=>$i>=count($run['questions']),
        'correct'=>$run['correct'],'unassisted'=>$run['unassisted'],'expires_at'=>gmdate(DATE_ATOM,$run['expires'])];
    if($q && $unit===FRACTIONS_UNIT){
        $view['question']=array_intersect_key($q,array_flip(['id','a','b','d','target','other_d','op','prompt','concept','skill','bars','speech','answer_type','resolved','tries']));
        if($q['tries']===1&&!$q['resolved'])$view['question']['hint']=$q['hint'];
        if($q['resolved'])$view['question']['feedback']=$q['correct']?'Correct. Tap Next when you are ready.':'The answer is '.(is_array($q['answer'])?fraction_text($q['answer']):$q['answer']).'. Let us practise a new question next.';
    }elseif($q) {
        $op=$q['op']==='+'?'add':'subtract';
        $concept=$q['op']==='+'?'Line up tens and ones. Add the ones first. Ten ones make one ten. Then add the tens. Example: 24 plus 18. Twelve ones become one ten and two ones. Four tens and two ones make 42.':'Line up tens and ones. Subtract the ones first. If there are not enough ones, exchange one ten for ten ones. Then subtract the tens. Example: 42 minus 18. Exchange one ten: twelve ones minus eight is four. Three tens minus one ten is two tens. The answer is 24.';
        $view['question']=['id'=>$q['id'],'a'=>$q['a'],'b'=>$q['b'],'op'=>$q['op'],'prompt'=>'What is '.$q['signature'].'?',
            'concept'=>$concept,'skill'=>$q['skill'],'regroup'=>$q['regroup'],'resolved'=>$q['resolved'],'tries'=>$q['tries']];
        if($q['tries']===1&&!$q['resolved']) $view['question']['hint']=$q['regroup']?($op==='add'?'Add the ones. If you have ten or more, exchange ten ones for one ten.':'Exchange one ten from the top number for ten ones, then subtract.'):'Start with the ones column. Then work with the tens.';
        if($q['resolved']) $view['question']['feedback']=$q['correct']?'Correct. Tap Next when you are ready.':'The answer is '.$q['answer'].'. Let us practise a new question next.';
    }
    return $result+['run'=>$view];
}
function practice_read(string $sid,string $unit=PRACTICE_UNIT): array {
    $unit=practice_unit($unit);return private_state('practice',practice_key($sid,$unit),fn(array &$s):array=>practice_public($s,$unit));
}
function practice_start(string $sid,string $unit=PRACTICE_UNIT): array {
    $unit=practice_unit($unit);
    consume_limit('practice_start',$sid,30,3600);
    return private_state('practice',practice_key($sid,$unit),function(array &$s) use($unit):array {
        $used=$s['used']??[];$questions=[];
        for($i=0;$i<10;$i++){ $q=$unit===FRACTIONS_UNIT?fraction_question($used,$i):practice_question($used,$i);$questions[]=$q;$used[]=$q['signature']; }
        $s['used']=array_slice($used,-100);
        $s['run']=['id'=>uuid_v4(),'questions'=>$questions,'index'=>0,'correct'=>0,'unassisted'=>0,'expires'=>time()+86400];
        $s['requests']=[];
        return practice_public($s,$unit);
    });
}
function practice_update(string $sid,array $b): array {
    $unit=practice_unit($b['unit']??PRACTICE_UNIT);
    $request=(string)($b['request_id']??'');
    if(!preg_match('/^[a-f0-9-]{36}$/i',$request)) throw new RequestFailure(422,'A request identifier is required');
    $action=(string)($b['action']??'answer');
    if(!in_array($action,['answer','next'],true))throw new RequestFailure(422,'Choose answer or next');
    $answer=$b['answer']??null;
    if($action==='answer' && (!is_string($answer)||strlen($answer)>7))throw new RequestFailure(422,'Enter a short numeric answer');
    return private_state('practice',practice_key($sid,$unit),function(array &$s) use($b,$request,$action,$answer,$unit):array {
        $fingerprint=hash('sha256',json_encode($b,JSON_THROW_ON_ERROR));
        if(isset($s['requests'][$request])){
            $saved=$s['requests'][$request];
            if($saved['fingerprint']!==$fingerprint)throw new RequestFailure(409,'Request identifier was already used');
            return $saved['response'];
        }
        if(!isset($s['run'])||$s['run']['expires']<time()||!hash_equals($s['run']['id'],(string)($b['run_id']??'')))throw new RequestFailure(409,'This practice has expired. Start a fresh practice');
        $run=&$s['run'];$i=$run['index'];
        if(!isset($run['questions'][$i])||!hash_equals($run['questions'][$i]['id'],(string)($b['question_id']??'')))throw new RequestFailure(409,'This question has already changed');
        $q=&$run['questions'][$i];
        if($action==='answer'){
            if($q['resolved'])throw new RequestFailure(409,'This question has already been answered');
            $correct=practice_answer_matches($q,$answer);
            $q['tries']++;
            if($correct){$q['correct']=true;$q['resolved']=true;$run['correct']++;if($q['tries']===1)$run['unassisted']++;}
            elseif($q['tries']>=2)$q['resolved']=true;
        }else{
            if(!$q['resolved'])throw new RequestFailure(409,'Try this question before moving on');
            $run['index']++;
            if($run['index']===count($run['questions'])){
                $skills=[];
                foreach($run['questions'] as $item){$skills[$item['skill']]??=['correct'=>0,'total'=>0];$skills[$item['skill']]['total']++;if($item['correct']&&$item['tries']===1)$skills[$item['skill']]['correct']++;}
                $s['history'][]=['run_id'=>$run['id'],'completed_at'=>gmdate(DATE_ATOM),'correct'=>$run['correct'],'unassisted'=>$run['unassisted'],'total'=>10,
                    'status'=>$run['unassisted']>=8?'mastered':($run['correct']>=6?'practised':'needs_help'),'skills'=>$skills];
                $s['history']=array_slice($s['history'],-20);
            }
        }
        $response=practice_public($s,$unit);
        $s['requests'][$request]=['fingerprint'=>$fingerprint,'response'=>$response];
        $s['requests']=array_slice($s['requests'],-30,null,true);
        return $response;
    });
}
