<?php
// hash.php — сгенерировать bcrypt-хэш для пароля
echo password_hash('user123', PASSWORD_DEFAULT);
