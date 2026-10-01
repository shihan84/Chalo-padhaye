<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function uuid_v4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function bearer_token(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', trim($h), $m)) return null;
    return trim($m[1]);
}
function public_user(array $u): array {
    return ['id'=>$u['id'],'email'=>$u['email'],'role'=>$u['role'],'full_name'=>$u['full_name'] ?? null];
}
function create_session(string $userId): array {
    $raw = bin2hex(random_bytes(32));
    $id = uuid_v4();
    $hours = max(1, min(720, (int)(env_value('SESSION_HOURS','168') ?? '168')));
    $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify("+{$hours} hours");
    $s=db()->prepare('INSERT INTO auth_sessions(id,user_id,token_hash,expires_at) VALUES(?,?,?,?)');
    $s->execute([$id,$userId,hash('sha256',$raw),$expires->format('Y-m-d H:i:s')]);
    return ['access_token'=>$raw,'token_type'=>'bearer','expires_at'=>$expires->format(DATE_ATOM)];
}
function require_user(): array {
    $raw=bearer_token();
    if (!$raw) json_response(['error'=>'Login required'],401);
    $s=db()->prepare("SELECT u.id,u.email,u.role,u.full_name,a.id session_id FROM auth_sessions a JOIN users u ON u.id=a.user_id WHERE a.token_hash=? AND a.expires_at>UTC_TIMESTAMP() LIMIT 1");
    $s->execute([hash('sha256',$raw)]);
    $u=$s->fetch();
    if (!$u) json_response(['error'=>'Session expired'],401);
    db()->prepare('UPDATE auth_sessions SET last_used_at=UTC_TIMESTAMP() WHERE id=?')->execute([$u['session_id']]);
    return $u;
}
function require_role(string $role): array {
    $u=require_user();
    if ($u['role']!==$role) json_response(['error'=>"{$role} account required"],403);
    return $u;
}
function require_student_access(string $studentId): array {
    $u=require_user();
    $s=db()->prepare('SELECT id,full_name,grade,medium,board,parent_id,student_user_id,created_at FROM students WHERE id=? LIMIT 1');
    $s->execute([$studentId]);
    $st=$s->fetch();
    if (!$st) json_response(['error'=>'Student not found'],404);
    $ok=($u['role']==='parent' && hash_equals($st['parent_id'],$u['id'])) ||
        ($u['role']==='student' && !empty($st['student_user_id']) && hash_equals($st['student_user_id'],$u['id']));
    if (!$ok) json_response(['error'=>'Student is not available to this account'],403);
    return $st;
}
function register_parent(array $b): array {
    $email=strtolower(trim((string)($b['email']??''))); $password=(string)($b['password']??''); $name=trim((string)($b['full_name']??''));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) json_response(['error'=>'Valid email required'],422);
    if (strlen($password)<8) json_response(['error'=>'Password must be at least 8 characters'],422);
    $id=uuid_v4();
    try {
        $s=db()->prepare("INSERT INTO users(id,email,password_hash,role,full_name) VALUES(?,?,?,'parent',?)");
        $s->execute([$id,$email,password_hash($password,PASSWORD_DEFAULT),$name?:null]);
    } catch (PDOException $e) {
        if ((string)$e->getCode()==='23000') json_response(['error'=>'Email is already registered'],409);
        throw $e;
    }
    $session=create_session($id);
    return ['user'=>['id'=>$id,'email'=>$email,'role'=>'parent','full_name'=>$name?:null]]+$session;
}
function login_user(array $b): array {
    $email=strtolower(trim((string)($b['email']??''))); $password=(string)($b['password']??'');
    $s=db()->prepare('SELECT id,email,password_hash,role,full_name FROM users WHERE email=? LIMIT 1'); $s->execute([$email]); $u=$s->fetch();
    if (!$u || !password_verify($password,$u['password_hash'])) json_response(['error'=>'Invalid email or password'],401);
    if (password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)) db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$u['id']]);
    return ['user'=>public_user($u)]+create_session($u['id']);
}
function logout_user(): void {
    $raw=bearer_token();
    if ($raw) db()->prepare('DELETE FROM auth_sessions WHERE token_hash=?')->execute([hash('sha256',$raw)]);
}
