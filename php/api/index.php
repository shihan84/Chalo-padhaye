<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth.php';

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

    json_response(['error'=>'API route not implemented','path'=>$path],404);
} catch(Throwable $e) {
    error_log('Chalo Padhaye API error: '.$e->getMessage());
    json_response(['error'=>'Internal server error'],500);
}
