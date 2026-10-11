<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_once __DIR__.'/private_state.php';

const PRACTICE_UNIT='class3-add-subtract';
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
function practice_public(array $s): array {
    $run=$s['run']??null;
    $result=['unit'=>PRACTICE_UNIT,'title'=>'Two-digit addition and subtraction',
        'label'=>'Class 3 foundation / admission practice — independently authored, not an official board lesson',
        'history'=>$s['history']??[]];
    if(!$run || ($run['expires']??0)<time()) return $result+['run'=>null];
    $i=$run['index'];$q=$run['questions'][$i]??null;
    $view=['id'=>$run['id'],'index'=>$i,'total'=>count($run['questions']),'completed'=>$i>=count($run['questions']),
        'correct'=>$run['correct'],'unassisted'=>$run['unassisted'],'expires_at'=>gmdate(DATE_ATOM,$run['expires'])];
    if($q) {
        $op=$q['op']==='+'?'add':'subtract';
        $concept=$q['op']==='+'?'Line up tens and ones. Add the ones first. Ten ones make one ten. Then add the tens. Example: 24 plus 18. Twelve ones become one ten and two ones. Four tens and two ones make 42.':'Line up tens and ones. Subtract the ones first. If there are not enough ones, exchange one ten for ten ones. Then subtract the tens. Example: 42 minus 18. Exchange one ten: twelve ones minus eight is four. Three tens minus one ten is two tens. The answer is 24.';
        $view['question']=['id'=>$q['id'],'a'=>$q['a'],'b'=>$q['b'],'op'=>$q['op'],'prompt'=>'What is '.$q['signature'].'?',
            'concept'=>$concept,'skill'=>$q['skill'],'regroup'=>$q['regroup'],'resolved'=>$q['resolved'],'tries'=>$q['tries']];
        if($q['tries']===1&&!$q['resolved']) $view['question']['hint']=$q['regroup']?($op==='add'?'Add the ones. If you have ten or more, exchange ten ones for one ten.':'Exchange one ten from the top number for ten ones, then subtract.'):'Start with the ones column. Then work with the tens.';
        if($q['resolved']) $view['question']['feedback']=$q['correct']?'Correct. Tap Next when you are ready.':'The answer is '.$q['answer'].'. Let us practise a new question next.';
    }
    return $result+['run'=>$view];
}
function practice_read(string $sid): array {
    return private_state('practice',$sid,fn(array &$s):array=>practice_public($s));
}
function practice_start(string $sid): array {
    consume_limit('practice_start',$sid,30,3600);
    return private_state('practice',$sid,function(array &$s):array {
        $used=$s['used']??[];$questions=[];
        for($i=0;$i<10;$i++){ $q=practice_question($used,$i);$questions[]=$q;$used[]=$q['signature']; }
        $s['used']=array_slice($used,-100);
        $s['run']=['id'=>uuid_v4(),'questions'=>$questions,'index'=>0,'correct'=>0,'unassisted'=>0,'expires'=>time()+86400];
        $s['requests']=[];
        return practice_public($s);
    });
}
function practice_update(string $sid,array $b): array {
    $request=(string)($b['request_id']??'');
    if(!preg_match('/^[a-f0-9-]{36}$/i',$request)) throw new RequestFailure(422,'A request identifier is required');
    $action=(string)($b['action']??'answer');
    if(!in_array($action,['answer','next'],true))throw new RequestFailure(422,'Choose answer or next');
    $answer=$b['answer']??null;
    if($action==='answer' && (!is_string($answer)||!preg_match('/^[0-9]{1,3}$/',$answer)))throw new RequestFailure(422,'Enter a whole number from 0 to 999');
    return private_state('practice',$sid,function(array &$s) use($b,$request,$action,$answer):array {
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
            $q['tries']++;
            if((int)$answer===$q['answer']){$q['correct']=true;$q['resolved']=true;$run['correct']++;if($q['tries']===1)$run['unassisted']++;}
            elseif($q['tries']>=2)$q['resolved']=true;
        }else{
            if(!$q['resolved'])throw new RequestFailure(409,'Try this question before moving on');
            $run['index']++;
            if($run['index']===count($run['questions'])){
                $skills=['addition'=>['correct'=>0,'total'=>0],'subtraction'=>['correct'=>0,'total'=>0]];
                foreach($run['questions'] as $item){$skills[$item['skill']]['total']++;if($item['correct']&&$item['tries']===1)$skills[$item['skill']]['correct']++;}
                $s['history'][]=['run_id'=>$run['id'],'completed_at'=>gmdate(DATE_ATOM),'correct'=>$run['correct'],'unassisted'=>$run['unassisted'],'total'=>10,
                    'status'=>$run['unassisted']>=8?'mastered':($run['correct']>=6?'practised':'needs_help'),'skills'=>$skills];
                $s['history']=array_slice($s['history'],-20);
            }
        }
        $response=practice_public($s);
        $s['requests'][$request]=['fingerprint'=>$fingerprint,'response'=>$response];
        $s['requests']=array_slice($s['requests'],-30,null,true);
        return $response;
    });
}
