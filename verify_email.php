<?php
// verify_email.php - обработка подтверждения email
require_once 'config.php';

// Устанавливаем заголовки для предотвращения кэширования
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Проверяем наличие кода
if (!isset($_GET['code'])) {
    header("Location: register.php?error=no_code");
    exit();
}

$verification_code = trim($_GET['code']);

// Проверяем длину кода
if (strlen($verification_code) !== 64) {
    header("Location: register.php?error=invalid_code_format");
    exit();
}

try {
    // Ищем код верификации в базе
    $stmt = $pdo->prepare("
        SELECT ev.email, ev.created_at, u.id as user_id, u.username, u.is_verified 
        FROM email_verifications ev
        LEFT JOIN users u ON ev.email = u.email
        WHERE ev.verification_code = ?
    ");
    $stmt->execute([$verification_code]);
    $result = $stmt->fetch();
    
    if (!$result) {
        // Код не найден
        header("Location: register.php?error=invalid_verification_code");
        exit();
    }
    
    // Проверяем, не истекло ли время действия кода (24 часа)
    $created_time = strtotime($result['created_at']);
    $current_time = time();
    $hours_diff = ($current_time - $created_time) / 3600;
    
    if ($hours_diff > 24) {
        // Удаляем просроченный код
        $pdo->prepare("DELETE FROM email_verifications WHERE verification_code = ?")->execute([$verification_code]);
        header("Location: register.php?error=verification_expired");
        exit();
    }
    
    // Если пользователь уже верифицирован
    if ($result['is_verified']) {
        // Удаляем использованный код
        $pdo->prepare("DELETE FROM email_verifications WHERE verification_code = ?")->execute([$verification_code]);
        
        // Показываем страницу успеха
        showSuccessPage("Аккаунт уже подтвержден", "Ваш аккаунт уже был подтвержден ранее.");
        exit();
    }
    
    // Если пользователь не найден (маловероятно, но на всякий случай)
    if (!$result['user_id']) {
        header("Location: register.php?error=user_not_found");
        exit();
    }
    
    // Верифицируем аккаунт пользователя
    $stmt = $pdo->prepare("UPDATE users SET is_verified = TRUE WHERE id = ?");
    $stmt->execute([$result['user_id']]);
    
    // Удаляем использованный код верификации
    $pdo->prepare("DELETE FROM email_verifications WHERE verification_code = ?")->execute([$verification_code]);
    
    // Добавляем достижение за подтверждение email
    $stmt = $pdo->prepare("INSERT INTO achievements (user_id, achievement_name, description) VALUES (?, ?, ?)");
    $stmt->execute([
        $result['user_id'],
        "Email подтвержден",
        "Вы успешно подтвердили ваш email адрес"
    ]);
    
    // Автоматически входим в систему
    $_SESSION['user_id'] = $result['user_id'];
    $_SESSION['username'] = $result['username'];
    $_SESSION['email_verified'] = true;
    $_SESSION['last_activity'] = time();
    
    // Записываем в логи
    $log_message = "User " . $result['user_id'] . " (" . $result['email'] . ") verified email";
    error_log($log_message);
    
    // Показываем страницу успеха
    showSuccessPage("Email подтвержден!", "Ваш аккаунт успешно активирован.", true);
    
} catch (PDOException $e) {
    error_log("Verification error: " . $e->getMessage());
    header("Location: register.php?error=database_error");
    exit();
}

// Функция для отображения страницы успеха
function showSuccessPage($title, $message, $redirect = false) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | HackCraft</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="terminal-theme">
        <div class="container">
            <div class="verification-container">
                <div class="terminal-window">
                    <div class="terminal-header">
                        <div class="terminal-buttons">
                            <span class="close"></span>
                            <span class="minimize"></span>
                            <span class="maximize"></span>
                        </div>
                        <span class="terminal-title">HackCraft - Подтверждение Email</span>
                    </div>
                    
                    <div class="terminal-body">
                        <div class="verification-result">
                            <div class="result-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            
                            <h1><?php echo htmlspecialchars($title); ?></h1>
                            <p class="result-message"><?php echo htmlspecialchars($message); ?></p>
                            
                            <?php if ($redirect): ?>
                                <div class="achievement-unlocked">
                                    <i class="fas fa-trophy"></i>
                                    <span>Достижение получено: "Подтвержденный хакер"</span>
                                </div>
                                
                                <div class="redirect-info">
                                    <p>Вы будете перенаправлены на главную страницу через <span id="countdown">5</span> секунд</p>
                                </div>
                                
                                <div class="action-buttons">
                                    <a href="dashboard.php" class="btn-primary">
                                        <i class="fas fa-rocket"></i> Перейти в Дашборд
                                    </a>
                                    <a href="tasks.php" class="btn-secondary">
                                        <i class="fas fa-gamepad"></i> Начать обучение
                                    </a>
                                </div>
                                
                                <script>
                                    // Автоматический редирект через 5 секунд
                                    let countdown = 5;
                                    const countdownElement = document.getElementById('countdown');
                                    
                                    const timer = setInterval(() => {
                                        countdown--;
                                        countdownElement.textContent = countdown;
                                        
                                        if (countdown <= 0) {
                                            clearInterval(timer);
                                            window.location.href = 'dashboard.php';
                                        }
                                    }, 1000);
                                </script>
                            <?php else: ?>
                                <div class="action-buttons">
                                    <a href="login.php" class="btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Войти в систему
                                    </a>
                                    <a href="register.php" class="btn-secondary">
                                        <i class="fas fa-user-plus"></i> Регистрация
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="verification-info">
                                <h3><i class="fas fa-info-circle"></i> Что дальше?</h3>
                                <ul>
                                    <li>Изучите терминал и доступные команды</li>
                                    <li>Начните выполнять задания для получения опыта</li>
                                    <li>Читайте статьи в базе знаний</li>
                                    <li>Прокачивайте свой уровень и получайте достижения</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .verification-container {
            max-width: 800px;
            margin: 50px auto;
        }
        
        .verification-result {
            text-align: center;
            padding: 40px 20px;
        }
        
        .result-icon {
            font-size: 80px;
            margin-bottom: 30px;
        }
        
        .result-icon.success {
            color: #00ff00;
            animation: pulse 2s infinite;
        }
        
        .verification-result h1 {
            color: #00ff00;
            margin-bottom: 20px;
            font-size: 32px;
        }
        
        .result-message {
            color: #f0f0f0;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .achievement-unlocked {
            background-color: rgba(255, 215, 0, 0.1);
            border: 1px solid #FFD700;
            color: #FFD700;
            padding: 15px;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .redirect-info {
            background-color: rgba(0, 255, 255, 0.1);
            border: 1px solid #00ffff;
            color: #00ffff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 400px;
        }
        
        .redirect-info span {
            font-weight: bold;
            color: #00ff00;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 40px 0;
        }
        
        .btn-primary, .btn-secondary {
            padding: 15px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: #00ff00;
            color: #000;
        }
        
        .btn-primary:hover {
            background-color: #00cc00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.3);
        }
        
        .btn-secondary {
            background-color: rgba(0, 255, 255, 0.2);
            color: #00ffff;
            border: 1px solid #00ffff;
        }
        
        .btn-secondary:hover {
            background-color: rgba(0, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .verification-info {
            background-color: rgba(0, 0, 0, 0.3);
            padding: 25px;
            border-radius: 8px;
            margin-top: 40px;
            text-align: left;
            border-left: 4px solid #00ff00;
        }
        
        .verification-info h3 {
            color: #00ffff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .verification-info ul {
            list-style: none;
            padding-left: 0;
        }
        
        .verification-info li {
            padding: 8px 0;
            color: #f0f0f0;
            position: relative;
            padding-left: 25px;
        }
        
        .verification-info li:before {
            content: "▶";
            color: #00ff00;
            position: absolute;
            left: 0;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
            
            .verification-container {
                margin: 20px auto;
            }
        }
        </style>
    </body>
    </html>
    <?php
}
?>