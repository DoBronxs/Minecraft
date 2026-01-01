<?php
require_once 'config.php';

// Проверка прав администратора
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Проверка, является ли пользователь администратором
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['is_admin']) {
    // Проверка блокировки IP
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("SELECT attempts, blocked_until FROM blocked_ips WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $block_info = $stmt->fetch();
    
    // Обработка попытки входа
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        // Проверка учетных данных администратора
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND password = ? AND is_admin = 1");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            // Увеличиваем счетчик попыток
            if ($block_info) {
                $new_attempts = $block_info['attempts'] + 1;
                $stmt = $pdo->prepare("UPDATE blocked_ips SET attempts = ?, blocked_until = ? WHERE ip_address = ?");
                
                if ($new_attempts >= 3) {
                    // Блокировка на 1 час
                    $block_until = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $stmt->execute([$new_attempts, $block_until, $ip]);
                    $error = "Слишком много попыток! Доступ заблокирован на 1 час. Для разблокировки задонатьте 100 рублей.";
                } else {
                    $stmt->execute([$new_attempts, null, $ip]);
                    $error = "Неверные данные! Осталось попыток: " . (3 - $new_attempts);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO blocked_ips (ip_address, attempts) VALUES (?, 1)");
                $stmt->execute([$ip]);
                $error = "Неверные данные! Осталось попыток: 2";
            }
        } else {
            // Успешный вход - сбрасываем блокировку
            if ($block_info) {
                $pdo->prepare("DELETE FROM blocked_ips WHERE ip_address = ?")->execute([$ip]);
            }
            $_SESSION['admin_id'] = $admin['id'];
            header("Location: admin.php");
            exit();
        }
    }
    
    // Проверка блокировки
    if ($block_info && $block_info['blocked_until']) {
        if (strtotime($block_info['blocked_until']) > time()) {
            $error = "Ваш IP заблокирован до " . $block_info['blocked_until'] . ". Для разблокировки задонатьте 100 рублей.";
        } else {
            // Разблокировка по истечении времени
            $pdo->prepare("DELETE FROM blocked_ips WHERE ip_address = ?")->execute([$ip]);
        }
    }
    
    // Показываем форму входа для администратора
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Админ-панель | HackCraft</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="terminal-theme">
        <div class="container">
            <div class="terminal-window admin-login">
                <div class="terminal-header">
                    <div class="terminal-buttons">
                        <span class="close"></span>
                        <span class="minimize"></span>
                        <span class="maximize"></span>
                    </div>
                    <span class="terminal-title">HackCraft Admin Panel - Authentication</span>
                </div>
                <div class="terminal-body">
                    <div class="admin-login-form">
                        <div class="login-header">
                            <i class="fas fa-shield-alt"></i>
                            <h2>Доступ к админ-панели</h2>
                            <p>Только для администраторов системы</p>
                        </div>
                        
                        <?php if (isset($error)): ?>
                            <div class="admin-alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="username"><i class="fas fa-user"></i> Логин администратора</label>
                                <input type="text" id="username" name="username" required 
                                       class="terminal-input" autocomplete="off">
                            </div>
                            
                            <div class="form-group">
                                <label for="password"><i class="fas fa-key"></i> Пароль</label>
                                <input type="password" id="password" name="password" required 
                                       class="terminal-input">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="admin_login" class="btn-admin-login">
                                    <i class="fas fa-sign-in-alt"></i> Войти в админ-панель
                                </button>
                                <a href="donate.php?unlock_admin=1" class="btn-donate-unlock">
                                    <i class="fas fa-unlock"></i> Разблокировать доступ (100 руб.)
                                </a>
                            </div>
                        </form>
                        
                        <div class="login-footer">
                            <p><i class="fas fa-info-circle"></i> После 3 неудачных попыток доступ с вашего IP будет заблокирован</p>
                            <p><i class="fas fa-user-secret"></i> Если второй администратор зайдет в систему, вы получите уведомление</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .admin-login {
            max-width: 500px;
            margin: 50px auto;
        }
        
        .admin-login-form {
            padding: 30px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header i {
            font-size: 48px;
            color: var(--terminal-red);
            margin-bottom: 15px;
        }
        
        .login-header h2 {
            color: var(--terminal-red);
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: var(--terminal-cyan);
            font-weight: bold;
        }
        
        .form-group .terminal-input {
            width: 100%;
            padding: 12px;
            background-color: #000;
            border: 1px solid var(--terminal-border);
        }
        
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 30px 0;
        }
        
        .btn-admin-login {
            background-color: var(--terminal-red);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-admin-login:hover {
            background-color: #cc0000;
            transform: translateY(-2px);
        }
        
        .btn-donate-unlock {
            background-color: var(--terminal-purple);
            color: white;
            padding: 15px;
            border-radius: 4px;
            text-decoration: none;
            text-align: center;
            font-weight: bold;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-donate-unlock:hover {
            background-color: #cc00cc;
            transform: translateY(-2px);
        }
        
        .login-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--terminal-border);
            color: #888;
            font-size: 14px;
        }
        
        .login-footer p {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        </style>
    </body>
    </html>
    <?php
    exit();
}

// Пользователь - администратор, показываем админ-панель

// Проверка активности других администраторов
$stmt = $pdo->query("SELECT username, last_login FROM users WHERE is_admin = 1 AND id != ? ORDER BY last_login DESC");
$other_admins = $stmt->fetchAll();

// Уведомление о входе другого администратора
if (isset($_GET['admin_alert'])) {
    $admin_alert = "Второй администратор вошел в систему в " . date('H:i:s');
}

// Обработка действий админ-панели
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_article'])) {
        // Добавление новой статьи
        $title = $_POST['title'];
        $description = $_POST['description'];
        $content = $_POST['content'];
        $is_encrypted = isset($_POST['is_encrypted']) ? 1 : 0;
        $required_level = $_POST['required_level'];
        $category = $_POST['category'];
        
        if ($is_encrypted) {
            $content = encrypt_text($content);
        }
        
        $stmt = $pdo->prepare("INSERT INTO articles (title, description, content, is_encrypted, required_level, category, author_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $content, $is_encrypted, $required_level, $category, $_SESSION['user_id']]);
        
        $success = "Статья успешно добавлена!";
        
    } elseif (isset($_POST['edit_user'])) {
        // Редактирование пользователя
        $user_id = $_POST['user_id'];
        $level = $_POST['level'];
        $vip_level = $_POST['vip_level'];
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE users SET level = ?, vip_level = ?, is_admin = ? WHERE id = ?");
        $stmt->execute([$level, $vip_level, $is_admin, $user_id]);
        
        $success = "Пользователь обновлен!";
        
    } elseif (isset($_POST['add_task'])) {
        // Добавление задания
        $title = $_POST['task_title'];
        $description = $_POST['task_description'];
        $command = $_POST['command'];
        $level_required = $_POST['level_required'];
        $experience_reward = $_POST['experience_reward'];
        $category = $_POST['task_category'];
        
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, command, level_required, experience_reward, category) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $command, $level_required, $experience_reward, $category]);
        
        $success = "Задание успешно добавлено!";
    }
}

// Получение статистики
$total_users = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$total_articles = $pdo->query("SELECT COUNT(*) as count FROM articles")->fetch()['count'];
$total_tasks = $pdo->query("SELECT COUNT(*) as count FROM tasks")->fetch()['count'];
$total_completed_tasks = $pdo->query("SELECT COUNT(*) as count FROM user_tasks")->fetch()['count'];

// Получение последних пользователей
$recent_users = $pdo->query("SELECT username, email, level, registration_date FROM users ORDER BY registration_date DESC LIMIT 5")->fetchAll();

// Получение всех пользователей для управления
$all_users = $pdo->query("SELECT id, username, email, level, vip_level, is_admin, registration_date FROM users ORDER BY id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | HackCraft</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="terminal-theme">
    <!-- Навигационная панель -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-logo">
                <i class="fas fa-terminal"></i> HackCraft
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Дашборд</a></li>
                <li><a href="admin.php" class="nav-link active"><i class="fas fa-shield-alt"></i> Админ-панель</a></li>
                <li><a href="admin.php?section=users" class="nav-link"><i class="fas fa-users"></i> Пользователи</a></li>
                <li><a href="admin.php?section=articles" class="nav-link"><i class="fas fa-newspaper"></i> Статьи</a></li>
                <li><a href="admin.php?section=tasks" class="nav-link"><i class="fas fa-tasks"></i> Задания</a></li>
                <li><a href="admin.php?section=stats" class="nav-link"><i class="fas fa-chart-bar"></i> Статистика</a></li>
            </ul>
            <div class="user-info">
                <span class="admin-badge"><i class="fas fa-crown"></i> Админ</span>
                <a href="logout.php" class="logout-btn" title="Выйти"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Приветствие админа -->
        <div class="admin-welcome">
            <h1><i class="fas fa-shield-alt"></i> Админ-панель HackCraft</h1>
            <p>Управление системой и модерация контента</p>
            
            <?php if (isset($admin_alert)): ?>
                <div class="admin-alert">
                    <i class="fas fa-bell"></i>
                    <span><?php echo $admin_alert; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="admin-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Быстрая статистика -->
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $total_users; ?></span>
                    <span class="stat-label">Пользователей</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $total_articles; ?></span>
                    <span class="stat-label">Статей</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $total_tasks; ?></span>
                    <span class="stat-label">Заданий</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $total_completed_tasks; ?></span>
                    <span class="stat-label">Выполнений</span>
                </div>
            </div>
        </div>

        <!-- Основное содержимое -->
        <div class="admin-content">
            <?php 
            $section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
            
            switch ($section) {
                case 'users':
                    include 'admin_users.php';
                    break;
                case 'articles':
                    include 'admin_articles.php';
                    break;
                case 'tasks':
                    include 'admin_tasks.php';
                    break;
                case 'stats':
                    include 'admin_stats.php';
                    break;
                default:
                    include 'admin_dashboard.php';
                    break;
            }
            ?>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // Обновление статистики в реальном времени
    function updateAdminStats() {
        fetch('api/admin_stats.php')
            .then(response => response.json())
            .then(data => {
                document.querySelectorAll('.stat-number').forEach((el, index) => {
                    if (index === 0) el.textContent = data.total_users;
                    if (index === 1) el.textContent = data.total_articles;
                    if (index === 2) el.textContent = data.total_tasks;
                    if (index === 3) el.textContent = data.total_completed;
                });
            });
    }
    
    // Обновлять каждые 30 секунд
    setInterval(updateAdminStats, 30000);
    
    // Уведомление о входе другого администратора
    function checkOtherAdmins() {
        fetch('api/check_admins.php')
            .then(response => response.json())
            .then(data => {
                if (data.other_admin_online) {
                    showAdminAlert('Второй администратор онлайн: ' + data.admin_name);
                }
            });
    }
    
    function showAdminAlert(message) {
        const alert = document.createElement('div');
        alert.className = 'admin-alert floating';
        alert.innerHTML = `
            <i class="fas fa-bell"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">&times;</button>
        `;
        document.querySelector('.admin-welcome').appendChild(alert);
    }
    
    // Проверять каждую минуту
    setInterval(checkOtherAdmins, 60000);
    </script>
    
    <style>
    /* Стили для админ-панели */
    .admin-welcome {
        text-align: center;
        margin: 30px 0;
        padding: 20px;
        background: linear-gradient(135deg, rgba(255,0,0,0.1), rgba(255,0,255,0.1));
        border-radius: 10px;
        border: 2px solid var(--terminal-red);
        position: relative;
    }
    
    .admin-welcome h1 {
        color: var(--terminal-red);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
    }
    
    .admin-alert {
        background-color: rgba(255, 0, 0, 0.1);
        border: 1px solid var(--terminal-red);
        color: var(--terminal-red);
        padding: 15px;
        border-radius: 4px;
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: pulse 2s infinite;
    }
    
    .admin-alert.floating {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    }
    
    .admin-success {
        background-color: rgba(0, 255, 0, 0.1);
        border: 1px solid var(--terminal-green);
        color: var(--terminal-green);
        padding: 15px;
        border-radius: 4px;
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .admin-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 30px 0;
    }
    
    .stat-card {
        background-color: rgba(20, 20, 20, 0.8);
        border-radius: 8px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        border: 1px solid var(--terminal-border);
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        border-color: var(--terminal-purple);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(45deg, var(--terminal-purple), var(--terminal-red));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-number {
        display: block;
        font-size: 28px;
        font-weight: bold;
        color: var(--terminal-green);
    }
    
    .stat-label {
        display: block;
        color: #888;
        font-size: 14px;
        margin-top: 5px;
    }
    
    .admin-content {
        background-color: rgba(10, 10, 10, 0.9);
        border-radius: 8px;
        padding: 30px;
        margin: 30px 0;
        border: 2px solid var(--terminal-red);
        min-height: 500px;
    }
    
    .admin-badge {
        background: linear-gradient(45deg, var(--terminal-red), var(--terminal-purple));
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(255, 0, 0, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0); }
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @media (max-width: 768px) {
        .admin-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .admin-alert.floating {
            position: relative;
            top: 0;
            right: 0;
            margin: 10px;
        }
    }
    </style>
</body>
</html>