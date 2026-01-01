<?php
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Получение информации о пользователе
$stmt = $pdo->prepare("SELECT level, experience, vip_level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Обработка выполнения задания
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_task'])) {
    $task_id = $_POST['task_id'];
    
    // Проверяем, выполнено ли уже задание
    $check_stmt = $pdo->prepare("SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ?");
    $check_stmt->execute([$user_id, $task_id]);
    
    if ($check_stmt->rowCount() === 0) {
        // Получаем информацию о задании
        $task_stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $task_stmt->execute([$task_id]);
        $task = $task_stmt->fetch();
        
        if ($task && $user['level'] >= $task['level_required']) {
            // Добавляем выполненное задание
            $insert_stmt = $pdo->prepare("INSERT INTO user_tasks (user_id, task_id) VALUES (?, ?)");
            $insert_stmt->execute([$user_id, $task_id]);
            
            // Начисляем опыт
            $new_experience = $user['experience'] + $task['experience_reward'];
            $update_stmt = $pdo->prepare("UPDATE users SET experience = ? WHERE id = ?");
            $update_stmt->execute([$new_experience, $user_id]);
            
            // Проверяем повышение уровня
            $new_level = floor($new_experience / 1000) + 1;
            if ($new_level > $user['level']) {
                $update_level_stmt = $pdo->prepare("UPDATE users SET level = ? WHERE id = ?");
                $update_level_stmt->execute([$new_level, $user_id]);
                
                // Добавляем достижение за новый уровень
                $achievement_stmt = $pdo->prepare("INSERT INTO achievements (user_id, achievement_name, description) VALUES (?, ?, ?)");
                $achievement_stmt->execute([
                    $user_id, 
                    "Достигнут уровень " . $new_level,
                    "Поздравляем! Вы достигли уровня " . $new_level
                ]);
                
                $_SESSION['level_up'] = $new_level;
            }
            
            // Добавляем достижение за выполнение задания
            if ($task['category'] === 'Уровень 1' && !isset($_SESSION['first_task_completed'])) {
                $achievement_stmt = $pdo->prepare("INSERT INTO achievements (user_id, achievement_name, description) VALUES (?, ?, ?)");
                $achievement_stmt->execute([
                    $user_id, 
                    "Первый шаг",
                    "Выполнено первое задание"
                ]);
                $_SESSION['first_task_completed'] = true;
            }
            
            $_SESSION['task_completed'] = $task_id;
            $_SESSION['experience_gained'] = $task['experience_reward'];
            
            header("Location: tasks.php?success=1");
            exit();
        }
    }
}

// Получение всех заданий сгруппированных по уровням
$tasks_by_level = [];
$stmt = $pdo->query("SELECT * FROM tasks ORDER BY level_required, category, id");
$all_tasks = $stmt->fetchAll();

foreach ($all_tasks as $task) {
    $tasks_by_level[$task['category']][] = $task;
}

// Получение выполненных заданий пользователя
$completed_stmt = $pdo->prepare("SELECT task_id FROM user_tasks WHERE user_id = ?");
$completed_stmt->execute([$user_id]);
$completed_tasks = array_column($completed_stmt->fetchAll(), 'task_id');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задания | HackCraft</title>
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
                <li><a href="articles.php" class="nav-link"><i class="fas fa-newspaper"></i> Статьи</a></li>
                <li><a href="tasks.php" class="nav-link active"><i class="fas fa-tasks"></i> Задания</a></li>
                <li><a href="terminal.php" class="nav-link"><i class="fas fa-terminal"></i> Терминал</a></li>
                <li><a href="server-parser.php" class="nav-link"><i class="fas fa-server"></i> Парсер серверов</a></li>
                <li><a href="donate.php" class="nav-link"><i class="fas fa-gem"></i> Донат</a></li>
            </ul>
            <div class="user-info">
                <span class="user-level">Уровень <?php echo $user['level']; ?></span>
                <span class="user-xp"><?php echo $user['experience']; ?> XP</span>
                <a href="logout.php" class="logout-btn" title="Выйти"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Заголовок -->
        <div class="page-header">
            <h1><i class="fas fa-gamepad"></i> Задания по хакингу Minecraft</h1>
            <p class="subtitle">Прокачивайте свои навыки, выполняя задания разных уровней сложности</p>
            
            <!-- Прогресс-бар -->
            <div class="xp-summary">
                <div class="xp-bar-container">
                    <div class="xp-bar">
                        <div class="xp-progress" 
                             style="width: <?php echo min(($user['experience'] % 1000) / 10, 100); ?>%"></div>
                    </div>
                    <div class="xp-info">
                        <span>До уровня <?php echo $user['level'] + 1; ?>: <?php echo (($user['level'] + 1) * 1000) - $user['experience']; ?> XP</span>
                        <span>Уровень <?php echo $user['level']; ?> | <?php echo $user['experience']; ?> XP</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Уведомления о выполнении -->
        <?php if (isset($_SESSION['task_completed'])): ?>
            <div class="task-notification success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>Задание выполнено!</h4>
                    <p>Вы получили <?php echo $_SESSION['experience_gained']; ?> опыта</p>
                </div>
                <button onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['task_completed'], $_SESSION['experience_gained']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['level_up'])): ?>
            <div class="task-notification level-up">
                <i class="fas fa-trophy"></i>
                <div>
                    <h4>Поздравляем!</h4>
                    <p>Вы достигли уровня <?php echo $_SESSION['level_up']; ?>!</p>
                </div>
                <button onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['level_up']); ?>
        <?php endif; ?>

        <!-- Основной контент -->
        <div class="tasks-container">
            <?php foreach ($tasks_by_level as $level_name => $tasks): ?>
                <div class="level-section" id="level-<?php echo str_replace(' ', '-', strtolower($level_name)); ?>">
                    <h2 class="level-title">
                        <span class="level-icon">
                            <?php if (strpos($level_name, '1') !== false): ?>
                                <i class="fas fa-user-graduate"></i>
                            <?php elseif (strpos($level_name, '2') !== false): ?>
                                <i class="fas fa-user-secret"></i>
                            <?php elseif (strpos($level_name, '3') !== false): ?>
                                <i class="fas fa-user-ninja"></i>
                            <?php elseif (strpos($level_name, '4') !== false): ?>
                                <i class="fas fa-user-astronaut"></i>
                            <?php else: ?>
                                <i class="fas fa-star"></i>
                            <?php endif; ?>
                        </span>
                        <?php echo $level_name; ?>
                        <span class="level-badge"><?php echo count($tasks); ?> заданий</span>
                    </h2>
                    
                    <div class="level-description">
                        <?php 
                        $descriptions = [
                            'Уровень 1' => 'Освойте основы хакинга Minecraft серверов. Идеально для новичков.',
                            'Уровень 2' => 'Повышенная сложность. Требуются базовые знания сетевой безопасности.',
                            'Уровень 3' => 'Продвинутые техники взлома. Для опытных пользователей.',
                            'Уровень 4' => 'Экспертный уровень. Сложнейшие задания для мастеров хакинга.'
                        ];
                        echo $descriptions[$level_name] ?? 'Задания различной сложности';
                        ?>
                    </div>
                    
                    <div class="tasks-grid">
                        <?php foreach ($tasks as $task): 
                            $is_completed = in_array($task['id'], $completed_tasks);
                            $is_available = $user['level'] >= $task['level_required'];
                            $is_locked = !$is_available && !$is_completed;
                        ?>
                            <div class="task-card <?php echo $is_completed ? 'completed' : ''; ?> <?php echo $is_locked ? 'locked' : ''; ?>" 
                                 id="task-<?php echo $task['id']; ?>">
                                <div class="task-header">
                                    <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                    <div class="task-meta">
                                        <span class="task-xp">+<?php echo $task['experience_reward']; ?> XP</span>
                                        <span class="task-level">Ур. <?php echo $task['level_required']; ?></span>
                                    </div>
                                </div>
                                
                                <p class="task-description">
                                    <?php echo htmlspecialchars($task['description']); ?>
                                </p>
                                
                                <div class="task-command">
                                    <code><?php echo htmlspecialchars($task['command']); ?></code>
                                </div>
                                
                                <div class="task-footer">
                                    <?php if ($is_completed): ?>
                                        <div class="task-status completed">
                                            <i class="fas fa-check-circle"></i> Выполнено
                                        </div>
                                    <?php elseif ($is_locked): ?>
                                        <div class="task-status locked">
                                            <i class="fas fa-lock"></i> Требуется уровень <?php echo $task['level_required']; ?>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" class="task-form">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <button type="submit" name="complete_task" class="btn-start-task">
                                                <i class="fas fa-play"></i> Начать выполнение
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Детали выполнения задания (открываются по клику) -->
                                <div class="task-details" style="display: none;">
                                    <div class="task-instructions">
                                        <h4>Инструкция по выполнению:</h4>
                                        <?php 
                                        // Генерация инструкций в зависимости от команды
                                        $instructions = generateTaskInstructions($task['command']);
                                        echo $instructions;
                                        ?>
                                    </div>
                                    
                                    <?php if (!$is_completed && !$is_locked): ?>
                                        <div class="task-hint">
                                            <button class="btn-hint" onclick="showHint(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-lightbulb"></i> Показать подсказку
                                            </button>
                                            <div class="hint-content" id="hint-<?php echo $task['id']; ?>" style="display: none;">
                                                <?php echo getTaskHint($task['command']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <button class="btn-toggle-details" onclick="toggleTaskDetails(<?php echo $task['id']; ?>)">
                                    <i class="fas fa-chevron-down"></i> Подробнее
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Статистика выполнения -->
        <div class="tasks-stats">
            <div class="stats-card">
                <h3><i class="fas fa-chart-pie"></i> Статистика выполнения</h3>
                <div class="stats-content">
                    <div class="stat-item">
                        <span class="stat-label">Всего заданий</span>
                        <span class="stat-value"><?php echo count($all_tasks); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Выполнено</span>
                        <span class="stat-value"><?php echo count($completed_tasks); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Процент выполнения</span>
                        <span class="stat-value">
                            <?php echo count($all_tasks) > 0 ? round((count($completed_tasks) / count($all_tasks)) * 100) : 0; ?>%
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Заработано опыта</span>
                        <span class="stat-value">
                            <?php 
                            $total_xp = 0;
                            foreach ($completed_tasks as $task_id) {
                                $xp_stmt = $pdo->prepare("SELECT experience_reward FROM tasks WHERE id = ?");
                                $xp_stmt->execute([$task_id]);
                                $xp = $xp_stmt->fetch();
                                if ($xp) $total_xp += $xp['experience_reward'];
                            }
                            echo $total_xp;
                            ?> XP
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // Переключение деталей задания
    function toggleTaskDetails(taskId) {
        const details = document.querySelector(`#task-${taskId} .task-details`);
        const button = document.querySelector(`#task-${taskId} .btn-toggle-details i`);
        
        if (details.style.display === 'none') {
            details.style.display = 'block';
            button.className = 'fas fa-chevron-up';
            details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            details.style.display = 'none';
            button.className = 'fas fa-chevron-down';
        }
    }
    
    // Показать подсказку
    function showHint(taskId) {
        const hint = document.getElementById(`hint-${taskId}`);
        const button = document.querySelector(`#task-${taskId} .btn-hint i`);
        
        if (hint.style.display === 'none') {
            hint.style.display = 'block';
            button.className = 'fas fa-eye-slash';
            hint.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            hint.style.display = 'none';
            button.className = 'fas fa-lightbulb';
        }
    }
    
    // Анимация при наведении на задания
    document.querySelectorAll('.task-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.classList.contains('locked')) {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 10px 20px rgba(0, 255, 0, 0.2)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
    
    // Плавная прокрутка к уровню
    document.querySelectorAll('.nav-level-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    </script>
    
    <style>
    /* Стили для страницы заданий */
    .xp-summary {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        border: 1px solid var(--terminal-border);
    }
    
    .task-notification {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 20px;
        margin: 20px 0;
        border-radius: 8px;
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .task-notification.success {
        background-color: rgba(0, 255, 0, 0.1);
        border-left: 4px solid var(--terminal-green);
        color: var(--terminal-green);
    }
    
    .task-notification.level-up {
        background-color: rgba(255, 215, 0, 0.1);
        border-left: 4px solid var(--terminal-yellow);
        color: var(--terminal-yellow);
    }
    
    .task-notification button {
        background: none;
        border: none;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
        margin-left: auto;
    }
    
    .level-section {
        margin: 40px 0;
        padding: 20px;
        background-color: rgba(20, 20, 20, 0.8);
        border-radius: 8px;
        border: 1px solid var(--terminal-border);
    }
    
    .level-title {
        color: var(--terminal-cyan);
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .level-icon {
        font-size: 24px;
        width: 50px;
        height: 50px;
        background-color: rgba(0, 255, 255, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .level-badge {
        background-color: var(--terminal-purple);
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 14px;
        margin-left: auto;
    }
    
    .level-description {
        color: #aaa;
        font-style: italic;
        margin-bottom: 20px;
        padding-left: 65px;
    }
    
    .tasks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .task-card {
        background-color: rgba(30, 30, 30, 0.8);
        border-radius: 8px;
        padding: 20px;
        border-left: 4px solid var(--terminal-green);
        transition: all 0.3s;
        position: relative;
    }
    
    .task-card.completed {
        border-left-color: var(--terminal-cyan);
        opacity: 0.9;
    }
    
    .task-card.completed::after {
        content: '✓ ВЫПОЛНЕНО';
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: var(--terminal-cyan);
        color: #000;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: bold;
    }
    
    .task-card.locked {
        border-left-color: var(--terminal-red);
        opacity: 0.6;
    }
    
    .task-card.locked::after {
        content: '🔒 ЗАБЛОКИРОВАНО';
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: var(--terminal-red);
        color: white;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: bold;
    }
    
    .task-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }
    
    .task-title {
        color: var(--terminal-cyan);
        font-size: 18px;
        margin: 0;
        flex: 1;
    }
    
    .task-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
    }
    
    .task-xp {
        color: var(--terminal-yellow);
        font-weight: bold;
        font-size: 16px;
    }
    
    .task-level {
        background-color: var(--terminal-purple);
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
    }
    
    .task-description {
        color: #aaa;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 15px;
        min-height: 60px;
    }
    
    .task-command {
        background-color: #000;
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
        border: 1px solid var(--terminal-border);
        font-family: 'Courier New', monospace;
        color: var(--terminal-green);
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .task-footer {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--terminal-border);
    }
    
    .task-status {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: bold;
    }
    
    .task-status.completed {
        color: var(--terminal-cyan);
    }
    
    .task-status.locked {
        color: var(--terminal-red);
    }
    
    .task-form {
        margin: 0;
    }
    
    .btn-start-task {
        width: 100%;
        background-color: var(--terminal-green);
        color: #000;
        border: none;
        padding: 12px;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-start-task:hover {
        background-color: #00cc00;
        transform: translateY(-2px);
    }
    
    .task-details {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--terminal-border);
    }
    
    .task-instructions {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    
    .task-instructions h4 {
        color: var(--terminal-cyan);
        margin-bottom: 10px;
    }
    
    .task-hint {
        margin-top: 15px;
    }
    
    .btn-hint {
        background-color: var(--terminal-yellow);
        color: #000;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-hint:hover {
        background-color: #cccc00;
    }
    
    .hint-content {
        background-color: rgba(255, 255, 0, 0.1);
        padding: 15px;
        border-radius: 4px;
        margin-top: 10px;
        border-left: 3px solid var(--terminal-yellow);
    }
    
    .btn-toggle-details {
        width: 100%;
        background: none;
        border: 1px solid var(--terminal-border);
        color: var(--terminal-text);
        padding: 10px;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 15px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-toggle-details:hover {
        background-color: rgba(0, 255, 0, 0.1);
        border-color: var(--terminal-green);
    }
    
    .tasks-stats {
        margin: 40px 0;
    }
    
    .stats-card {
        background-color: rgba(20, 20, 20, 0.8);
        border-radius: 8px;
        padding: 30px;
        border: 2px solid var(--terminal-purple);
    }
    
    .stats-card h3 {
        color: var(--terminal-purple);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .stats-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .stats-content .stat-item {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid var(--terminal-border);
    }
    
    .stats-content .stat-label {
        display: block;
        color: #888;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .stats-content .stat-value {
        display: block;
        color: var(--terminal-green);
        font-size: 24px;
        font-weight: bold;
    }
    
    @media (max-width: 768px) {
        .tasks-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-content {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .level-description {
            padding-left: 0;
        }
        
        .level-title {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .level-badge {
            margin-left: 0;
        }
    }
    </style>
</body>
</html>

<?php
// Вспомогательные функции для заданий
function generateTaskInstructions($command) {
    $instructions = [
        '/steal_data' => '
            <p><strong>Задание:</strong> Узнайте название сервера, используя технику SQL-инъекции</p>
            <p><strong>Шаги выполнения:</strong></p>
            <ol>
                <li>Откройте терминал и введите команду <code>cd sql-lab</code></li>
                <li>Найдите уязвимую форму поиска</li>
                <li>Введите SQL-инъекцию: <code>\' OR \'1\'=\'1</code></li>
                <li>Проанализируйте вывод и найдите название сервера</li>
                <li>Введите найденное название в поле ответа</li>
            </ol>',
        
        '/bypass_firewall' => '
            <p><strong>Задание:</strong> Пропустите защиту файрвола, решив несложную головоломку</p>
            <p><strong>Шаги выполнения:</strong></p>
            <ol>
                <li>Подключитесь к тестовому серверу</li>
                <li>Просканируйте открытые порты</li>
                <li>Найдите обходные пути через порт 8080</li>
                <li>Решите головоломку с кодами доступа</li>
                <li>Введите полученный код для обхода файрвола</li>
            </ol>',
        
        '/decrypt_message' => '
            <p><strong>Задание:</strong> Получите ключ для дешифровки более сложного сообщения</p>
            <p><strong>Шаги выполнения:</strong></p>
            <ol>
                <li>Скачайте зашифрованное сообщение</li>
                <li>Используйте команду <code>analyze message.txt</code></li>
                <li>Определите тип шифрования (Base64, ROT13, AES)</li>
                <li>Найдите ключ в логах системы</li>
                <li>Расшифруйте сообщение и извлеките ключ</li>
            </ol>',
        
        '/bruteforce_password' => '
            <p><strong>Задание:</strong> Используя метод перебора, получите доступ к защищённому хранилищу</p>
            <p><strong>Шаги выполнения:</strong></p>
            <ol>
                <li>Скачайте словарь паролей</li>
                <li>Настройте инструмент для брутфорса</li>
                <li>Запустите перебор паролей</li>
                <li>Дождитесь успешного подбора</li>
                <li>Введите найденный пароль</li>
            </ol>'
    ];
    
    return $instructions[$command] ?? '<p>Подробные инструкции будут доступны после начала задания.</p>';
}

function getTaskHint($command) {
    $hints = [
        '/steal_data' => 'Попробуйте использовать UNION SELECT для извлечения дополнительной информации из базы данных.',
        '/bypass_firewall' => 'Файрвол может быть настроен на блокировку определенных типов трафика. Попробуйте изменить User-Agent.',
        '/decrypt_message' => 'Проверьте, нет ли в сообщении стандартных заголовков шифрования. Часто используются Base64 или XOR.',
        '/bruteforce_password' => 'Начните с самых распространенных паролей. Используйте словарь rockyou.txt если доступен.'
    ];
    
    return $hints[$command] ?? 'Подсказка: внимательно изучите все доступные материалы и логи системы.';
}
?>