<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Обработка команды регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $command = trim($_POST['command']);
    
    if (strpos($command, '/register') === 0) {
        $parts = explode(' ', $command);
        
        if (count($parts) < 4) {
            $error = "Ошибка: Используйте формат /register ник пароль почта";
        } else {
            $username = $parts[1];
            $password = $parts[2];
            $email = $parts[3];
            
            // Валидация
            if (strlen($password) < 6) {
                $error = "Ошибка: Пароль должен быть не менее 6 символов";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Ошибка: Неверный формат email";
            } else {
                // Проверка существования пользователя
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "Ошибка: Пользователь с таким именем или email уже существует";
                } else {
                    // Создание пользователя
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $verification_code = md5(uniqid(rand(), true));
                    
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, verification_code) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $email, $verification_code]);
                    
                    // Отправка email (симуляция)
                    $verification_link = SITE_URL . "/verify.php?code=" . $verification_code;
                    
                    $success = "Регистрация успешна! Проверьте вашу почту для подтверждения регистрации. ";
                    $success .= "Ссылка для тестирования: <a href='verify.php?code=$verification_code'>Подтвердить email</a>";
                }
            }
        }
    } else {
        $error = "Ошибка: Неизвестная команда. Используйте /register ник пароль почта";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | HackCraft</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="terminal-theme">
    <div class="container">
        <div class="terminal-window">
            <div class="terminal-header">
                <div class="terminal-buttons">
                    <span class="close"></span>
                    <span class="minimize"></span>
                    <span class="maximize"></span>
                </div>
                <span class="terminal-title">HackCraft Terminal - Регистрация</span>
            </div>
            <div class="terminal-body">
                <div class="welcome-message">
                    <pre class="ascii-art">
   _    _            _     _____             _   
  | |  | |          | |   / ____|           | |  
  | |__| | __ _  ___| | _| |     __ _ _ __ | |_ 
  |  __  |/ _` |/ __| |/ / |    / _` | '_ \| __|
  | |  | | (_| | (__|   <| |___| (_| | | | | |_ 
  |_|  |_|\__,_|\___|_|\_\\_____\__,_|_| |_|\__|
                    </pre>
                    <p class="typewriter">Добро пожаловать в HackCraft! Система обучения хакерству в мире Minecraft.</p>
                    <p>Для регистрации введите команду:</p>
                    <p class="command-example">/register [никнейм] [пароль] [email]</p>
                    <p>Пример: <span class="text-muted">/register HackMaster password123 hackmaster@email.com</span></p>
                    <p>Пароль должен содержать минимум 6 символов</p>
                </div>
                
                <div class="terminal-output">
                    <?php if ($error): ?>
                        <div class="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="success"><?php echo $success; ?></div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" class="terminal-input-form">
                    <div class="input-line">
                        <span class="prompt">user@hackcraft:~$</span>
                        <input type="text" name="command" class="terminal-input" autocomplete="off" autofocus 
                               placeholder="Введите команду /register...">
                        <button type="submit" class="enter-btn"><i class="fas fa-arrow-right"></i></button>
                    </div>
                </form>
                
                <div class="terminal-help">
                    <p>Нужна помощь? Введите <span class="command">help</span> после регистрации</p>
                </div>
            </div>
        </div>
        
        <div class="footer-info">
            <p>HackCraft © 2026 | Образовательный проект по кибербезопасности и Minecraft</p>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>