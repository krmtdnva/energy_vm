<?php
// logout.php — выход из системы
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
