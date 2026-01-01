<?php
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Получение информации о пользователе
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Получение достижений пользователя
$stmt = $pdo->prepare("SELECT * FROM achievements WHERE user_id = ? ORDER BY earned_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$achievements = $stmt->fetchAll();

// Получение выполненных заданий
$stmt = $pdo->prepare("SELECT t.* FROM tasks t 
                      JOIN user_tasks ut ON t.id = ut.task_id 
                      WHERE ut.user_id = ? 
                      ORDER BY ut.completed_at DESC LIMIT 3");
$stmt->execute([$user_id]);
$completed_tasks = $stmt->fetchAll();

// Получение доступных заданий
$stmt = $pdo->prepare("SELECT * FROM tasks 
                      WHERE level_required <= ? 
                      AND id NOT IN (SELECT task_id FROM user_tasks WHERE user_id = ?)
                      ORDER BY level_required, id");
$stmt->execute([$user['level'], $user_id]);
$available_tasks = $stmt->fetchAll();

// Расчет прогресса опыта
$xp_for_current_level = $user['level'] * 1000; // 1000 XP за уровень
$xp_progress = min(($user['experience'] % 1000) / 10, 100);
$xp_to_next_level = $xp_for_current_level - $user['experience'];

// Обновление времени последней активности
$pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user_id]);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет | HackCraft</title>
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
                <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Дашборд</a></li>
                <li><a href="articles.php" class="nav-link"><i class="fas fa-newspaper"></i> Статьи</a></li>
                <li><a href="tasks.php" class="nav-link"><i class="fas fa-tasks"></i> Задания</a></li>
                <li><a href="terminal.php" class="nav-link"><i class="fas fa-terminal"></i> Терминал</a></li>
                <li><a href="server-parser.php" class="nav-link"><i class="fas fa-server"></i> Парсер серверов</a></li>
                <li><a href="donate.php" class="nav-link"><i class="fas fa-gem"></i> Донат</a></li>
                <?php if ($user['is_admin']): ?>
                    <li><a href="admin.php" class="nav-link admin-link"><i class="fas fa-shield-alt"></i> Админ</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-info">
                <span class="user-level">Уровень <?php echo $user['level']; ?></span>
                <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                <a href="logout.php" class="logout-btn" title="Выйти"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Приветствие -->
        <div class="welcome-section">
            <h1 class="typewriter">Добро пожаловать, <?php echo htmlspecialchars($user['username']); ?>!</h1>
            <p class="subtitle">Ваш путь к мастерству хакинга Minecraft начинается здесь</p>
        </div>

        <!-- Основная сетка дашборда -->
        <div class="dashboard-grid">
            <!-- Карточка статистики -->
            <div class="dashboard-card">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> Статистика</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Уровень</span>
                        <span class="stat-value"><?php echo $user['level']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Опыт</span>
                        <span class="stat-value"><?php echo $user['experience']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Достижения</span>
                        <span class="stat-value"><?php echo count($achievements); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Задания</span>
                        <span class="stat-value"><?php echo count($completed_tasks); ?></span>
                    </div>
                </div>
                
                <!-- Прогресс-бар опыта -->
                <div class="xp-bar-container">
                    <div class="xp-bar">
                        <div class="xp-progress" style="width: <?php echo $xp_progress; ?>%"></div>
                    </div>
                    <div class="xp-info">
                        <span>До следующего уровня: <?php echo $xp_to_next_level; ?> XP</span>
                        <span><?php echo $user['experience']; ?> / <?php echo $xp_for_current_level; ?></span>
                    </div>
                </div>
            </div>

            <!-- Карточка быстрого доступа -->
            <div class="dashboard-card">
                <h3 class="card-title"><i class="fas fa-bolt"></i> Быстрый доступ</h3>
                <div class="quick-actions">
                    <a href="terminal.php" class="quick-action">
                        <i class="fas fa-terminal"></i>
                        <span>Терминал</span>
                    </a>
                    <a href="tasks.php" class="quick-action">
                        <i class="fas fa-tasks"></i>
                        <span>Задания</span>
                    </a>
                    <a href="articles.php" class="quick-action">
                        <i class="fas fa-newspaper"></i>
                        <span>Статьи</span>
                    </a>
                    <a href="server-parser.php" class="quick-action">
                        <i class="fas fa-server"></i>
                        <span>Парсер</span>
                    </a>
                    <a href="donate.php" class="quick-action vip">
                        <i class="fas fa-gem"></i>
                        <span>Донат</span>
                    </a>
                    <a href="#" class="quick-action" onclick="showCommandHelp()">
                        <i class="fas fa-question-circle"></i>
                        <span>Помощь</span>
                    </a>
                </div>
            </div>

            <!-- Карточка последних достижений -->
            <div class="dashboard-card">
                <h3 class="card-title"><i class="fas fa-trophy"></i> Последние достижения</h3>
                <div class="achievements-list">
                    <?php if (count($achievements) > 0): ?>
                        <?php foreach ($achievements as $achievement): ?>
                            <div class="achievement-item">
                                <i class="fas fa-medal" style="color: #FFD700;"></i>
                                <div class="achievement-info">
                                    <strong><?php echo htmlspecialchars($achievement['achievement_name']); ?></strong>
                                    <small><?php echo date('d.m.Y', strtotime($achievement['earned_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Достижений пока нет. Выполняйте задания!</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Карточка активных заданий -->
            <div class="dashboard-card">
                <h3 class="card-title"><i class="fas fa-gamepad"></i> Доступные задания</h3>
                <div class="tasks-preview">
                    <?php if (count($available_tasks) > 0): ?>
                        <?php foreach (array_slice($available_tasks, 0, 3) as $task): ?>
                            <div class="task-preview-item">
                                <div class="task-preview-header">
                                    <span class="task-preview-title"><?php echo htmlspecialchars($task['title']); ?></span>
                                    <span class="task-preview-xp">+<?php echo $task['experience_reward']; ?> XP</span>
                                </div>
                                <p class="task-preview-desc"><?php echo substr($task['description'], 0, 60); ?>...</p>
                                <div class="task-preview-footer">
                                    <span class="task-level-badge">Ур. <?php echo $task['level_required']; ?></span>
                                    <a href="tasks.php#task-<?php echo $task['id']; ?>" class="btn-start-task">Начать</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Все задания выполнены! Ждите новых обновлений.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Карточка терминала -->
            <div class="dashboard-card full-width">
                <h3 class="card-title"><i class="fas fa-code"></i> Быстрый терминал</h3>
                <div class="quick-terminal">
                    <div class="terminal-output" id="quick-terminal-output">
                        <p>Введите команду для быстрого выполнения</p>
                        <p>Доступные команды: help, status, decode [текст]</p>
                    </div>
                    <form class="terminal-input-form" id="quick-terminal-form">
                        <div class="input-line">
                            <span class="prompt"><?php echo htmlspecialchars($user['username']); ?>@hackcraft:~$</span>
                            <input type="text" class="terminal-input" id="quick-terminal-input" 
                                   placeholder="Введите команду..." autocomplete="off">
                            <button type="submit" class="enter-btn"><i class="fas fa-arrow-right"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Карточка системы доната -->
            <?php if ($user['vip_level'] == 'none'): ?>
            <div class="dashboard-card glow">
                <h3 class="card-title"><i class="fas fa-gem"></i> Улучшите аккаунт!</h3>
                <div class="upgrade-promo">
                    <p>Получите доступ к эксклюзивным функциям:</p>
                    <ul class="upgrade-features">
                        <li><i class="fas fa-check"></i> Парсер серверов Minecraft</li>
                        <li><i class="fas fa-check"></i> Расширенный терминал</li>
                        <li><i class="fas fa-check"></i> Приоритетная поддержка</li>
                        <li><i class="fas fa-check"></i> Бонусные задания</li>
                    </ul>
                    <a href="donate.php" class="btn-upgrade">Перейти к улучшению</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Модальное окно помощи -->
    <div id="help-modal" class="modal">
        <div class="modal-content terminal-modal">
            <div class="modal-header">
                <h3><i class="fas fa-question-circle"></i> Справка по командам</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-commands">
                    <h4>Основные команды:</h4>
                    <table class="commands-table">
                        <tr>
                            <td><code>help</code></td>
                            <td>Показать эту справку</td>
                        </tr>
                        <tr>
                            <td><code>status</code></td>
                            <td>Показать статус аккаунта</td>
                        </tr>
                        <tr>
                            <td><code>decode [текст]</code></td>
                            <td>Расшифровать текст</td>
                        </tr>
                        <tr>
                            <td><code>clear</code></td>
                            <td>Очистить терминал</td>
                        </tr>
                    </table>
                    
                    <h4>Команды навигации:</h4>
                    <table class="commands-table">
                        <tr>
                            <td><code>cd articles</code></td>
                            <td>Перейти к статьям</td>
                        </tr>
                        <tr>
                            <td><code>cd tasks</code></td>
                            <td>Перейти к заданиям</td>
                        </tr>
                        <tr>
                            <td><code>cd parser</code></td>
                            <td>Перейти к парсеру (требуется VIP)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // Быстрый терминал
    document.getElementById('quick-terminal-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const input = document.getElementById('quick-terminal-input');
        const command = input.value.trim();
        const output = document.getElementById('quick-terminal-output');
        
        if (command === 'help') {
            output.innerHTML = `
                <h4>Доступные команды:</h4>
                <p><code>help</code> - эта справка</p>
                <p><code>status</code> - статус аккаунта</p>
                <p><code>decode [текст]</code> - расшифровать текст</p>
                <p><code>clear</code> - очистить терминал</p>
            `;
        } else if (command === 'status') {
            output.innerHTML = `
                <h4>Статус аккаунта:</h4>
                <p>Пользователь: <?php echo htmlspecialchars($user['username']); ?></p>
                <p>Уровень: <?php echo $user['level']; ?></p>
                <p>Опыт: <?php echo $user['experience']; ?></p>
                <p>VIP статус: <?php echo $user['vip_level']; ?></p>
            `;
        } else if (command.startsWith('decode ')) {
            const text = command.substring(7);
            // Простая демо-дешифровка
            const decoded = text.split('').map(c => 
                String.fromCharCode(c.charCodeAt(0) - 1)
            ).join('');
            output.innerHTML = `
                <h4>Результат дешифровки:</h4>
                <p>Исходный: ${text}</p>
                <p>Расшифрованный: ${decoded}</p>
            `;
        } else if (command === 'clear') {
            output.innerHTML = '<p>Терминал очищен</p>';
        } else {
            output.innerHTML = `<p class="error">Неизвестная команда: ${command}</p>`;
        }
        
        input.value = '';
    });
    
    // Модальное окно помощи
    function showCommandHelp() {
        document.getElementById('help-modal').style.display = 'block';
    }
    
    document.querySelector('.close-modal').addEventListener('click', function() {
        document.getElementById('help-modal').style.display = 'none';
    });
    
    // Закрытие модального окна при клике вне его
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('help-modal');
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    </script>
    
    <style>
    /* Дополнительные стили для дашборда */
    .welcome-section {
        text-align: center;
        margin: 30px 0;
        padding: 20px;
        background: linear-gradient(135deg, rgba(0,255,0,0.1), rgba(0,255,255,0.1));
        border-radius: 10px;
        border: 1px solid var(--terminal-green);
    }
    
    .subtitle {
        color: var(--terminal-cyan);
        font-size: 18px;
        margin-top: 10px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin: 20px 0;
    }
    
    .stat-item {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid var(--terminal-border);
    }
    
    .stat-label {
        display: block;
        color: #888;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .stat-value {
        display: block;
        color: var(--terminal-green);
        font-size: 24px;
        font-weight: bold;
    }
    
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 20px;
    }
    
    .quick-action {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 20px 10px;
        border-radius: 8px;
        text-align: center;
        text-decoration: none;
        color: var(--terminal-text);
        border: 1px solid var(--terminal-border);
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .quick-action:hover {
        border-color: var(--terminal-green);
        transform: translateY(-3px);
        background-color: rgba(0, 255, 0, 0.1);
    }
    
    .quick-action.vip {
        border-color: var(--terminal-purple);
        color: var(--terminal-purple);
    }
    
    .quick-action.vip:hover {
        background-color: rgba(255, 0, 255, 0.1);
    }
    
    .quick-action i {
        font-size: 24px;
    }
    
    .achievements-list {
        margin-top: 20px;
    }
    
    .achievement-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px;
        margin-bottom: 10px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
        border-left: 4px solid #FFD700;
    }
    
    .achievement-info {
        flex: 1;
    }
    
    .achievement-info small {
        color: #888;
        display: block;
        margin-top: 5px;
    }
    
    .tasks-preview {
        margin-top: 20px;
    }
    
    .task-preview-item {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid var(--terminal-border);
    }
    
    .task-preview-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .task-preview-title {
        font-weight: bold;
        color: var(--terminal-cyan);
    }
    
    .task-preview-xp {
        color: var(--terminal-yellow);
        font-weight: bold;
    }
    
    .task-preview-desc {
        color: #aaa;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .task-preview-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .task-level-badge {
        background-color: var(--terminal-purple);
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 12px;
    }
    
    .btn-start-task {
        background-color: var(--terminal-green);
        color: black;
        padding: 5px 15px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s;
    }
    
    .btn-start-task:hover {
        background-color: #00cc00;
        transform: translateX(2px);
    }
    
    .quick-terminal {
        margin-top: 20px;
    }
    
    .upgrade-promo {
        text-align: center;
        padding: 20px;
    }
    
    .upgrade-features {
        list-style: none;
        margin: 20px 0;
        text-align: left;
    }
    
    .upgrade-features li {
        margin-bottom: 10px;
        padding: 8px;
        background-color: rgba(0, 255, 255, 0.1);
        border-radius: 4px;
    }
    
    .upgrade-features i {
        color: var(--terminal-green);
        margin-right: 10px;
    }
    
    .btn-upgrade {
        display: inline-block;
        background: linear-gradient(45deg, var(--terminal-purple), var(--terminal-cyan));
        color: white;
        padding: 12px 30px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s;
    }
    
    .btn-upgrade:hover {
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(255, 0, 255, 0.3);
    }
    
    .full-width {
        grid-column: 1 / -1;
    }
    
    /* Модальное окно */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
    }
    
    .modal-content {
        background-color: var(--terminal-bg);
        margin: 5% auto;
        padding: 0;
        width: 80%;
        max-width: 800px;
        border-radius: 8px;
        border: 2px solid var(--terminal-green);
    }
    
    .modal-header {
        background: linear-gradient(to right, #222, #333);
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--terminal-border);
    }
    
    .modal-header h3 {
        color: var(--terminal-green);
        margin: 0;
    }
    
    .close-modal {
        color: var(--terminal-red);
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close-modal:hover {
        color: #ff0000;
    }
    
    .modal-body {
        padding: 20px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .commands-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }
    
    .commands-table td {
        padding: 10px;
        border-bottom: 1px solid var(--terminal-border);
    }
    
    .commands-table code {
        background-color: rgba(0, 255, 0, 0.1);
        padding: 2px 8px;
        border-radius: 4px;
        color: var(--terminal-green);
    }
    
    @media (max-width: 768px) {
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            width: 95%;
            margin: 10% auto;
        }
    }
    </style>
</body>
</html>