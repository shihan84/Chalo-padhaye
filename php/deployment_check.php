<?php
declare(strict_types=1);
// CLI only: report readiness without credentials or family records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/bootstrap.php';
$out=['php'=>PHP_VERSION,'pdo_mysql'=>extension_loaded('pdo_mysql'),'curl'=>extension_loaded('curl'),'database'=>false,'schema'=>false,'missing_tables'=>[]];
try {
    $pdo=db();$pdo->query('SELECT 1');$out['database']=true;
    $q=$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
    $out['existing_tables']=(int)$q->fetchColumn();
    foreach (['users','students','auth_sessions','student_invites','student_progress','tutor_sessions','tutor_messages','homeschool_records','lesson_progress','lesson_step_records','lesson_test_attempts'] as $table) {
        try {$pdo->query('SELECT * FROM `'.$table.'` LIMIT 0');}
        catch(Throwable $e) {$out['missing_tables'][]=$table;}
    }
    $out['schema']=!$out['missing_tables'];
} catch(Throwable $e) {$out['database_error_code']=(string)$e->getCode();}
echo json_encode($out),PHP_EOL;
