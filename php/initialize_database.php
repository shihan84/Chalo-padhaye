<?php
declare(strict_types=1);
// Initial installation only. Existing databases require reviewed migrations.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/bootstrap.php';
$pdo=null;$locked=false;
try {
    if ($argc!==2 || !is_file($argv[1])) throw new RuntimeException('Schema file required');
    $pdo=db();
    $lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute(['chalo-bootstrap-'.substr(hash('sha256',(string)env_value('DB_NAME')),0,40)]);
    $locked=(int)$lock->fetchColumn()===1;
    if (!$locked) throw new RuntimeException('Database bootstrap lock unavailable');
    $count=(int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn();
    if ($count!==0) throw new LogicException('Database is not empty; automatic initialization refused');
    $sql=(string)file_get_contents($argv[1]);
    // This repository schema contains simple CREATE/SET statements, no procedures.
    foreach (explode(';',$sql) as $statement) {
        if (trim($statement)!=='') $pdo->exec($statement);
    }
    echo "Empty application database initialized successfully",PHP_EOL;
} catch(LogicException $e) {
    fwrite(STDERR,"Database is not empty; automatic initialization refused\n");$failed=true;
} catch(Throwable $e) {
    fwrite(STDERR,"Database initialization failed; inspect schema state before retrying\n");$failed=true;
} finally {
    if ($pdo && $locked) {
        $unlock=$pdo->prepare('SELECT RELEASE_LOCK(?)');$unlock->execute(['chalo-bootstrap-'.substr(hash('sha256',(string)env_value('DB_NAME')),0,40)]);
    }
}
if (!empty($failed)) exit(1);
