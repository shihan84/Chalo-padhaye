<?php
declare(strict_types=1);
require_once __DIR__.'/textbook.php';
require_once __DIR__.'/maths_questions.php';
function maths_course_definition(array $student): array {
    $grade=(int)$student['grade'];
    if(!in_array($grade,[3,5],true))throw new RequestFailure(422,'Guided mathematics is available for Classes 3 and 5');
    // A child may browse other publishers, but this learning path is explicitly Maharashtra.
    $data=json_decode((string)file_get_contents(dirname(__DIR__).'/data/maths_courses.json'),true,512,JSON_THROW_ON_ERROR);
    $course=$data['courses'][(string)$grade];$source=null;
    foreach(textbook_manifest() as $book)if($book['id']===$course['book_id'])$source=$book;
    if(!$source||!hash_equals($source['sha256'],$course['sha256'])||count($source['chapters'])!==count($course['chapters']))throw new RequestFailure(503,'The textbook alignment needs review');
    foreach($course['chapters'] as $i=>$chapter)if($source['chapters'][$i]['title']!==$chapter['title']||$source['chapters'][$i]['pdf_page']!==$chapter['pdf_page'])throw new RequestFailure(503,'The chapter alignment needs review');
    return $course+['grade'=>$grade,'version'=>$data['version'],'scope'=>$data['scope'],'url'=>$source['url']];
}
function maths_chapter(array $course,string $id): array {
    foreach($course['chapters'] as $chapter)if($chapter['id']===$id)return $chapter;
    throw new RequestFailure(422,'Choose a chapter from this class');
}
function maths_unlocked(array $course,array $s,string $id): bool {
    foreach($course['chapters'] as $i=>$chapter)if($chapter['id']===$id)return $i===0||($s['chapters'][$course['chapters'][$i-1]['id']]['mastered']??false);
    return false;
}
function maths_new_question(array $course,array $chapter,array $run,array $s): array {
    $skills=$chapter['skills'];
    if($run['stage']==='revision'){
        usort($skills,fn($a,$b)=>(($s['chapters'][$chapter['id']]['skills'][$a]['independent']??0)/max(1,$s['chapters'][$chapter['id']]['skills'][$a]['total']??0))<=>(($s['chapters'][$chapter['id']]['skills'][$b]['independent']??0)/max(1,$s['chapters'][$chapter['id']]['skills'][$b]['total']??0)));
        $skill=$skills[$run['index']%2===0?0:($run['index']%count($skills))];
    }else $skill=$skills[$run['index']%count($skills)];
    $q=maths_question($skill,$course['grade']);
    // Retry numeric/diagram generators where variants exist; small fixed-fact pools may repeat.
    for($i=0;$i<20&&in_array($q['prompt'],$run['recent']??[],true);$i++)$q=maths_question($skill,$course['grade']);
    return $q;
}
function maths_public(array $course,array $s): array {
    $chapters=[];$mastered=0;
    foreach($course['chapters'] as $chapter){$p=$s['chapters'][$chapter['id']]??[];$done=$p['mastered']??false;if($done)$mastered++;
        $weak=[];foreach($p['skills']??[] as $skill=>$v)if($v['independent']<$v['total'])$weak[]=$skill;
        $chapters[]=$chapter+['status'=>$done?'checkpoint_passed':(maths_unlocked($course,$s,$chapter['id'])?'available':'locked'),'best_score'=>$p['best_score']??null,'test_attempts'=>$p['test_attempts']??0,'skills_progress'=>$p['skills']??[],'weak_skills'=>$weak,'history'=>$p['history']??[]];
    }
    $run=$s['run']??null;$view=null;
    if($run){$view=array_intersect_key($run,array_flip(['id','chapter_id','stage','index','total','independent','completed','stage_feedback','revision_only']));$q=$run['question']??null;
        if($q){$public=array_intersect_key($q,array_flip(['id','skill','prompt','answer_type','choices','visual','tries','resolved']));
            if($run['stage']!=='test')$public['concept']=$q['concept'];
            if($q['tries']===1&&!$q['resolved'])$public['hint']=$q['hint'];
            if($q['resolved'])$public['feedback']=$q['independent']?'Correct without help.':($q['correct']??false?'Correct with help.':$q['worked']);
            $view['question']=$public;
        }
    }
    return ['curriculum'=>'maharashtra','grade'=>$course['grade'],'book_id'=>$course['book_id'],'edition'=>$course['edition'],'scope'=>$course['scope'],'source_url'=>$course['url'],'pass_score'=>80,'mastered_checkpoints'=>$mastered,'total_chapters'=>count($chapters),'chapters'=>$chapters,'run'=>$view];
}
function maths_state(array $student,?array $body=null): array {
    $course=maths_course_definition($student);$key=$student['id'].':'.$course['grade'];
    if($body===null)return private_state('maths_course',$key,fn(array &$s):array=>maths_public($course,$s));
    $action=$body['action']??'';$request=$body['request_id']??'';
    if(!is_string($request)||!preg_match('/^[a-f0-9-]{36}$/i',$request)||!is_string($action)||!in_array($action,['start','revision','answer','next'],true))throw new RequestFailure(422,'Choose a learning action and request identifier');
    consume_limit('maths_actions',$student['parent_id'],1500,86400);
    return private_state('maths_course',$key,function(array &$s)use($course,$body,$action,$request):array{
        if(isset($s['version'])&&$s['version']!==$course['version'])throw new RequestFailure(409,'This course was updated; ask your parent to review saved progress');
        $fingerprint=hash('sha256',json_encode($body,JSON_THROW_ON_ERROR));
        if(isset($s['requests'][$request])){
            if(!hash_equals($s['requests'][$request]['fingerprint'],$fingerprint))throw new RequestFailure(409,'Request identifier was already used');
            // Return current state: delayed duplicate replies cannot replace later progress.
            return maths_public($course,$s);
        }
        if($action==='start'||$action==='revision'){
            $id=$body['chapter_id']??'';if(!is_string($id))throw new RequestFailure(422,'Choose a chapter');$chapter=maths_chapter($course,$id);
            if(!maths_unlocked($course,$s,$id))throw new RequestFailure(403,'Pass the previous chapter skill checkpoint first');
            if($action==='revision'&&empty($s['chapters'][$id]['skills']))throw new RequestFailure(409,'Try the chapter before choosing targeted revision');
            if($action==='start'&&isset($s['run'])&&!$s['run']['completed']&&$s['run']['chapter_id']===$id)return maths_public($course,$s);
            $s['run']=['id'=>uuid_v4(),'chapter_id'=>$id,'stage'=>$action==='revision'?'revision':'learn','index'=>0,'total'=>$action==='revision'?4:3,'independent'=>0,'completed'=>false,'recent'=>[],'revision_only'=>$action==='revision'];
            $s['run']['question']=maths_new_question($course,$chapter,$s['run'],$s);
        }else{
            $run=&$s['run'];
            if(!$run||$run['completed']||!is_string($body['run_id']??null)||!hash_equals($run['id'],$body['run_id'])||!is_string($body['question_id']??null)||!hash_equals($run['question']['id'],$body['question_id']))throw new RequestFailure(409,'This question changed. Reload the saved lesson');
            $chapter=maths_chapter($course,$run['chapter_id']);$q=&$run['question'];
            if($action==='answer'){
                if($q['resolved'])throw new RequestFailure(409,'This question is already answered');
                $correct=maths_answer_matches($q,$body['answer']??null);$q['tries']++;
                if($correct||$q['tries']>=2){$q['resolved']=true;$q['correct']=$correct;$q['independent']=$correct&&$q['tries']===1;if($q['independent'])$run['independent']++;
                    $p=&$s['chapters'][$chapter['id']];$p['skills'][$q['skill']]??=['total'=>0,'independent'=>0];$p['skills'][$q['skill']]['total']++;if($q['independent'])$p['skills'][$q['skill']]['independent']++;unset($p);
                }
            }else{
                if(!$q['resolved'])throw new RequestFailure(409,'Try the question before moving on');
                $run['recent'][]=$q['prompt'];$run['recent']=array_slice($run['recent'],-30);$run['index']++;
                if($run['index']>=$run['total']){
                    $stage=$run['stage'];$score=(int)round(100*$run['independent']/$run['total']);$run['stage_feedback']="Completed $stage: {$run['independent']}/{$run['total']} independently correct.";
                    if($stage==='test'){
                        $p=&$s['chapters'][$chapter['id']];$p['test_attempts']=($p['test_attempts']??0)+1;$p['best_score']=max($score,$p['best_score']??0);$passed=$score>=80;$p['mastered']=($p['mastered']??false)||$passed;
                        $p['history'][]=['completed_at'=>gmdate(DATE_ATOM),'score'=>$score,'independent'=>$run['independent'],'total'=>$run['total'],'passed'=>$passed];$p['history']=array_slice($p['history'],-10);unset($p);
                        if($passed){$run['completed']=true;unset($run['question']);}
                        else{$run['stage']='revision';$run['total']=4;$run['index']=0;$run['independent']=0;}
                    }elseif($stage==='revision'&&$run['revision_only']){$run['completed']=true;unset($run['question']);}
                    else{$run['stage']=$stage==='learn'?'practice':($stage==='practice'?($score>=67?'test':'revision'):'practice');$run['total']=$run['stage']==='test'?5:($run['stage']==='revision'?4:6);$run['index']=0;$run['independent']=0;}
                }
                unset($q);
                if(!$run['completed'])$run['question']=maths_new_question($course,$chapter,$run,$s);
            }
            unset($run);
        }
        $s['version']=$course['version'];$s['updated_at']=gmdate(DATE_ATOM);$s['requests'][$request]=['fingerprint'=>$fingerprint];$s['requests']=array_slice($s['requests'],-100,null,true);
        return maths_public($course,$s);
    });
}
