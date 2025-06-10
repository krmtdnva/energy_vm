<?php
// admin_users.php — единый файл для CRUD-панели управления пользователями

// Подключаем конфигурацию и утилиты
require 'config.php';
require 'utils.php';

// Проверяем, что пользователь авторизован и админ
check_auth();
if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

// Определяем текущее действие (action) и ID при необходимости
$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Переменные для хранения сообщений об ошибках/успехе
$error   = '';
$success = '';


// ======================
// 1) Удаление пользователя
// ======================
if ($action === 'delete' && $id > 0) {
    // Защита: нельзя удалить самого себя
    if ($id === (int)$_SESSION['user_id']) {
        $error = 'Нельзя удалить свою учётную запись.';
    } else {
        // Проверим, сколько админов осталось
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $adminCount = (int)$stmtCount->fetchColumn();

        // Если удаляемый пользователь админ, нельзя удалять последнего админа
        $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtRole->execute([$id]);
        $row = $stmtRole->fetch();
        if ($row && $row['role'] === 'admin' && $adminCount < 2) {
            $error = 'Нельзя удалить последнюю учётную запись администратора.';
        } else {
            // Удаляем
            $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDel->execute([$id]);
            $success = 'Пользователь успешно удалён.';
        }
    }
    // После удаления/ошибки будем показывать список пользователей
    $action = 'list';
}


// ======================
// 2) Создание нового пользователя
// ======================
if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'user';

        // Валидация
        if ($username === '' || $password === '') {
            $error = 'Логин и пароль обязательны.';
        } elseif (!in_array($role, ['user', 'admin'], true)) {
            $error = 'Недопустимая роль.';
        } else {
            // Проверим, нет ли уже такого логина
            $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmtChk->execute([$username]);
            $count = $stmtChk->fetchColumn();
            if ($count > 0) {
                $error = 'Пользователь с таким логином уже существует.';
            } else {
                // Создаем
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $stmtIns = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmtIns->execute([$username, $passHash, $role]);
                $success = 'Новый пользователь успешно создан.';
                // После успешного создания возвращаемся к списку
                header('Location: admin_users.php?success=' . urlencode($success));
                exit;
            }
        }
    }
    // Показываем форму создания (см. ниже HTML)
}


// ======================
// 3) Редактирование существующего пользователя
// ======================
if ($action === 'edit' && $id > 0) {
    // Сначала получим данные, чтобы заполнить форму
    $stmtUsr = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmtUsr->execute([$id]);
    $userData = $stmtUsr->fetch();
    if (!$userData) {
        // Если пользователь не найден — переходим к списку
        header('Location: admin_users.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newUsername = trim($_POST['username'] ?? '');
        $newRole     = $_POST['role'] ?? 'user';
        $newPassword = $_POST['password'] ?? ''; // если пустая строка — пароль не меняем

        // Валидация
        if ($newUsername === '') {
            $error = 'Поле «Логин» не может быть пустым.';
        } elseif (!in_array($newRole, ['user', 'admin'], true)) {
            $error = 'Недопустимая роль.';
        } else {
            // Проверим, не занят ли новый логин кем-то другим
            $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?");
            $stmtChk->execute([$newUsername, $id]);
            $count = $stmtChk->fetchColumn();
            if ($count > 0) {
                $error = 'Пользователь с таким логином уже существует.';
            } else {
                // Обновляем запись
                if ($newPassword !== '') {
                    // Генерируем хэш
                    $passHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmtUpd = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, role = ?, password = ? 
                        WHERE id = ?
                    ");
                    $stmtUpd->execute([$newUsername, $newRole, $passHash, $id]);
                } else {
                    // Пароль не меняем
                    $stmtUpd = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, role = ? 
                        WHERE id = ?
                    ");
                    $stmtUpd->execute([$newUsername, $newRole, $id]);
                }

                // Если изменили свою собственную учётку – обновим сессию
                if ($id === (int)$_SESSION['user_id']) {
                    $_SESSION['username'] = $newUsername;
                    $_SESSION['role'] = $newRole;
                }

                $success = 'Данные пользователя успешно обновлены.';
                // Рефрешим данные для формы
                $userData['username'] = $newUsername;
                $userData['role']     = $newRole;
            }
        }
    }
    // Показываем форму редактирования (см. ниже HTML)
}


// ======================
// 4) Вывод списка пользователей (action=list) или после операций
// ======================
if ($action === 'list') {
    // Если при редиректе передали success-параметр
    if (isset($_GET['success'])) {
        $success = $_GET['success'];
    }
    // Получаем пользователей
    $stmtAll = $pdo->query("SELECT id, username, role FROM users ORDER BY id ASC");
    $allUsers = $stmtAll->fetchAll();
    // Показываем таблицу (см. ниже HTML)
}


?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админ: управление пользователями</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    nav { margin-bottom: 20px; }
    a { margin-right: 10px; color: #1277D6; text-decoration: none; }
    a:hover { text-decoration: underline; }
    table { border-collapse: collapse; width: 100%; margin-top: 15px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f0f0f0; }
    form { max-width: 400px; }
    label { display: block; margin-top: 10px; }
    input[type=text], input[type=password], select {
      width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;
    }
    button {
      margin-top: 15px; padding: 8px 16px;
      background-color: #1277D6; color: #fff; border: none; cursor: pointer;
    }
    .error { color: red; margin-top: 10px; }
    .success { color: green; margin-top: 10px; }
    .actions a { margin-right: 6px; }
    .btn { 
      display: inline-block; 
      padding: 6px 12px; 
      background-color: #1277D6; 
      color: #fff; 
      text-decoration: none; 
      border-radius: 4px; 
      margin-top: 10px;
    }
    .btn:hover { background-color: #0f5ca8; }
  </style>
</head>
<body>
  <nav>
    <strong>Админ: <?= htmlspecialchars($_SESSION['username']) ?></strong> |
    <a href="dashboard.php">↩ В панель пользователя</a> |
    <a href="logout.php">Выйти</a>
  </nav>

  <?php if ($action === 'list'): ?>
    <h2>Список пользователей</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <a class="btn" href="admin_users.php?action=create">Добавить нового пользователя</a>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Логин</th>
          <th>Роль</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($allUsers as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td class="actions">
            <a href="admin_users.php?action=edit&id=<?= $u['id'] ?>">Изменить</a>
            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
              <a href="admin_users.php?action=delete&id=<?= $u['id'] ?>"
                 style="color: red;"
                 onclick="return confirm('Удалить пользователя <?= htmlspecialchars($u['username']) ?>?');"
              >Удалить</a>
            <?php else: ?>
              <span style="color: gray;">Удалить</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

  <?php elseif ($action === 'create'): ?>
    <h2>Добавить нового пользователя</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="admin_users.php?action=create">
      <label>Логин:
        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
      </label>
      <label>Пароль:
        <input type="password" name="password" required>
      </label>
      <label>Роль:
        <select name="role">
          <option value="user" <?= (($_POST['role'] ?? '') === 'user') ? 'selected' : '' ?>>user</option>
          <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>admin</option>
        </select>
      </label>
      <button type="submit">Создать</button>
    </form>

    <p><a href="admin_users.php?action=list">← Вернуться к списку</a></p>

  <?php elseif ($action === 'edit' && $id > 0 && isset($userData)): ?>
    <h2>Редактировать пользователя (ID = <?= $userData['id'] ?>)</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" action="admin_users.php?action=edit&id=<?= $userData['id'] ?>">
      <label>Логин:
        <input type="text" name="username" value="<?= htmlspecialchars($userData['username']) ?>" required>
      </label>
      <label>Новый пароль (оставьте пустым, если не нужно менять):
        <input type="password" name="password">
      </label>
      <label>Роль:
        <select name="role">
          <option value="user" <?= ($userData['role'] === 'user') ? 'selected' : '' ?>>user</option>
          <option value="admin" <?= ($userData['role'] === 'admin') ? 'selected' : '' ?>>admin</option>
        </select>
      </label>
      <button type="submit">Сохранить</button>
    </form>

    <p><a href="admin_users.php?action=list">← Вернуться к списку</a></p>

  <?php else: ?>
    <!-- Если action не распознан, или нет userData для редактирования, просто покажем список -->
    <?php
      header('Location: admin_users.php?action=list');
      exit;
    ?>
  <?php endif; ?>

</body>
</html>
