<?php
// Disposable CI only; never included in the deployment archive.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/api/')) { require dirname(__DIR__).'/php/api/index.php'; }
return false;
