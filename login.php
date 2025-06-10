<?php
// login.php — форма входа
require 'config.php';
require 'utils.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Логин и пароль обязательны.';
    } else {
        // Ищем пользователя
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            // Успешная авторизация
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            // Перенаправление в зависимости от роли
            if ($user['role'] === 'admin') {
                header('Location: admin_users.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Вход в систему</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 40px; }
    form { max-width: 300px; margin: auto; }
    label { display: block; margin-top: 10px; }
    input[type=text], input[type=password] {
      width: 100%; padding: 8px; margin-top: 5px;
      box-sizing: border-box;
    }
    button {
      margin-top: 15px; padding: 8px 16px;
      background-color: #1277D6; color: #fff; border: none; cursor: pointer;
    }
    .error { color: red; margin-top: 10px; }
  </style>
</head>
<body>
  <h2>Авторизация</h2>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="login.php">
    <label>Логин:
      <input type="text" name="username" required>
    </label>
    <label>Пароль:
      <input type="password" name="password" required>
    </label>
    <button type="submit">Войти</button>
  </form>
</body>
</html>
