<?php
// dashboard.php — закрытая страница для обычных пользователей
require 'config.php';
require 'utils.php';
check_auth(); // если не залогинен — вернет на login.php

?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Панель пользователя</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    nav { margin-bottom: 20px; }
    a { margin-right: 10px; color: #1277D6; text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <nav>
    <strong>Привет, <?= htmlspecialchars($_SESSION['username']) ?></strong> |
    <a href="logout.php">Выйти</a>
  </nav>

  <h2>Добро пожаловать в пользовательскую панель</h2>
  
  <p><a href="calculator.php">Перейти к калькулятору</a></p>
</body>
</html>
