<?php
declare(strict_types=1);
require_once __DIR__.'/lessons.php';
require_once __DIR__.'/textbook.php';

function http_json(string $url,array $headers,array $body,int $timeout=45): array {
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_TIMEOUT=>$timeout]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);if($raw===false||$status<200||$status>=300)throw new RuntimeException("Upstream request failed: {$status} {$err}");$j=json_decode($raw,true);if(!is_array($j))throw new RuntimeException('Invalid upstream JSON');return$j;
}
function groq_tutor(array $student,array $b,array $history=[]): array {
    // This deployment has not ported trusted textbook retrieval yet. A client
    // message is not a trusted lesson source. Do not invent official content or
    // bill a provider for an ungrounded response while cutover is incomplete.
    return [
        'text'=>'Lesson material is not available in this deployment yet. Please use the existing learning app or ask your parent for help.',
        'assessment'=>'not_answer','topic'=>'General','student_state'=>'neutral',
        'delivery'=>'normal','sources'=>[],'supplemental'=>false,
        'interaction'=>['type'=>'none','prompt'=>'','options'=>[]],
        'error'=>'textbook_retrieval_unavailable'
    ];
}
function tutor_history(string $session): array {$q=db()->prepare('SELECT role,message,created_at FROM tutor_messages WHERE session_id=? ORDER BY created_at DESC LIMIT 9');$q->execute([$session]);return array_reverse($q->fetchAll());}
function save_progress_assessment(string $sid,string $curr,string $subject,array $answer): void {$a=$answer['assessment']??'not_answer';if(!in_array($a,['correct','partial','incorrect'],true))return;$topic=substr(trim((string)($answer['topic']??'General')),0,120);$key=$curr.':'.$subject;$q=db()->prepare('SELECT * FROM student_progress WHERE student_id=? AND subject=? AND topic=? ORDER BY updated_at DESC LIMIT 1');$q->execute([$sid,$key,$topic]);$r=$q->fetch();$old=(int)($r['score']??0);$attempts=(int)($r['attempts']??0)+1;$delta=['correct'=>12,'partial'=>5,'incorrect'=>-3][$a];$score=max(0,min(100,$old+$delta));$status=$score>=80?'mastered':($score>=45?'developing':'started');if($r)db()->prepare('UPDATE student_progress SET score=?,attempts=?,status=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$score,$attempts,$status,$r['id']]);else db()->prepare('INSERT INTO student_progress(id,student_id,subject,topic,score,attempts,status) VALUES(?,?,?,?,?,?,?)')->execute([uuid_v4(),$sid,$key,$topic,$score,$attempts,$status]);}
