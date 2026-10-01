<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/tutor.php';

$method=$_SERVER['REQUEST_METHOD']??'GET';
$uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$path='/' . ltrim((string)preg_replace('#^/api#','',$uri),'/');

try {
    if ($method==='GET' && $path==='/health') {
        $database=false; try { db()->query('SELECT 1'); $database=true; } catch(Throwable $e) {}
        json_response(['ok'=>true,'runtime'=>'php','database'=>$database,'custom_voice'=>(bool)(env_value('FISH_AUDIO_API_KEY')&&env_value('FISH_AUDIO_VOICE_ID'))]);
    }
    if ($method==='GET' && $path==='/config') json_response(['backend'=>'hostinger-php','auth'=>'mysql','custom_voice'=>(bool)(env_value('FISH_AUDIO_API_KEY')&&env_value('FISH_AUDIO_VOICE_ID'))]);

    if ($method==='POST' && $path==='/auth/register') json_response(register_parent(request_json()),201);
    if ($method==='POST' && $path==='/auth/login') json_response(login_user(request_json()));
    if ($method==='POST' && $path==='/auth/logout') { require_user(); logout_user(); json_response(['ok'=>true]); }
    if ($method==='GET' && $path==='/auth/me') json_response(['user'=>public_user(require_user())]);

    if ($method==='GET' && $path==='/students') {
        $u=require_user();
        if ($u['role']==='parent') {
            $s=db()->prepare('SELECT id,full_name,grade,medium,board,student_user_id,created_at FROM students WHERE parent_id=? ORDER BY created_at');
        } else {
            $s=db()->prepare('SELECT id,full_name,grade,medium,board,student_user_id,created_at FROM students WHERE student_user_id=? ORDER BY created_at');
        }
        $s->execute([$u['id']]); json_response(['students'=>$s->fetchAll()]);
    }
    if ($method==='POST' && $path==='/students') {
        $u=require_role('parent'); $b=request_json();
        $name=trim((string)($b['full_name']??'')); $grade=(int)($b['grade']??0);
        $medium=trim((string)($b['medium']??'English')); $board=trim((string)($b['board']??'Maharashtra State Board'));
        if ($name===''||$grade<1||$grade>12) json_response(['error'=>'Full name and grade 1-12 are required'],422);
        $id=uuid_v4(); $s=db()->prepare('INSERT INTO students(id,full_name,grade,medium,board,parent_id) VALUES(?,?,?,?,?,?)');
        $s->execute([$id,$name,$grade,$medium?:'English',$board?:'Maharashtra State Board',$u['id']]);
        json_response(['student'=>['id'=>$id,'full_name'=>$name,'grade'=>$grade,'medium'=>$medium,'board'=>$board]],201);
    }
    if (preg_match('#^/students/([0-9a-f-]{36})$#i',$path,$m)) {
        $student=require_student_access($m[1]);
        if ($method==='GET') json_response(['student'=>$student]);
        if ($method==='PATCH') {
            $u=require_role('parent');
            if (!hash_equals($student['parent_id'],$u['id'])) json_response(['error'=>'Parent ownership required'],403);
            $b=request_json(); $fields=[];$vals=[];
            foreach(['full_name','medium','board'] as $f) if(array_key_exists($f,$b)){ $v=trim((string)$b[$f]); if($v==='')json_response(['error'=>"$f cannot be empty"],422);$fields[]="$f=?";$vals[]=$v; }
            if(array_key_exists('grade',$b)){ $g=(int)$b['grade'];if($g<1||$g>12)json_response(['error'=>'Grade must be 1-12'],422);$fields[]='grade=?';$vals[]=$g; }
            if(!$fields) json_response(['error'=>'No supported fields supplied'],422);
            $vals[]=$student['id']; db()->prepare('UPDATE students SET '.implode(',',$fields).' WHERE id=?')->execute($vals);
            require_student_access($student['id']); $q=db()->prepare('SELECT id,full_name,grade,medium,board,student_user_id,created_at FROM students WHERE id=?');$q->execute([$student['id']]);json_response(['student'=>$q->fetch()]);
        }
    }

    if (preg_match('#^/students/([0-9a-f-]{36})/invite$#i',$path,$m) && $method==='POST') {
        $u=require_role('parent'); $student=require_student_access($m[1]);
        if(!hash_equals($student['parent_id'],$u['id']))json_response(['error'=>'Parent ownership required'],403);
        $code=strtoupper(bin2hex(random_bytes(4))); $hash=hash('sha256',$code); $id=uuid_v4();
        $expires=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+24 hours');
        db()->beginTransaction();
        try {
            db()->prepare('DELETE FROM student_invites WHERE student_id=? AND used_at IS NULL')->execute([$student['id']]);
            db()->prepare('INSERT INTO student_invites(id,student_id,code_hash,created_by,expires_at) VALUES(?,?,?,?,?)')->execute([$id,$student['id'],$hash,$u['id'],$expires->format('Y-m-d H:i:s')]);
            db()->commit();
        } catch(Throwable $e){db()->rollBack();throw $e;}
        json_response(['invite_code'=>$code,'expires_at'=>$expires->format(DATE_ATOM)],201);
    }

    if ($method==='POST' && $path==='/auth/student/register') {
        $b=request_json(); $email=strtolower(trim((string)($b['email']??'')));$password=(string)($b['password']??'');$code=strtoupper(trim((string)($b['invite_code']??'')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||$code==='')json_response(['error'=>'Valid email, password (8+ characters), and invite code required'],422);
        $pdo=db();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT i.id invite_id,i.student_id,s.full_name FROM student_invites i JOIN students s ON s.id=i.student_id WHERE i.code_hash=? AND i.used_at IS NULL AND i.expires_at>UTC_TIMESTAMP() FOR UPDATE');
            $q->execute([hash('sha256',$code)]);$invite=$q->fetch();if(!$invite){$pdo->rollBack();json_response(['error'=>'Invite code is invalid or expired'],400);}
            $uid=uuid_v4();
            $pdo->prepare("INSERT INTO users(id,email,password_hash,role,full_name) VALUES(?,?,?,'student',?)")->execute([$uid,$email,password_hash($password,PASSWORD_DEFAULT),$invite['full_name']]);
            $link=$pdo->prepare('UPDATE students SET student_user_id=? WHERE id=? AND student_user_id IS NULL');$link->execute([$uid,$invite['student_id']]);
            if($link->rowCount()!==1){$pdo->rollBack();json_response(['error'=>'Student profile already has a login'],409);}
            $pdo->prepare('UPDATE student_invites SET used_at=UTC_TIMESTAMP() WHERE id=?')->execute([$invite['invite_id']]);$pdo->commit();
            json_response(['user'=>['id'=>$uid,'email'=>$email,'role'=>'student','full_name'=>$invite['full_name']]]+create_session($uid),201);
        } catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((string)$e->getCode()==='23000')json_response(['error'=>'Email is already registered'],409);throw $e;}
    }


    if ($method==='GET' && $path==='/dashboard') {
        $id=query_param('student_id','')??'';$student=require_student_access($id);$progress=student_progress_rows($id);$sessions=student_sessions($id);$records=student_records($id);
        $scores=array_values(array_map(fn($x)=>(int)$x['score'],array_filter($progress,fn($x)=>$x['score']!==null)));$weak=array_values(array_filter($progress,fn($x)=>(int)$x['attempts']>0));usort($weak,fn($a,$b)=>(int)$a['score']<=>(int)$b['score']);$mastered=array_filter($progress,fn($x)=>(int)$x['score']>=80);
        json_response(['student'=>$student,'stats'=>['sessions'=>count($sessions),'topics'=>count($progress),'mastered'=>count($mastered),'average_mastery'=>$scores?(int)round(array_sum($scores)/count($scores)):0],'week'=>week_metrics($sessions,$records),'progress'=>$progress,'weak_topics'=>array_slice($weak,0,5),'recent_sessions'=>array_slice($sessions,0,8),'last_session'=>$sessions[0]??null,'portfolio_available'=>true,'recent_portfolio'=>array_slice($records,0,8)]);
    }
    if ($method==='GET' && $path==='/portfolio') { $id=query_param('student_id','')??'';require_student_access($id);json_response(['available'=>true,'records'=>student_records($id),'migration'=>null]); }
    if ($method==='POST' && $path==='/portfolio') {
        $u=require_user();$b=request_json();$id=(string)($b['student_id']??'');$student=require_student_access($id);
        if($u['role']!=='parent')json_response(['error'=>'Only a parent can create portfolio records'],403);
        $type=(string)($b['record_type']??'other');$status=(string)($b['status']??'completed');$curr=(string)($b['curriculum']??'nios');$title=trim((string)($b['title']??''));
        if(!in_array($type,RECORD_TYPES,true)||!in_array($status,RECORD_STATUS,true)||!in_array($curr,['maharashtra','nios','cbse','telangana','general'],true)||$title==='')json_response(['error'=>'Invalid portfolio record'],422);
        $occur=(string)($b['occurred_on']??gmdate('Y-m-d'));$due=isset($b['due_date'])&&$b['due_date']!==''?(string)$b['due_date']:null;if(!valid_date($occur)||($due&&!valid_date($due)))json_response(['error'=>'Use YYYY-MM-DD for dates'],422);
        $rid=uuid_v4();$q=db()->prepare('INSERT INTO homeschool_records(id,student_id,record_type,title,curriculum,subject,notes,minutes,status,occurred_on,due_date) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$rid,$id,$type,substr($title,0,255),$curr,substr(trim((string)($b['subject']??'')),0,160)?:null,substr(trim((string)($b['notes']??'')),0,5000)?:null,max(0,min(600,(int)($b['minutes']??0))),$status,$occur,$due]);
        $q=db()->prepare('SELECT * FROM homeschool_records WHERE id=?');$q->execute([$rid]);json_response(['record'=>$q->fetch()],201);
    }
    if (preg_match('#^/portfolio/([0-9a-f-]{36})$#i',$path,$m) && $method==='PATCH') {
        $u=require_role('parent');$b=request_json();$sid=(string)($b['student_id']??'');$student=require_student_access($sid);if(!hash_equals($student['parent_id'],$u['id']))json_response(['error'=>'Parent ownership required'],403);
        $status=(string)($b['status']??'');if(!in_array($status,RECORD_STATUS,true))json_response(['error'=>'Unknown record status'],422);
        $q=db()->prepare('UPDATE homeschool_records SET status=?,occurred_on=CASE WHEN ?="completed" THEN CURDATE() ELSE occurred_on END WHERE id=? AND student_id=?');$q->execute([$status,$status,$m[1],$sid]);if(!$q->rowCount())json_response(['error'=>'Portfolio record not found'],404);
        $q=db()->prepare('SELECT * FROM homeschool_records WHERE id=?');$q->execute([$m[1]]);json_response(['record'=>$q->fetch()]);
    }
    if ($method==='GET' && $path==='/roadmap') {
        $id=query_param('student_id','')??'';$curr=query_param('curriculum','nios')??'nios';if(!in_array($curr,CURRICULA,true))json_response(['error'=>'Unknown curriculum'],400);$student=require_student_access($id);json_response(['curriculum'=>$curr,'level'=>level_label($student,$curr),'subjects'=>roadmap_data($student,$curr,student_progress_rows($id))]);
    }
    if ($method==='GET' && $path==='/daily-plan') {
        $id=query_param('student_id','')??'';$curr=query_param('curriculum','nios')??'nios';if(!in_array($curr,CURRICULA,true))json_response(['error'=>'Unknown curriculum'],400);$student=require_student_access($id);$items=daily_plan_data($student,$curr,student_progress_rows($id));$planned=[];foreach(student_records($id) as $r){if($r['status']!=='planned'||($r['due_date']&&$r['due_date']>gmdate('Y-m-d')))continue;$planned[]=['kind'=>'portfolio','record_id'=>$r['id'],'record_type'=>$r['record_type'],'title'=>$r['title'],'minutes'=>(int)$r['minutes'],'reason'=>'Parent-planned activity','subject'=>$r['subject']];if(count($planned)>=3)break;}$items=array_merge($planned,$items);
        json_response(['curriculum'=>$curr,'level'=>level_label($student,$curr),'items'=>$items,'total_minutes'=>array_sum(array_map(fn($x)=>max(0,(int)($x['minutes']??0)),$items)),'portfolio_available'=>true,'note'=>'This is a Chalo Padhaye study plan. Include breaks and adjust the pace to the child.']);
    }
    if ($method==='GET' && $path==='/catalog') json_response(curriculum_catalog());


    if ($method==='GET' && $path==='/lessons') {
        $sid=query_param('student_id','')??'';$c=query_param('curriculum','')??'';$subject=query_param('subject','')??'';if(!in_array($c,CURRICULA,true))json_response(['error'=>'Unknown curriculum'],400);$student=require_student_access($sid);json_response(lessons_list($student,$c,$subject));
    }
    if ($method==='POST' && $path==='/lessons/start') {
        $b=request_json();$sid=(string)($b['student_id']??'');$c=(string)($b['curriculum']??'');$subject=(string)($b['subject']??'');$lid=(string)($b['lesson_id']??'');if(!in_array($c,CURRICULA,true))json_response(['error'=>'Unknown curriculum'],400);$student=require_student_access($sid);[$lesson,$defs]=lesson_definition($student,$c,$subject,$lid);$p=ensure_progress($student,$c,$subject,$lesson,$defs);json_response(['ok'=>true,'lesson'=>$lesson,'progress'=>$p]);
    }
    if ($method==='POST' && $path==='/lessons/step') {
        $b=request_json();$sid=(string)($b['student_id']??'');$c=(string)($b['curriculum']??'');$subject=(string)($b['subject']??'');$lid=(string)($b['lesson_id']??'');$step=(string)($b['step_id']??'');$assessment=(string)($b['assessment']??'not_answer');$student=require_student_access($sid);[$lesson,$defs]=lesson_definition($student,$c,$subject,$lid);$p=ensure_progress($student,$c,$subject,$lesson,$defs);$templates=lesson_steps_template();$template=null;foreach($templates as$t)if($t['id']===$step)$template=$t;if(!$template||$step==='test')json_response(['error'=>'Unknown lesson step'],400);$rows=ensure_steps($sid,$p);$sr=null;foreach($rows as$r)if($r['step_id']===$step)$sr=$r;if(!$sr)json_response(['error'=>'Lesson step record not found'],404);if($sr['status']==='locked')json_response(['error'=>'Complete the previous lesson step first'],403);
        $attempts=(int)$sr['attempts'];$correct=(int)$sr['correct_count'];if(in_array($assessment,['correct','partial','incorrect'],true)){$attempts++;if($assessment==='correct')$correct++;}$min=(int)($template['minimum_checks']??1);$completed=$step==='learn'?($attempts>=$min&&$correct>=1):($correct>=$min);$score=array_key_exists('score',$b)?max(0,min(100,(int)$b['score'])):$sr['score'];db()->prepare('UPDATE lesson_step_records SET status=?,attempts=?,correct_count=?,score=?,started_at=COALESCE(started_at,UTC_TIMESTAMP()),completed_at=IF(?,UTC_TIMESTAMP(),completed_at),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$completed?'completed':'in_progress',$attempts,$correct,$score,$completed?1:0,$sr['id']]);
        if($completed){$idx=0;foreach($templates as$i=>$t)if($t['id']===$step)$idx=$i;$next=$templates[$idx+1]??null;$percent=$templates?(int)round(($idx+1)*100/count($templates)):0;if($next){db()->prepare("UPDATE lesson_step_records SET status='available',updated_at=UTC_TIMESTAMP() WHERE lesson_progress_id=? AND step_id=? AND status='locked'")->execute([$p['id'],$next['id']]);}$status=($next&&$next['id']==='test')?'test_ready':'in_progress';db()->prepare('UPDATE lesson_progress SET status=?,current_step=?,percent_complete=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,$next['id']??$step,$percent,$p['id']]);$p=get_progress($sid,$c,$subject,$lid);}
        $q=db()->prepare('SELECT * FROM lesson_step_records WHERE id=?');$q->execute([$sr['id']]);json_response(['ok'=>true,'step'=>$q->fetch(),'progress'=>$p]);
    }
    if ($method==='POST' && $path==='/lessons/test') {
        $b=request_json();$sid=(string)($b['student_id']??'');$c=(string)($b['curriculum']??'');$subject=(string)($b['subject']??'');$lid=(string)($b['lesson_id']??'');$student=require_student_access($sid);[$lesson,$defs]=lesson_definition($student,$c,$subject,$lid);$p=ensure_progress($student,$c,$subject,$lesson,$defs);if(!in_array($p['status'],['test_ready','parent_unlocked','mastered'],true))json_response(['error'=>'Complete Learn, Practice and Revision before the chapter test'],403);$qc=max(1,min(20,(int)($b['question_count']??5)));$correct=max(0,min($qc,(int)($b['correct_count']??0)));$score=(int)round($correct*100/$qc);$passed=$score>=lesson_pass_score();$attempt=(int)$p['test_attempts']+1;$summary=json_encode($b['summary']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);db()->prepare('INSERT INTO lesson_test_attempts(id,student_id,lesson_progress_id,attempt_no,score,passed,correct_count,question_count,summary) VALUES(?,?,?,?,?,?,?,?,?)')->execute([uuid_v4(),$sid,$p['id'],$attempt,$score,$passed?1:0,$correct,$qc,$summary]);db()->prepare("UPDATE lesson_step_records SET status=?,attempts=?,correct_count=GREATEST(correct_count,?),score=?,started_at=COALESCE(started_at,UTC_TIMESTAMP()),completed_at=IF(?,UTC_TIMESTAMP(),NULL),updated_at=UTC_TIMESTAMP() WHERE lesson_progress_id=? AND step_id='test'")->execute([$passed?'completed':'in_progress',$attempt,$correct,$score,$passed?1:0,$p['id']]);db()->prepare('UPDATE lesson_progress SET status=?,current_step="test",percent_complete=?,test_score=?,test_attempts=?,mastered_at=IF(?,UTC_TIMESTAMP(),NULL),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$passed?'mastered':'test_ready',$passed?100:75,$score,$attempt,$passed?1:0,$p['id']]);json_response(['ok'=>true,'passed'=>$passed,'score'=>$score,'pass_score'=>lesson_pass_score(),'attempt_no'=>$attempt,'progress'=>get_progress($sid,$c,$subject,$lid)]);
    }
    if ($method==='POST' && $path==='/chat') {
        $b=request_json();$sid=(string)($b['student_id']??'');$message=trim((string)($b['message']??''));if($message==='')json_response(['error'=>'Message required'],422);$student=require_student_access($sid);$session=(string)($b['session_id']??'');if($session!==''){$q=db()->prepare('SELECT id FROM tutor_sessions WHERE id=? AND student_id=?');$q->execute([$session,$sid]);if(!$q->fetch())json_response(['error'=>'Tutor session not found'],404);}else{$session=uuid_v4();db()->prepare('INSERT INTO tutor_sessions(id,student_id,subject,chapter) VALUES(?,?,?,?)')->execute([$session,$sid,substr((string)($b['subject']??''),0,160),null]);}$history=tutor_history($session);db()->prepare("INSERT INTO tutor_messages(id,session_id,role,message) VALUES(?,?,'student',?)")->execute([uuid_v4(),$session,$message]);$answer=groq_tutor($student,$b,$history);db()->prepare("INSERT INTO tutor_messages(id,session_id,role,message) VALUES(?,?,'tutor',?)")->execute([uuid_v4(),$session,$answer['text']]);save_progress_assessment($sid,(string)($b['curriculum']??'maharashtra'),(string)($b['subject']??'general'),$answer);json_response($answer+['session_id'=>$session]);
    }
    if ($method==='POST' && $path==='/end-session') {
        $b=request_json();$sid=(string)($b['student_id']??'');$session=(string)($b['session_id']??'');require_student_access($sid);$q=db()->prepare('UPDATE tutor_sessions SET ended_at=UTC_TIMESTAMP() WHERE id=? AND student_id=?');$q->execute([$session,$sid]);json_response(['ok'=>true]);
    }
    if ($method==='POST' && $path==='/tts') {
        require_user();$b=request_json();$text=trim((string)($b['text']??''));$key=env_value('FISH_AUDIO_API_KEY');$voice=env_value('FISH_AUDIO_VOICE_ID');if(!$key||!$voice)json_response(['error'=>'Custom voice is not configured'],503);if($text==='')json_response(['error'=>'Text required'],422);if(strlen($text)>1800)$text=substr($text,0,1800);
        $ch=curl_init('https://api.fish.audio/v1/tts');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['text'=>$text,'reference_id'=>$voice,'format'=>'mp3','model'=>env_value('FISH_AUDIO_MODEL','s2.1-pro-free')]),CURLOPT_TIMEOUT=>60]);$audio=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$ct=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);if($audio===false||$status<200||$status>=300)json_response(['error'=>'Voice provider request failed'],502);http_response_code(200);header('Content-Type: '.($ct?:'audio/mpeg'));header('Cache-Control: no-store');echo $audio;exit;
    }

    json_response(['error'=>'API route not implemented','path'=>$path],404);
} catch(Throwable $e) {
    error_log('Chalo Padhaye API error: '.$e->getMessage());
    json_response(['error'=>'Internal server error'],500);
}
