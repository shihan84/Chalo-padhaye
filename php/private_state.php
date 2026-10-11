<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

final class RequestFailure extends RuntimeException {
    public function __construct(public readonly int $status, string $message) { parent::__construct($message); }
}
// Files live outside public_html. flock serializes updates across PHP workers.
// No browser-supplied path is used, and deploys never replace this directory.
function private_state(string $namespace, string $key, callable $update, bool $existingOnly=false): mixed {
    $root=dirname(__DIR__).'/runtime/'.$namespace;
    if (!preg_match('/^[a-z_]+$/',$namespace)) throw new RuntimeException('Invalid storage namespace');
    if (!is_dir($root) && !@mkdir($root,0700,true) && !is_dir($root)) throw new RequestFailure(503,'Learning storage is temporarily unavailable');
    $path=$root.'/'.hash('sha256',$key).'.json';
    if ($existingOnly && !is_file($path)) throw new RequestFailure(409,'This reading check expired. Start a new check');
    if (!is_file($path) && in_array($namespace,['limits','book_sessions'],true) && count(glob($root.'/*.json.lock')?:[])>=10000) throw new RequestFailure(503,'Please try again later');
    if (is_link($path) || is_link($path.'.lock')) throw new RequestFailure(503,'Learning storage is temporarily unavailable');
    $file=@fopen($path.'.lock','c+');
    if (!$file) throw new RequestFailure(503,'Learning storage is temporarily unavailable');
    @chmod($path.'.lock',0600);
    try {
        if (!flock($file,LOCK_EX)) throw new RequestFailure(503,'Learning storage is temporarily unavailable');
        $raw=is_file($path)?file_get_contents($path):'';$state=$raw!==''?json_decode($raw,true,512,JSON_THROW_ON_ERROR):[];
        if (!is_array($state)) throw new RuntimeException('Invalid stored state');
        $result=$update($state);
        $encoded=json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $temporary=tempnam($root,'.state-');
        if ($temporary===false) throw new RequestFailure(503,'Learning storage is temporarily unavailable');
        try {
            chmod($temporary,0600);
            if (file_put_contents($temporary,$encoded)!==strlen($encoded) || !rename($temporary,$path)) throw new RequestFailure(503,'Learning storage is temporarily unavailable');
        } finally { if (is_file($temporary)) unlink($temporary); }
        return $result;
    } finally { flock($file,LOCK_UN);fclose($file); }
}
function consume_limit(string $scope,string $key,int $limit,int $seconds,int $cost=1): void {
    private_state('limits',$scope.':'.$key,function(array &$s) use($limit,$seconds,$cost): void {
        $now=time();if (($s['reset']??0)<=$now) $s=['used'=>0,'reset'=>$now+$seconds];
        if (($s['used']??0)+$cost>$limit) throw new RequestFailure(429,'Please wait before trying again');
        $s['used']=($s['used']??0)+$cost;
    });
}
function client_ip(): string { return (string)($_SERVER['REMOTE_ADDR']??'unknown'); }

function private_storage_ready(): bool {
    try { private_state('readiness','storage',function(array &$s):void{$s=['checked'=>true];}); return true; }
    catch(Throwable $e) { return false; }
}
