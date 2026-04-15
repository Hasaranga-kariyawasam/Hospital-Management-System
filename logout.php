<?php
declare(strict_types=1);

session_start();
$_SESSION = [];
session_unset();
session_destroy();

header('Location: /hospital-system/login.php');
exit();