<?php
// mail/send_verification.php
require_once '../config.php';

// Включение отладки ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
    exit();
}

// Получение и валидация данных
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['email']) || !isset($data['username'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неверные данные']);
    exit();
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$username = htmlspecialchars($data['username'], ENT_QUOTES, 'UTF-8');

// Проверка формата email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Неверный формат email']);
    exit();
}

// Проверяем, существует ли уже пользователь с таким email
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Пользователь с таким email уже существует']);
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных']);
    exit();
}

// Генерируем уникальный код верификации
$verification_code = bin2hex(random_bytes(32));

// Сохраняем код верификации в базе данных
try {
    // Сначала удаляем старые коды для этого email
    $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE email = ? AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $stmt->execute([$email]);
    
    // Сохраняем новый код
    $stmt = $pdo->prepare("INSERT INTO email_verifications (email, verification_code) VALUES (?, ?)");
    $stmt->execute([$email, $verification_code]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка сохранения кода верификации']);
    exit();
}

// Создаем ссылку для подтверждения
$verification_link = SITE_URL . "/verify_email.php?code=" . $verification_code;

// Подготовка HTML письма
$subject = "Подтверждение регистрации на HackCraft";
$html_message = '
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение регистрации</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Courier New", monospace;
            line-height: 1.6;
            color: #f0f0f0;
            background-color: #0a0a0a;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #0c0c0c;
            border: 2px solid #00ff00;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .email-header {
            background: linear-gradient(to right, #222, #333);
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid #00ff00;
        }
        
        .email-header h1 {
            color: #00ff00;
            font-size: 28px;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
        }
        
        .email-header p {
            color: #00ffff;
            font-size: 16px;
        }
        
        .email-body {
            padding: 30px;
        }
        
        .greeting {
            font-size: 20px;
            margin-bottom: 20px;
            color: #00ffff;
        }
        
        .username {
            color: #00ff00;
            font-weight: bold;
        }
        
        .instructions {
            background-color: rgba(0, 20, 0, 0.3);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #00ff00;
        }
        
        .instructions h3 {
            color: #00ff00;
            margin-bottom: 10px;
        }
        
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        
        .verify-button {
            display: inline-block;
            background-color: #00ff00;
            color: #000;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 18px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .verify-button:hover {
            background-color: #00cc00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.3);
        }
        
        .verification-code {
            background-color: #000;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border: 1px solid #333;
            font-family: "Courier New", monospace;
            color: #00ff00;
            word-break: break-all;
        }
        
        .warning {
            background-color: rgba(255, 0, 0, 0.1);
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #ff5555;
            color: #ff5555;
        }
        
        .email-footer {
            background-color: #222;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #333;
            font-size: 12px;
            color: #888;
        }
        
        .footer-links {
            margin-top: 10px;
        }
        
        .footer-links a {
            color: #00ffff;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .ascii-art {
            font-family: "Courier New", monospace;
            font-size: 10px;
            color: #00ff00;
            text-align: center;
            margin: 20px 0;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>HackCraft</h1>
            <p>Система обучения хакингу Minecraft</p>
        </div>
        
        <div class="email-body">
            <div class="ascii-art">
 _____ _   _    _    ____  _   _ ____  
|_   _| | | |  / \  |  _ \| | | |  _ \ 
  | | | |_| | / _ \ | |_) | | | | |_) |
  | | |  _  |/ ___ \|  _ <| |_| |  __/ 
  |_| |_| |_/_/   \_\_| \_\\___/|_|    
            </div>
            
            <h2 class="greeting">Добро пожаловать, <span class="username">' . $username . '</span>!</h2>
            
            <div class="instructions">
                <h3>🛡️ Подтверждение регистрации</h3>
                <p>Для активации вашего аккаунта на HackCraft и доступа ко всем функциям необходимо подтвердить ваш email адрес.</p>
                <p>Нажмите кнопку ниже для завершения регистрации:</p>
            </div>
            
            <div class="button-container">
                <a href="' . $verification_link . '" class="verify-button">✅ Подтвердить Email</a>
            </div>
            
            <p>Или скопируйте и вставьте следующую ссылку в адресную строку браузера:</p>
            <div class="verification-code">
                ' . $verification_link . '
            </div>
            
            <div class="warning">
                <p><strong>⚠️ Внимание!</strong></p>
                <p>Если вы не регистрировались на HackCraft, пожалуйста, проигнорируйте это письмо.</p>
                <p>Ссылка действительна в течение 24 часов.</p>
            </div>
            
            <div class="instructions">
                <h3>🎮 Что дальше?</h3>
                <p>После подтверждения email вы получите доступ к:</p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Системе заданий по хакингу Minecraft</li>
                    <li>Терминалу с кастомными командами</li>
                    <li>Базам знаний и статьям</li>
                    <li>Системе достижений и уровней</li>
                </ul>
            </div>
        </div>
        
        <div class="email-footer">
            <p>© 2026 HackCraft. Все права защищены.</p>
            <p>Это автоматическое письмо, пожалуйста, не отвечайте на него.</p>
            <div class="footer-links">
                <a href="' . SITE_URL . '">Главная страница</a> | 
                <a href="' . SITE_URL . '/support">Поддержка</a> | 
                <a href="' . SITE_URL . '/privacy">Конфиденциальность</a>
            </div>
        </div>
    </div>
</body>
</html>';

// Для продакшена используем эту функцию отправки:
function sendEmail($to, $subject, $html_message) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: no-reply@hackcraft.ru\r\n";
    $headers .= "Reply-To: support@hackcraft.ru\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $headers .= "X-Priority: 1 (Highest)\r\n";
    $headers .= "X-MSMail-Priority: High\r\n";
    
    // В реальном проекте раскомментировать:
    // return mail($to, $subject, $html_message, $headers);
    
    // Для демо - сохраняем в файл
    return saveEmailForDemo($to, $subject, $html_message);
}

// Функция для сохранения письма в файл (для демо)
function saveEmailForDemo($to, $subject, $html_message) {
    $mail_dir = __DIR__ . '/sent_emails/';
    if (!file_exists($mail_dir)) {
        mkdir($mail_dir, 0777, true);
    }
    
    $filename = $mail_dir . 'email_' . time() . '_' . md5($to) . '.html';
    $file_content = "To: $to\nSubject: $subject\n\n$html_message";
    
    if (file_put_contents($filename, $file_content)) {
        // Создаем удобную ссылку для просмотра
        $view_link = SITE_URL . '/mail/sent_emails/' . basename($filename);
        return ['saved' => true, 'file' => $filename, 'view_link' => $view_link];
    }
    
    return false;
}

// Отправляем письмо
$email_result = sendEmail($email, $subject, $html_message);

if ($email_result) {
    // Генерируем удобную ссылку для тестирования
    $test_link = isset($email_result['view_link']) ? $email_result['view_link'] : $verification_link;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Письмо с подтверждением отправлено на ' . $email,
        'verification_link' => $verification_link,
        'test_link' => $test_link,
        'debug_info' => [
            'email' => $email,
            'username' => $username,
            'code' => $verification_code
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка отправки письма']);
}
?>
