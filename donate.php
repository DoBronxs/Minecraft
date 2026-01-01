<?php
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Получение информации о пользователе
$stmt = $pdo->prepare("SELECT username, vip_level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Обработка покупки доната
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_vip'])) {
    $vip_level = $_POST['vip_level'];
    $amount = $_POST['amount'];
    
    // В реальном приложении здесь была бы интеграция с платежной системой
    // Для демонстрации просто обновляем статус пользователя
    
    $stmt = $pdo->prepare("UPDATE users SET vip_level = ? WHERE id = ?");
    $stmt->execute([$vip_level, $user_id]);
    
    // Добавляем достижение
    $achievement_name = "VIP " . ($vip_level === 'vip' ? "Статус" : "Хакер");
    $stmt = $pdo->prepare("INSERT INTO achievements (user_id, achievement_name, description) VALUES (?, ?, ?)");
    $stmt->execute([
        $user_id,
        $achievement_name,
        "Получен VIP статус: " . $vip_level
    ]);
    
    $_SESSION['vip_upgraded'] = $vip_level;
    header("Location: donate.php?success=1");
    exit();
}

// Обработка разблокировки админ-панели
if (isset($_GET['unlock_admin']) && $_GET['unlock_admin'] == 1) {
    // В реальном приложении здесь была бы проверка платежа
    $ip = $_SERVER['REMOTE_ADDR'];
    $pdo->prepare("DELETE FROM blocked_ips WHERE ip_address = ?")->execute([$ip]);
    $_SESSION['admin_unlocked'] = true;
    header("Location: admin.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Донат | HackCraft</title>
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
                <li><a href="tasks.php" class="nav-link"><i class="fas fa-tasks"></i> Задания</a></li>
                <li><a href="terminal.php" class="nav-link"><i class="fas fa-terminal"></i> Терминал</a></li>
                <li><a href="server-parser.php" class="nav-link"><i class="fas fa-server"></i> Парсер серверов</a></li>
                <li><a href="donate.php" class="nav-link active"><i class="fas fa-gem"></i> Донат</a></li>
            </ul>
            <div class="user-info">
                <span class="vip-status <?php echo $user['vip_level']; ?>">
                    <?php 
                    if ($user['vip_level'] === 'none') echo 'Бесплатный';
                    elseif ($user['vip_level'] === 'vip') echo 'VIP';
                    else echo 'HACKER';
                    ?>
                </span>
                <a href="logout.php" class="logout-btn" title="Выйти"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Заголовок -->
        <div class="page-header">
            <h1><i class="fas fa-gem"></i> Улучшите свой аккаунт</h1>
            <p class="subtitle">Получите доступ к эксклюзивным функциям и поддержите развитие проекта</p>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="donate-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <h4>Спасибо за поддержку!</h4>
                        <p>Ваш аккаунт успешно улучшен. Новые функции уже доступны.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Текущий статус -->
        <div class="current-status">
            <h3><i class="fas fa-user"></i> Ваш текущий статус</h3>
            <div class="status-card <?php echo $user['vip_level']; ?>">
                <div class="status-icon">
                    <?php if ($user['vip_level'] === 'none'): ?>
                        <i class="fas fa-user"></i>
                    <?php elseif ($user['vip_level'] === 'vip'): ?>
                        <i class="fas fa-crown"></i>
                    <?php else: ?>
                        <i class="fas fa-user-ninja"></i>
                    <?php endif; ?>
                </div>
                <div class="status-info">
                    <h4>
                        <?php 
                        if ($user['vip_level'] === 'none') echo 'Бесплатный аккаунт';
                        elseif ($user['vip_level'] === 'vip') echo 'VIP Статус';
                        else echo 'HACKER Статус';
                        ?>
                    </h4>
                    <p>
                        <?php 
                        if ($user['vip_level'] === 'none') echo 'Базовые функции обучения';
                        elseif ($user['vip_level'] === 'vip') echo 'Доступ к парсеру серверов';
                        else echo 'Полный доступ ко всем функциям';
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Тарифные планы -->
        <div class="pricing-plans">
            <h2><i class="fas fa-tags"></i> Доступные тарифы</h2>
            <p class="section-description">Выберите подходящий вариант улучшения</p>
            
            <div class="plans-grid">
                <!-- Бесплатный тариф -->
                <div class="plan-card <?php echo $user['vip_level'] === 'none' ? 'current' : ''; ?>">
                    <div class="plan-header">
                        <h3>Бесплатный</h3>
                        <div class="plan-price">
                            <span class="price">0 ₽</span>
                            <span class="period">навсегда</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li class="included"><i class="fas fa-check"></i> Базовые уроки хакинга</li>
                            <li class="included"><i class="fas fa-check"></i> Терминал с основными командами</li>
                            <li class="included"><i class="fas fa-check"></i> Система заданий (уровни 1-2)</li>
                            <li class="included"><i class="fas fa-check"></i> Статьи (базовые)</li>
                            <li class="excluded"><i class="fas fa-times"></i> Парсер Minecraft серверов</li>
                            <li class="excluded"><i class="fas fa-times"></i> Расширенный терминал</li>
                            <li class="excluded"><i class="fas fa-times"></i> Задания уровней 3-4</li>
                            <li class="excluded"><i class="fas fa-times"></i> Приоритетная поддержка</li>
                        </ul>
                    </div>
                    <div class="plan-action">
                        <?php if ($user['vip_level'] === 'none'): ?>
                            <button class="btn-plan current" disabled>Текущий план</button>
                        <?php else: ?>
                            <button class="btn-plan" onclick="downgradePlan()">Вернуться к бесплатному</button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- VIP тариф -->
                <div class="plan-card vip <?php echo $user['vip_level'] === 'vip' ? 'current' : ''; ?>">
                    <div class="plan-badge">Популярный</div>
                    <div class="plan-header">
                        <h3>VIP</h3>
                        <div class="plan-price">
                            <span class="price">299 ₽</span>
                            <span class="period">/ навсегда</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li class="included"><i class="fas fa-check"></i> <strong>Всё из бесплатного тарифа</strong></li>
                            <li class="included"><i class="fas fa-check"></i> Парсер Minecraft серверов</li>
                            <li class="included"><i class="fas fa-check"></i> Команда <code>scan</code> в терминале</li>
                            <li class="included"><i class="fas fa-check"></i> До 999 IP за сканирование</li>
                            <li class="included"><i class="fas fa-check"></i> Задания уровней 1-3</li>
                            <li class="included"><i class="fas fa-check"></i> Расширенные статьи</li>
                            <li class="excluded"><i class="fas fa-times"></i> Команда <code>server list</code></li>
                            <li class="excluded"><i class="fas fa-times"></i> Сканирование по базе данных</li>
                        </ul>
                    </div>
                    <div class="plan-action">
                        <?php if ($user['vip_level'] === 'vip'): ?>
                            <button class="btn-plan vip current" disabled>Текущий план</button>
                        <?php else: ?>
                            <form method="POST" class="purchase-form">
                                <input type="hidden" name="vip_level" value="vip">
                                <input type="hidden" name="amount" value="299">
                                <button type="submit" name="purchase_vip" class="btn-plan vip">
                                    <i class="fas fa-shopping-cart"></i> Купить за 299 ₽
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- HACKER тариф -->
                <div class="plan-card hacker <?php echo $user['vip_level'] === 'hacker' ? 'current' : ''; ?>">
                    <div class="plan-header">
                        <h3>HACKER</h3>
                        <div class="plan-price">
                            <span class="price">999 ₽</span>
                            <span class="period">/ навсегда</span>
                        </div>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li class="included"><i class="fas fa-check"></i> <strong>Всё из VIP тарифа</strong></li>
                            <li class="included"><i class="fas fa-check"></i> Команда <code>server list</code></li>
                            <li class="included"><i class="fas fa-check"></i> Сканирование по базе данных</li>
                            <li class="included"><i class="fas fa-check"></i> Все задания (уровни 1-4)</li>
                            <li class="included"><i class="fas fa-check"></i> Приоритетная поддержка 24/7</li>
                            <li class="included"><i class="fas fa-check"></i> Эксклюзивные материалы</li>
                            <li class="included"><i class="fas fa-check"></i> Ранний доступ к новым функциям</li>
                            <li class="included"><i class="fas fa-check"></i> Уникальные достижения</li>
                        </ul>
                    </div>
                    <div class="plan-action">
                        <?php if ($user['vip_level'] === 'hacker'): ?>
                            <button class="btn-plan hacker current" disabled>Текущий план</button>
                        <?php else: ?>
                            <form method="POST" class="purchase-form">
                                <input type="hidden" name="vip_level" value="hacker">
                                <input type="hidden" name="amount" value="999">
                                <button type="submit" name="purchase_vip" class="btn-plan hacker">
                                    <i class="fas fa-shopping-cart"></i> Купить за 999 ₽
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Дополнительные опции -->
        <div class="extra-options">
            <h2><i class="fas fa-star"></i> Дополнительные услуги</h2>
            
            <div class="options-grid">
                <div class="option-card">
                    <div class="option-icon">
                        <i class="fas fa-unlock"></i>
                    </div>
                    <h4>Разблокировка админ-панели</h4>
                    <p class="option-price">100 ₽</p>
                    <p class="option-desc">Если вы заблокировали свой IP при попытке входа в админ-панель</p>
                    <a href="donate.php?unlock_admin=1" class="btn-option">
                        <i class="fas fa-lock-open"></i> Разблокировать
                    </a>
                </div>
                
                <div class="option-card">
                    <div class="option-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h4>Ускоренное обучение</h4>
                    <p class="option-price">199 ₽</p>
                    <p class="option-desc">+5000 опыта для быстрого повышения уровня</p>
                    <button class="btn-option" onclick="buyExperience()">
                        <i class="fas fa-rocket"></i> Купить опыт
                    </button>
                </div>
                
                <div class="option-card">
                    <div class="option-icon">
                        <i class="fas fa-code"></i>
                    </div>
                    <h4>Кастомная интеграция</h4>
                    <p class="option-price">от 999 ₽</p>
                    <p class="option-desc">Добавление вашего Python скрипта в систему</p>
                    <button class="btn-option" onclick="showIntegrationModal()">
                        <i class="fas fa-plus-circle"></i> Подробнее
                    </button>
                </div>
            </div>
        </div>

        <!-- Сравнение тарифов -->
        <div class="comparison-table">
            <h2><i class="fas fa-balance-scale"></i> Сравнение тарифов</h2>
            <table>
                <thead>
                    <tr>
                        <th>Функция</th>
                        <th>Бесплатный</th>
                        <th>VIP</th>
                        <th>HACKER</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Терминал с командами</td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td>Система заданий</td>
                        <td>Уровни 1-2</td>
                        <td>Уровни 1-3</td>
                        <td>Все уровни</td>
                    </tr>
                    <tr>
                        <td>Команда <code>scan</code></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td>Команда <code>server list</code></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td>Максимум IP для сканирования</td>
                        <td>-</td>
                        <td>999</td>
                        <td>Неограниченно</td>
                    </tr>
                    <tr>
                        <td>Приоритетная поддержка</td>
                        <td><i class="fas fa-times"></i></td>
                        <td>Базовая</td>
                        <td><i class="fas fa-check"></i> 24/7</td>
                    </tr>
                    <tr>
                        <td>Эксклюзивные материалы</td>
                        <td><i class="fas fa-times"></i></td>
                        <td>Частично</td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- FAQ -->
        <div class="faq-section">
            <h2><i class="fas fa-question-circle"></i> Частые вопросы</h2>
            
            <div class="faq-grid">
                <div class="faq-item">
                    <h4>Как происходит оплата?</h4>
                    <p>Оплата принимается через безопасные платежные системы. После подтверждения платежа статус аккаунта обновляется автоматически.</p>
                </div>
                
                <div class="faq-item">
                    <h4>Можно ли отменить подписку?</h4>
                    <p>Все тарифы приобретаются навсегда, поэтому отмена не требуется. Вы платите один раз и получаете доступ навсегда.</p>
                </div>
                
                <div class="faq-item">
                    <h4>Что такое парсер серверов?</h4>
                    <p>Это инструмент для поиска и анализа Minecraft серверов в указанных сетях. Позволяет находить активные серверы и получать информацию о них.</p>
                </div>
                
                <div class="faq-item">
                    <h4>Безопасны ли платежи?</h4>
                    <p>Да, все платежи обрабатываются через защищенные платежные шлюзы. Мы не храним данные ваших карт.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно интеграции -->
    <div id="integration-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-code"></i> Кастомная интеграция</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Мы можем интегрировать ваш Python скрипт в систему HackCraft. Скрипт будет доступен через скрытую страницу сайта.</p>
                
                <div class="integration-features">
                    <h4>Что входит в услугу:</h4>
                    <ul>
                        <li><i class="fas fa-check"></i> Размещение скрипта на нашем сервере</li>
                        <li><i class="fas fa-check"></i> Создание графического интерфейса управления</li>
                        <li><i class="fas fa-check"></i> Интеграция с системой аутентификации</li>
                        <li><i class="fas fa-check"></i> Техническая поддержка в течение месяца</li>
                    </ul>
                </div>
                
                <div class="integration-form">
                    <h4>Оставить заявку:</h4>
                    <form id="integration-request">
                        <div class="form-group">
                            <label>Email для связи</label>
                            <input type="email" required placeholder="your@email.com">
                        </div>
                        <div class="form-group">
                            <label>Описание скрипта</label>
                            <textarea placeholder="Опишите функционал вашего скрипта..." rows="4"></textarea>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Отправить заявку
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // Покупка опыта
    function buyExperience() {
        if (confirm('Купить 5000 опыта за 199 ₽?')) {
            // В реальном приложении здесь был бы запрос к серверу
            showNotification('Опыт успешно приобретен! +5000 XP', 'success');
        }
    }
    
    // Понижение тарифа
    function downgradePlan() {
        if (confirm('Вы уверены, что хотите вернуться к бесплатному тарифу?')) {
            // В реальном приложении здесь был бы запрос к серверу
            showNotification('Тариф изменен на бесплатный', 'info');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    }
    
    // Показать модальное окно интеграции
    function showIntegrationModal() {
        document.getElementById('integration-modal').style.display = 'block';
    }
    
    // Закрыть модальное окно
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // Закрытие модального окна при клике вне его
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
    
    // Обработка формы интеграции
    document.getElementById('integration-request').addEventListener('submit', function(e) {
        e.preventDefault();
        showNotification('Заявка отправлена! Мы свяжемся с вами в течение 24 часов.', 'success');
        document.getElementById('integration-modal').style.display = 'none';
    });
    
    // Показать уведомление
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
    </script>
    
    <style>
    /* Стили для страницы доната */
    .donate-success {
        background-color: rgba(0, 255, 0, 0.1);
        border: 1px solid var(--terminal-green);
        color: var(--terminal-green);
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        display: flex;
        align-items: center;
        gap: 20px;
        animation: slideDown 0.3s ease;
    }
    
    .donate-success i {
        font-size: 40px;
    }
    
    .current-status {
        margin: 40px 0;
    }
    
    .current-status h3 {
        color: var(--terminal-cyan);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .status-card {
        background-color: rgba(20, 20, 20, 0.8);
        border-radius: 8px;
        padding: 30px;
        display: flex;
        align-items: center;
        gap: 30px;
        border: 2px solid var(--terminal-border);
        transition: all 0.3s;
    }
    
    .status-card.vip {
        border-color: var(--terminal-purple);
        background-color: rgba(255, 0, 255, 0.05);
    }
    
    .status-card.hacker {
        border-color: var(--terminal-red);
        background-color: rgba(255, 0, 0, 0.05);
    }
    
    .status-card.current {
        box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
    }
    
    .status-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(45deg, var(--terminal-green), var(--terminal-cyan));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
    }
    
    .status-card.vip .status-icon {
        background: linear-gradient(45deg, var(--terminal-purple), var(--terminal-cyan));
    }
    
    .status-card.hacker .status-icon {
        background: linear-gradient(45deg, var(--terminal-red), var(--terminal-purple));
    }
    
    .status-info h4 {
        color: var(--terminal-green);
        font-size: 24px;
        margin-bottom: 10px;
    }
    
    .status-card.vip .status-info h4 {
        color: var(--terminal-purple);
    }
    
    .status-card.hacker .status-info h4 {
        color: var(--terminal-red);
    }
    
    .vip-status {
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 12px;
    }
    
    .vip-status.none {
        background-color: #666;
        color: #ccc;
    }
    
    .vip-status.vip {
        background-color: var(--terminal-purple);
        color: white;
    }
    
    .vip-status.hacker {
        background: linear-gradient(45deg, var(--terminal-red), var(--terminal-purple));
        color: white;
    }
    
    .pricing-plans {
        margin: 60px 0;
    }
    
    .pricing-plans h2, .extra-options h2, .comparison-table h2, .faq-section h2 {
        color: var(--terminal-cyan);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .section-description {
        color: #888;
        margin-bottom: 30px;
        font-size: 18px;
    }
    
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }
    
    .plan-card {
        background-color: rgba(20, 20, 20, 0.8);
        border-radius: 12px;
        padding: 30px;
        border: 2px solid var(--terminal-border);
        position: relative;
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .plan-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    
    .plan-card.vip {
        border-color: var(--terminal-purple);
        background-color: rgba(255, 0, 255, 0.05);
    }
    
    .plan-card.hacker {
        border-color: var(--terminal-red);
        background-color: rgba(255, 0, 0, 0.05);
    }
    
    .plan-card.current {
        border-color: var(--terminal-green);
        box-shadow: 0 0 30px rgba(0, 255, 0, 0.2);
    }
    
    .plan-badge {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(45deg, var(--terminal-purple), var(--terminal-cyan));
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 14px;
        z-index: 1;
    }
    
    .plan-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--terminal-border);
    }
    
    .plan-header h3 {
        color: var(--terminal-text);
        font-size: 28px;
        margin-bottom: 15px;
    }
    
    .plan-card.vip .plan-header h3 {
        color: var(--terminal-purple);
    }
    
    .plan-card.hacker .plan-header h3 {
        color: var(--terminal-red);
    }
    
    .plan-price {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .price {
        font-size: 48px;
        font-weight: bold;
        color: var(--terminal-green);
        line-height: 1;
    }
    
    .plan-card.vip .price {
        color: var(--terminal-purple);
    }
    
    .plan-card.hacker .price {
        color: var(--terminal-red);
    }
    
    .period {
        color: #888;
        font-size: 14px;
        margin-top: 5px;
    }
    
    .plan-features {
        flex: 1;
        margin-bottom: 30px;
    }
    
    .plan-features ul {
        list-style: none;
        padding: 0;
    }
    
    .plan-features li {
        padding: 10px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .plan-features li:last-child {
        border-bottom: none;
    }
    
    .plan-features .included {
        color: var(--terminal-green);
    }
    
    .plan-features .excluded {
        color: #666;
        text-decoration: line-through;
    }
    
    .plan-features i.fa-check {
        color: var(--terminal-green);
    }
    
    .plan-features i.fa-times {
        color: var(--terminal-red);
    }
    
    .plan-features code {
        background-color: rgba(0, 255, 0, 0.1);
        padding: 2px 6px;
        border-radius: 3px;
        color: var(--terminal-green);
        font-family: 'Courier New', monospace;
    }
    
    .plan-action {
        text-align: center;
    }
    
    .btn-plan {
        width: 100%;
        padding: 15px;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-plan:not(.current):hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }
    
    .btn-plan.current {
        background-color: var(--terminal-green);
        color: #000;
        cursor: default;
    }
    
    .btn-plan.vip {
        background: linear-gradient(45deg, var(--terminal-purple), var(--terminal-cyan));
        color: white;
    }
    
    .btn-plan.hacker {
        background: linear-gradient(45deg, var(--terminal-red), var(--terminal-purple));
        color: white;
    }
    
    .extra-options {
        margin: 60px 0;
    }
    
    .options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }
    
    .option-card {
        background-color: rgba(30, 30, 30, 0.8);
        border-radius: 8px;
        padding: 25px;
        text-align: center;
        border: 1px solid var(--terminal-border);
        transition: all 0.3s;
    }
    
    .option-card:hover {
        border-color: var(--terminal-cyan);
        transform: translateY(-5px);
    }
    
    .option-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(45deg, var(--terminal-cyan), var(--terminal-green));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 24px;
    }
    
    .option-card h4 {
        color: var(--terminal-cyan);
        margin-bottom: 10px;
        font-size: 18px;
    }
    
    .option-price {
        color: var(--terminal-green);
        font-size: 24px;
        font-weight: bold;
        margin: 15px 0;
    }
    
    .option-desc {
        color: #aaa;
        font-size: 14px;
        margin-bottom: 20px;
        min-height: 60px;
    }
    
    .btn-option {
        display: inline-block;
        background-color: var(--terminal-green);
        color: #000;
        padding: 10px 20px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-option:hover {
        background-color: #00cc00;
        transform: translateY(-2px);
    }
    
    .comparison-table {
        margin: 60px 0;
        overflow-x: auto;
    }
    
    .comparison-table table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .comparison-table th {
        background-color: rgba(0, 0, 0, 0.5);
        padding: 15px;
        text-align: left;
        color: var(--terminal-cyan);
        border: 1px solid var(--terminal-border);
    }
    
    .comparison-table td {
        padding: 15px;
        border: 1px solid var(--terminal-border);
        color: var(--terminal-text);
    }
    
    .comparison-table tr:nth-child(even) {
        background-color: rgba(0, 0, 0, 0.2);
    }
    
    .comparison-table i.fa-check {
        color: var(--terminal-green);
    }
    
    .comparison-table i.fa-times {
        color: var(--terminal-red);
    }
    
    .faq-section {
        margin: 60px 0;
    }
    
    .faq-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }
    
    .faq-item {
        background-color: rgba(30, 30, 30, 0.8);
        padding: 20px;
        border-radius: 8px;
        border: 1px solid var(--terminal-border);
    }
    
    .faq-item h4 {
        color: var(--terminal-cyan);
        margin-bottom: 10px;
        font-size: 18px;
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
        width: 90%;
        max-width: 600px;
        border-radius: 8px;
        border: 2px solid var(--terminal-cyan);
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
        color: var(--terminal-cyan);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
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
        padding: 30px;
    }
    
    .integration-features {
        margin: 20px 0;
    }
    
    .integration-features ul {
        list-style: none;
        padding: 0;
    }
    
    .integration-features li {
        padding: 8px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--terminal-text);
    }
    
    .integration-features i {
        color: var(--terminal-green);
    }
    
    .integration-form {
        margin-top: 30px;
    }
    
    .integration-form .form-group {
        margin-bottom: 20px;
    }
    
    .integration-form label {
        display: block;
        color: var(--terminal-cyan);
        margin-bottom: 8px;
        font-weight: bold;
    }
    
    .integration-form input,
    .integration-form textarea {
        width: 100%;
        padding: 12px;
        background-color: #000;
        border: 1px solid var(--terminal-border);
        color: var(--terminal-text);
        border-radius: 4px;
        font-family: inherit;
    }
    
    .integration-form textarea {
        resize: vertical;
    }
    
    .btn-submit {
        width: 100%;
        background-color: var(--terminal-cyan);
        color: #000;
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
    
    .btn-submit:hover {
        background-color: #00cccc;
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
    
    @media (max-width: 768px) {
        .plans-grid {
            grid-template-columns: 1fr;
        }
        
        .options-grid {
            grid-template-columns: 1fr;
        }
        
        .faq-grid {
            grid-template-columns: 1fr;
        }
        
        .status-card {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        
        .comparison-table {
            font-size: 14px;
        }
    }
    </style>
</body>
</html>