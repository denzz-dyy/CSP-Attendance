<?php
// Admin-only. Log in as admin first, then open /attendance/import_acss.php to (re)import the ACSS workbook data.
require 'config.php';session_start();
if(($_SESSION['u']['role']??'')!='admin')exit('Log in as admin first (index.php), then reopen this page.');
$db=new PDO(DSN,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require 'import_lib.php';echo '<pre>'.htmlspecialchars(import_acss($db,(int)$_SESSION['u']['id'])).'</pre><a href="index.php">Back</a>';
