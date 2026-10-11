<?php
declare(strict_types=1);
// CLI only: report readiness without credentials or family records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/private_state.php';
$out=['private_storage'=>private_storage_ready(),'php'=>PHP_VERSION,'pdo_mysql'=>extension_loaded('pdo_mysql'),'curl'=>extension_loaded('curl'),'database'=>false,'schema'=>false,'missing_tables'=>[]];
try {
    $pdo=db();$pdo->query('SELECT 1');$out['database']=true;
    $q=$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
    $out['existing_tables']=(int)$q->fetchColumn();
    foreach (['users','students','auth_sessions','student_invites','student_progress','tutor_sessions','tutor_messages','homeschool_records','lesson_progress','lesson_step_records','lesson_test_attempts'] as $table) {
        try {$pdo->query('SELECT * FROM `'.$table.'` LIMIT 0');}
        catch(Throwable $e) {$out['missing_tables'][]=$table;}
    }
    $out['schema']=!$out['missing_tables'];
} catch(Throwable $e) {
    $out['database_error_code']=(string)$e->getCode();
    $message=strtolower($e->getMessage());
    $out['database_error_kind']=str_contains($message,'getaddrinfo')||str_contains($message,'getaddresses')?'hostname_resolution':(str_contains($message,'no such file')?'socket_missing':(str_contains($message,'refused')?'connection_refused':(str_contains($message,'timed out')?'timeout':'other')));
    $out['configured_host_is_localhost']=env_value('DB_HOST')==='localhost';
    // Same server/account, read-only diagnostic using the configured database credentials.
    try {
        $local=new PDO('mysql:host=127.0.0.1;port='.env_value('DB_PORT','3306').';dbname='.env_value('DB_NAME').';charset=utf8mb4',env_value('DB_USER'),env_value('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
        $local->query('SELECT 1');$out['local_tcp_database']=true;
        $out['local_tcp_existing_tables']=(int)$local->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn();
    } catch(Throwable $localError) {$out['local_tcp_database']=false;$out['local_tcp_error_code']=(string)$localError->getCode();}
}
echo json_encode($out),PHP_EOL;
