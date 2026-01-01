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

// Проверка VIP статуса
if ($user['vip_level'] === 'none') {
    header("Location: donate.php?redirect=parser");
    exit();
}

// Обработка команд парсера
$output = '';
$scan_results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['command'])) {
        $command = trim($_POST['command']);
        $args = explode(' ', $command);
        $cmd = strtolower($args[0]);
        
        switch ($cmd) {
            case 'scan':
                handleScanCommand($args);
                break;
                
            case 'server':
                if (count($args) > 1 && strtolower($args[1]) === 'list') {
                    handleServerListCommand($args);
                } elseif (count($args) > 1 && strtolower($args[1]) === 'info') {
                    handleServerInfoCommand($args);
                }
                break;
                
            case 'clear':
                $_SESSION['parser_output'] = '';
                break;
                
            default:
                $output = '<div class="error">Неизвестная команда: ' . htmlspecialchars($cmd) . '</div>';
        }
        
        if (!empty($output)) {
            $_SESSION['parser_output'] = (isset($_SESSION['parser_output']) ? $_SESSION['parser_output'] : '') . $output;
        }
    }
}

// Загрузка сохраненного вывода
if (isset($_SESSION['parser_output'])) {
    $output = $_SESSION['parser_output'];
}

// Функции обработки команд
function handleScanCommand($args) {
    global $output, $user, $pdo;
    
    if ($user['vip_level'] === 'none') {
        $output = '<div class="error">Требуется VIP доступ. Используйте команду donate</div>';
        return;
    }
    
    if (count($args) === 1) {
        // Интерактивный режим сканирования
        $output = '
        <div class="scan-interactive">
            <h4>Режим интерактивного сканирования</h4>
            <form id="interactive-scan" class="interactive-form">
                <div class="form-group">
                    <label for="network">Введите сеть (например 114.172.0.0/16):</label>
                    <input type="text" id="network" name="network" placeholder="114.172.0.0/16" required>
                </div>
                <div class="form-group">
                    <label for="max_ips">Введите максимум IP для проверки (Не больше 999):</label>
                    <input type="number" id="max_ips" name="max_ips" min="1" max="999" value="100" required>
                </div>
                <div class="form-group">
                    <label for="ports">Введите диапазон портов (25560-25570):</label>
                    <input type="text" id="ports" name="ports" placeholder="25560-25570" required>
                    <small>ВАЖНО: Диапазон портов не больше 10</small>
                </div>
                <button type="submit" class="btn-scan-start">
                    <i class="fas fa-search"></i> Начать сканирование
                </button>
            </form>
        </div>';
    } else {
        // Прямое сканирование
        $network = $args[1] ?? '';
        $max_ips = $args[2] ?? 100;
        $ports = $args[3] ?? '25560-25570';
        
        if (validateScanParameters($network, $max_ips, $ports)) {
            $output = startScanning($network, $max_ips, $ports);
            
            // Сохраняем результаты в базу данных
            saveScanResults($network, $max_ips, $ports);
        } else {
            $output = '<div class="error">Неверные параметры сканирования</div>';
        }
    }
}

function handleServerListCommand($args) {
    global $output, $user, $pdo;
    
    if ($user['vip_level'] !== 'hacker') {
        $output = '<div class="error">Требуется HACKER доступ. Используйте команду donate hacker</div>';
        return;
    }
    
    $start = isset($args[2]) ? intval($args[2]) : 1;
    $end = isset($args[3]) ? intval($args[3]) : 100;
    
    if ($end - $start > 100) {
        $output = '<div class="error">Максимальный диапазон: 100 строк</div>';
        return;
    }
    
    // Получаем сервера из базы данных
    $limit = $end - $start + 1;
    $offset = $start - 1;
    
    $stmt = $pdo->prepare("SELECT * FROM minecraft_servers ORDER BY last_scanned DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $servers = $stmt->fetchAll();
    
    if (count($servers) === 0) {
        $output = '<div class="info">Серверы не найдены. Сначала выполните сканирование.</div>';
        return;
    }
    
    $table = '
    <div class="server-list">
        <h4>Список серверов (строки ' . $start . '-' . $end . '):</h4>
        <table class="servers-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>IP:Port</th>
                    <th>Название</th>
                    <th>Версия</th>
                    <th>Игроки</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($servers as $server) {
        $table .= '
            <tr>
                <td>' . $server['id'] . '</td>
                <td><code>' . $server['ip_address'] . ':' . $server['port'] . '</code></td>
                <td>' . ($server['name'] ? htmlspecialchars($server['name']) : '<em>Неизвестно</em>') . '</td>
                <td>' . ($server['version'] ? htmlspecialchars($server['version']) : '-') . '</td>
                <td>' . $server['players_online'] . '/' . $server['max_players'] . '</td>
                <td>
                    <button onclick="scanServer(' . $server['id'] . ')" class="btn-scan-server">
                        <i class="fas fa-search"></i> Сканировать
                    </button>
                </td>
            </tr>';
    }
    
    $table .= '
            </tbody>
        </table>
        <div class="list-info">
            Показано ' . count($servers) . ' серверов. Используйте команду <code>server info [id]</code> для подробной информации
        </div>
    </div>';
    
    $output = $table;
}

function handleServerInfoCommand($args) {
    global $output, $pdo;
    
    $server_id = isset($args[2]) ? intval($args[2]) : 0;
    
    if ($server_id <= 0) {
        $output = '<div class="error">Использование: server info [id_сервера]</div>';
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM minecraft_servers WHERE id = ?");
    $stmt->execute([$server_id]);
    $server = $stmt->fetch();
    
    if (!$server) {
        $output = '<div class="error">Сервер с ID ' . $server_id . ' не найден</div>';
        return;
    }
    
    $output = '
    <div class="server-details">
        <h4>Детальная информация о сервере #' . $server['id'] . '</h4>
        <div class="server-info-grid">
            <div class="info-item">
                <span class="info-label">IP Адрес:</span>
                <span class="info-value">' . $server['ip_address'] . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Порт:</span>
                <span class="info-value">' . $server['port'] . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Название:</span>
                <span class="info-value">' . ($server['name'] ? htmlspecialchars($server['name']) : '<em>Неизвестно</em>') . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Версия:</span>
                <span class="info-value">' . ($server['version'] ? htmlspecialchars($server['version']) : '-') . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Игроки онлайн:</span>
                <span class="info-value">' . $server['players_online'] . '/' . $server['max_players'] . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Последнее сканирование:</span>
                <span class="info-value">' . date('d.m.Y H:i:s', strtotime($server['last_scanned'])) . '</span>
            </div>
        </div>
        <div class="server-actions">
            <button onclick="rescanServer(' . $server['id'] . ')" class="btn-rescan">
                <i class="fas fa-sync"></i> Пересканировать
            </button>
            <button onclick="testConnection(' . $server['id'] . ')" class="btn-test">
                <i class="fas fa-plug"></i> Проверить соединение
            </button>
            <button onclick="copyServerInfo(' . $server['id'] . ')" class="btn-copy">
                <i class="far fa-copy"></i> Копировать информацию
            </button>
        </div>
    </div>';
}

function validateScanParameters($network, $max_ips, $ports) {
    // Проверка формата сети (например, 192.168.1.0/24)
    if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $network)) {
        return false;
    }
    
    // Проверка максимального количества IP
    $max_ips = intval($max_ips);
    if ($max_ips < 1 || $max_ips > 999) {
        return false;
    }
    
    // Проверка диапазона портов
    if (!preg_match('/^\d{1,5}\-\d{1,5}$/', $ports)) {
        return false;
    }
    
    list($port_start, $port_end) = explode('-', $ports);
    $port_start = intval($port_start);
    $port_end = intval($port_end);
    
    if ($port_end - $port_start > 10 || $port_start < 1 || $port_end > 65535) {
        return false;
    }
    
    return true;
}

function startScanning($network, $max_ips, $ports) {
    global $user;
    
    list($port_start, $port_end) = explode('-', $ports);
    
    $output = '
    <div class="scan-progress">
        <h4>🚀 Запуск сканирования...</h4>
        <div class="progress-container">
            <div class="progress-bar" id="scan-progress-bar">
                <div class="progress-fill" style="width: 0%"></div>
            </div>
            <div class="progress-text" id="scan-progress-text">0%</div>
        </div>
        <div class="scan-details">
            <p><strong>Сеть:</strong> ' . htmlspecialchars($network) . '</p>
            <p><strong>Максимум IP:</strong> ' . $max_ips . '</p>
            <p><strong>Диапазон портов:</strong> ' . $ports . '</p>
            <p><strong>Статус:</strong> <span id="scan-status">Подготовка...</span></p>
        </div>
    </div>
    
    <div id="scan-results" style="display: none;">
        <h4>📊 Результаты сканирования</h4>
        <div class="results-container" id="results-container"></div>
    </div>
    
    <script>
    startRealTimeScan(
        "' . htmlspecialchars($network) . '",
        ' . $max_ips . ',
        ' . $port_start . ',
        ' . $port_end . ',
        "' . $user['vip_level'] . '"
    );
    </script>';
    
    return $output;
}

function saveScanResults($network, $max_ips, $ports) {
    global $pdo, $user_id;
    
    // В реальном приложении здесь сохранялись бы реальные результаты
    // Для демо генерируем случайные сервера
    
    list($port_start, $port_end) = explode('-', $ports);
    
    // Генерируем несколько случайных серверов для демонстрации
    $servers_to_generate = rand(5, 20);
    
    for ($i = 0; $i < $servers_to_generate; $i++) {
        $ip_parts = explode('.', explode('/', $network)[0]);
        $ip_parts[3] = rand(1, 254);
        $ip = implode('.', $ip_parts);
        $port = rand($port_start, $port_end);
        
        // Проверяем, существует ли уже такой сервер
        $check_stmt = $pdo->prepare("SELECT id FROM minecraft_servers WHERE ip_address = ? AND port = ?");
        $check_stmt->execute([$ip, $port]);
        
        if ($check_stmt->rowCount() === 0) {
            $server_names = [
                'Minecraft Survival', 'Creative Build', 'SkyBlock World', 
                'PvP Arena', 'Hardcore Survival', 'Towny Economy',
                'MiniGames Hub', 'RolePlay City', 'Anarchy Chaos',
                'Technical Server', 'Redstone Lab', 'Adventure Map'
            ];
            
            $versions = ['1.20.4', '1.20.1', '1.19.4', '1.18.2', '1.17.1', '1.16.5'];
            
            $stmt = $pdo->prepare("
                INSERT INTO minecraft_servers 
                (ip_address, port, name, version, players_online, max_players, last_scanned) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $ip,
                $port,
                $server_names[array_rand($server_names)],
                $versions[array_rand($versions)],
                rand(0, 100),
                rand(20, 200)
            ]);
        }
    }
    
    // Логируем сканирование
    $stmt = $pdo->prepare("
        INSERT INTO scan_logs (user_id, network, max_ips, ports, servers_found, scanned_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $network,
        $max_ips,
        $ports,
        $servers_to_generate
    ]);
}

// Создаем таблицу для логов сканирования, если она не существует
$pdo->exec("
    CREATE TABLE IF NOT EXISTS scan_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        network VARCHAR(50) NOT NULL,
        max_ips INT NOT NULL,
        ports VARCHAR(20) NOT NULL,
        servers_found INT NOT NULL,
        scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Парсер серверов | HackCraft</title>
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
                <li><a href="server-parser.php" class="nav-link active"><i class="fas fa-server"></i> Парсер серверов</a></li>
                <li><a href="donate.php" class="nav-link"><i class="fas fa-gem"></i> Донат</a></li>
            </ul>
            <div class="user-info">
                <span class="vip-status <?php echo $user['vip_level']; ?>">
                    <?php echo strtoupper($user['vip_level']); ?>
                </span>
                <a href="logout.php" class="logout-btn" title="Выйти"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Заголовок -->
        <div class="page-header">
            <h1><i class="fas fa-server"></i> Парсер Minecraft серверов</h1>
            <p class="subtitle">Находите и анализируйте Minecraft серверы в указанных сетях</p>
            
            <?php if ($user['vip_level'] === 'vip'): ?>
                <div class="vip-info">
                    <i class="fas fa-crown"></i>
                    <span>VIP доступ: До 999 IP за сканирование, диапазон портов до 10</span>
                </div>
            <?php elseif ($user['vip_level'] === 'hacker'): ?>
                <div class="hacker-info">
                    <i class="fas fa-user-ninja"></i>
                    <span>HACKER доступ: Неограниченное сканирование + доступ к базе данных серверов</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Основной интерфейс парсера -->
        <div class="parser-interface">
            <div class="parser-terminal">
                <div class="terminal-header">
                    <div class="terminal-buttons">
                        <span class="close"></span>
                        <span class="minimize"></span>
                        <span class="maximize"></span>
                    </div>
                    <span class="terminal-title">
                        <i class="fas fa-search"></i> Minecraft Server Parser v3.0
                        <span class="terminal-status">VIP Mode: <?php echo strtoupper($user['vip_level']); ?></span>
                    </span>
                </div>
                
                <div class="terminal-body" id="parser-output">
                    <!-- Начальное сообщение -->
                    <div class="parser-welcome">
                        <pre class="ascii-art">
  _____ _____ _____ _____ _____ _____ _____ 
 |     |   __|  _  |     |   __|     |  _  |
 |   --|   __|     | | | |   __|  |  |     |
 |_____|_____|__|__|_|_|_|_____|_____|__|__|
                        </pre>
                        <p class="typewriter">Minecraft Server Parser готов к работе</p>
                        <p>Ваш статус: <strong class="<?php echo $user['vip_level']; ?>-text"><?php echo strtoupper($user['vip_level']); ?></strong></p>
                        
                        <div class="available-commands">
                            <h4>Доступные команды:</h4>
                            <div class="commands-grid">
                                <div class="command-card">
                                    <code>scan</code>
                                    <p>Интерактивное сканирование сети</p>
                                </div>
                                <div class="command-card">
                                    <code>scan [сеть] [макс_IP] [порты]</code>
                                    <p>Прямое сканирование</p>
                                </div>
                                <?php if ($user['vip_level'] === 'hacker'): ?>
                                    <div class="command-card hacker">
                                        <code>server list [начало] [конец]</code>
                                        <p>Показать серверы из БД (HACKER)</p>
                                    </div>
                                    <div class="command-card hacker">
                                        <code>server info [id]</code>
                                        <p>Информация о сервере (HACKER)</p>
                                    </div>
                                <?php endif; ?>
                                <div class="command-card">
                                    <code>clear</code>
                                    <p>Очистить терминал</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Вывод команд -->
                    <?php echo $output; ?>
                </div>
                
                <!-- Ввод команды -->
                <div class="terminal-input-container">
                    <form method="POST" class="parser-form">
                        <div class="input-line">
                            <span class="prompt">
                                <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                                <span class="host">@parser</span>
                                <span class="path">:~$</span>
                            </span>
                            <input type="text" name="command" class="terminal-input" 
                                   placeholder="Введите команду..." autocomplete="off" autofocus
                                   id="parser-command">
                            <button type="submit" class="enter-btn">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                        <div class="input-hints">
                            <span id="command-hint">Используйте Tab для автодополнения</span>
                            <span class="hint-keys">
                                <kbd>↑/↓</kbd> история • Пример: <code>scan 192.168.1.0/24 100 25560-25570</code>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Боковая панель с информацией -->
            <div class="parser-sidebar">
                <div class="sidebar-section">
                    <h3><i class="fas fa-info-circle"></i> Информация</h3>
                    <div class="info-box">
                        <p><strong>Ваш статус:</strong> <span class="<?php echo $user['vip_level']; ?>-badge"><?php echo strtoupper($user['vip_level']); ?></span></p>
                        <p><strong>Доступные IP:</strong> <?php echo $user['vip_level'] === 'hacker' ? 'Неограниченно' : 'До 999'; ?></p>
                        <p><strong>Диапазон портов:</strong> До 10 портов</p>
                        <p><strong>Сканирований сегодня:</strong> <span id="scans-today">0</span></p>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <h3><i class="fas fa-history"></i> История сканирований</h3>
                    <div class="scan-history" id="scan-history">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT network, servers_found, scanned_at 
                            FROM scan_logs 
                            WHERE user_id = ? 
                            ORDER BY scanned_at DESC 
                            LIMIT 5
                        ");
                        $stmt->execute([$user_id]);
                        $history = $stmt->fetchAll();
                        
                        if (count($history) > 0):
                            foreach ($history as $scan):
                        ?>
                            <div class="history-item">
                                <div class="history-network"><?php echo htmlspecialchars($scan['network']); ?></div>
                                <div class="history-info">
                                    <span class="servers-found"><?php echo $scan['servers_found']; ?> серверов</span>
                                    <span class="scan-time"><?php echo date('H:i', strtotime($scan['scanned_at'])); ?></span>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <p class="no-history">История сканирований пуста</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <h3><i class="fas fa-bolt"></i> Быстрый доступ</h3>
                    <div class="quick-actions">
                        <button class="quick-action" onclick="insertCommand('scan')">
                            <i class="fas fa-search"></i> Интерактивное сканирование
                        </button>
                        <?php if ($user['vip_level'] === 'hacker'): ?>
                            <button class="quick-action hacker" onclick="insertCommand('server list 1 50')">
                                <i class="fas fa-list"></i> Список серверов
                            </button>
                        <?php endif; ?>
                        <button class="quick-action" onclick="insertCommand('clear')">
                            <i class="fas fa-broom"></i> Очистить терминал
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Быстрое сканирование -->
        <div class="quick-scan">
            <h3><i class="fas fa-rocket"></i> Быстрое сканирование</h3>
            <p>Выберите популярную сеть для быстрого старта:</p>
            
            <div class="quick-scan-grid">
                <div class="scan-preset" onclick="quickScan('114.172.0.0/16', 100, '25560-25570')">
                    <div class="preset-icon">
                        <i class="fas fa-globe-asia"></i>
                    </div>
                    <h4>Азиатские серверы</h4>
                    <p>114.172.0.0/16</p>
                    <span class="preset-info">100 IP, порты 25560-25570</span>
                </div>
                
                <div class="scan-preset" onclick="quickScan('192.168.0.0/24', 50, '25565-25575')">
                    <div class="preset-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <h4>Локальная сеть</h4>
                    <p>192.168.0.0/24</p>
                    <span class="preset-info">50 IP, порты 25565-25575</span>
                </div>
                
                <div class="scan-preset" onclick="quickScan('10.0.0.0/8', 200, '25560-25565')">
                    <div class="preset-icon">
                        <i class="fas fa-server"></i>
                    </div>
                    <h4>Корпоративные</h4>
                    <p>10.0.0.0/8</p>
                    <span class="preset-info">200 IP, порты 25560-25565</span>
                </div>
                
                <div class="scan-preset" onclick="quickScan('172.16.0.0/12', 150, '25570-25580')">
                    <div class="preset-icon">
                        <i class="fas fa-cloud"></i>
                    </div>
                    <h4>Облачные серверы</h4>
                    <p>172.16.0.0/12</p>
                    <span class="preset-info">150 IP, порты 25570-25580</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно результатов -->
    <div id="results-modal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3><i class="fas fa-list-alt"></i> Детальные результаты сканирования</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="results-content" id="detailed-results"></div>
                <div class="modal-actions">
                    <button onclick="exportResults('json')" class="btn-export">
                        <i class="fas fa-file-code"></i> Экспорт в JSON
                    </button>
                    <button onclick="exportResults('csv')" class="btn-export">
                        <i class="fas fa-file-csv"></i> Экспорт в CSV
                    </button>
                    <button onclick="copyAllResults()" class="btn-copy">
                        <i class="far fa-copy"></i> Копировать все
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // История команд парсера
    let parserHistory = JSON.parse(localStorage.getItem('parserHistory') || '[]');
    let parserHistoryIndex = parserHistory.length;
    
    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        const commandInput = document.getElementById('parser-command');
        
        // Автодополнение
        const commands = ['scan', 'server list', 'server info', 'clear'];
        commandInput.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                const currentValue = this.value.trim();
                
                for (let cmd of commands) {
                    if (cmd.startsWith(currentValue)) {
                        this.value = cmd + ' ';
                        break;
                    }
                }
            }
            
            // История команд
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (parserHistoryIndex > 0) {
                    parserHistoryIndex--;
                    commandInput.value = parserHistory[parserHistoryIndex] || '';
                }
            }
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (parserHistoryIndex < parserHistory.length - 1) {
                    parserHistoryIndex++;
                    commandInput.value = parserHistory[parserHistoryIndex] || '';
                } else {
                    parserHistoryIndex = parserHistory.length;
                    commandInput.value = '';
                }
            }
        });
        
        // Сохранение истории при отправке
        document.querySelector('.parser-form').addEventListener('submit', function() {
            const command = commandInput.value.trim();
            if (command) {
                parserHistory.push(command);
                if (parserHistory.length > 50) parserHistory.shift();
                localStorage.setItem('parserHistory', JSON.stringify(parserHistory));
                parserHistoryIndex = parserHistory.length;
            }
        });
        
        // Загружаем статистику сканирований
        updateScanStats();
    });
    
    // Вставка команды в поле ввода
    function insertCommand(command) {
        const input = document.getElementById('parser-command');
        input.value = command;
        input.focus();
    }
    
    // Быстрое сканирование
    function quickScan(network, maxIps, ports) {
        const command = `scan ${network} ${maxIps} ${ports}`;
        insertCommand(command);
        document.querySelector('.parser-form').submit();
    }
    
    // Сканирование конкретного сервера
    function scanServer(serverId) {
        fetch('api/scan_server.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ server_id: serverId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Сервер успешно отсканирован!', 'success');
                // Обновляем информацию о сервере
                if (data.server_info) {
                    showServerInfo(data.server_info);
                }
            } else {
                showNotification('Ошибка сканирования: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Ошибка соединения', 'error');
        });
    }
    
    // Показать информацию о сервере
    function showServerInfo(serverInfo) {
        const modal = document.getElementById('results-modal');
        const content = document.getElementById('detailed-results');
        
        content.innerHTML = `
            <div class="server-full-info">
                <h4>${serverInfo.name || 'Неизвестный сервер'}</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <label>IP:Port</label>
                        <value>${serverInfo.ip}:${serverInfo.port}</value>
                    </div>
                    <div class="info-item">
                        <label>Версия</label>
                        <value>${serverInfo.version || 'Неизвестно'}</value>
                    </div>
                    <div class="info-item">
                        <label>Игроки онлайн</label>
                        <value>${serverInfo.players_online}/${serverInfo.max_players}</value>
                    </div>
                    <div class="info-item">
                        <label>Пинг</label>
                        <value>${serverInfo.ping || '?'} мс</value>
                    </div>
                    <div class="info-item">
                        <label>Модпак</label>
                        <value>${serverInfo.modpack || 'Ванилла'}</value>
                    </div>
                    <div class="info-item">
                        <label>Защита</label>
                        <value>${serverInfo.protection || 'Стандартная'}</value>
                    </div>
                </div>
                <div class="server-description">
                    <h5>Описание:</h5>
                    <p>${serverInfo.description || 'Описание отсутствует'}</p>
                </div>
            </div>
        `;
        
        modal.style.display = 'block';
    }
    
    // Пересканирование сервера
    function rescanServer(serverId) {
        showNotification('Пересканирование запущено...', 'info');
        scanServer(serverId);
    }
    
    // Проверка соединения
    function testConnection(serverId) {
        fetch('api/test_connection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ server_id: serverId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(`Соединение успешно! Пинг: ${data.ping}мс`, 'success');
            } else {
                showNotification('Сервер недоступен', 'error');
            }
        });
    }
    
    // Копирование информации о сервере
    function copyServerInfo(serverId) {
        fetch('api/get_server_info.php?id=' + serverId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const info = `IP: ${data.server.ip}:${data.server.port}
Название: ${data.server.name || 'Неизвестно'}
Версия: ${data.server.version || 'Неизвестно'}
Игроки: ${data.server.players_online}/${data.server.max_players}
`;
                    
                    navigator.clipboard.writeText(info).then(() => {
                        showNotification('Информация скопирована в буфер обмена', 'success');
                    });
                }
            });
    }
    
    // Экспорт результатов
    function exportResults(format) {
        const content = document.getElementById('detailed-results').innerText;
        
        if (format === 'json') {
            const data = {
                servers: [],
                exported_at: new Date().toISOString()
            };
            
            // Здесь должна быть реальная логика экспорта
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'scan_results_' + Date.now() + '.json';
            a.click();
        } else if (format === 'csv') {
            const csv = 'IP,Port,Name,Version,Players\n192.168.1.1,25565,Test Server,1.20.4,10/50\n';
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'scan_results_' + Date.now() + '.csv';
            a.click();
        }
        
        showNotification('Результаты экспортированы в ' + format.toUpperCase(), 'success');
    }
    
    // Копировать все результаты
    function copyAllResults() {
        const content = document.getElementById('detailed-results').innerText;
        navigator.clipboard.writeText(content).then(() => {
            showNotification('Все результаты скопированы', 'success');
        });
    }
    
    // Обновление статистики сканирований
    function updateScanStats() {
        fetch('api/get_scan_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.scans_today !== undefined) {
                    document.getElementById('scans-today').textContent = data.scans_today;
                }
            });
    }
    
    // Реальное сканирование (симуляция)
    function startRealTimeScan(network, maxIps, portStart, portEnd, vipLevel) {
        const progressBar = document.getElementById('scan-progress-bar');
        const progressFill = progressBar.querySelector('.progress-fill');
        const progressText = document.getElementById('scan-progress-text');
        const scanStatus = document.getElementById('scan-status');
        const resultsContainer = document.getElementById('results-container');
        const scanResults = document.getElementById('scan-results');
        
        let progress = 0;
        const totalSteps = 10;
        const stepTime = 500;
        
        // Симуляция сканирования
        const simulateScan = () => {
            if (progress >= 100) {
                scanStatus.textContent = 'Сканирование завершено!';
                
                // Показываем результаты
                scanResults.style.display = 'block';
                
                // Генерируем случайные результаты
                const serversFound = Math.floor(Math.random() * 20) + 5;
                let resultsHTML = `
                    <div class="scan-summary">
                        <div class="summary-item">
                            <span class="summary-label">Найдено серверов:</span>
                            <span class="summary-value success">${serversFound}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Проверено IP:</span>
                            <span class="summary-value">${maxIps}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Диапазон портов:</span>
                            <span class="summary-value">${portStart}-${portEnd}</span>
                        </div>
                    </div>
                    <div class="servers-found">
                        <h5>Обнаруженные серверы:</h5>
                `;
                
                for (let i = 1; i <= Math.min(serversFound, 10); i++) {
                    const ipParts = network.split('.');
                    ipParts[3] = Math.floor(Math.random() * 254) + 1;
                    const ip = ipParts.join('.');
                    const port = Math.floor(Math.random() * (portEnd - portStart + 1)) + portStart;
                    const players = Math.floor(Math.random() * 100);
                    const maxPlayers = Math.floor(Math.random() * 100) + 50;
                    
                    resultsHTML += `
                        <div class="server-item">
                            <div class="server-header">
                                <span class="server-ip">${ip}:${port}</span>
                                <span class="server-players">${players}/${maxPlayers} игроков</span>
                            </div>
                            <div class="server-info">
                                <span class="server-version">Minecraft 1.${Math.floor(Math.random() * 20) + 1}.${Math.floor(Math.random() * 5)}</span>
                                <button onclick="saveServer('${ip}', ${port})" class="btn-save-server">
                                    <i class="far fa-save"></i> Сохранить
                                </button>
                            </div>
                        </div>
                    `;
                }
                
                if (serversFound > 10) {
                    resultsHTML += `<p class="more-servers">... и еще ${serversFound - 10} серверов</p>`;
                }
                
                resultsHTML += `
                        <div class="scan-actions">
                            <button onclick="showDetailedResults()" class="btn-view-details">
                                <i class="fas fa-chart-bar"></i> Подробная статистика
                            </button>
                            <button onclick="exportScanResults()" class="btn-export-scan">
                                <i class="fas fa-download"></i> Экспорт результатов
                            </button>
                        </div>
                    </div>
                `;
                
                resultsContainer.innerHTML = resultsHTML;
                
                // Прокручиваем к результатам
                scanResults.scrollIntoView({ behavior: 'smooth' });
                
                return;
            }
            
            progress += 100 / totalSteps;
            progressFill.style.width = progress + '%';
            progressText.textContent = Math.round(progress) + '%';
            
            // Обновляем статус
            const statuses = [
                'Сканирование сети...',
                'Генерация IP адресов...',
                'Проверка портов...',
                'Отправка запросов...',
                'Обработка ответов...',
                'Анализ данных...',
                'Фильтрация результатов...',
                'Сбор статистики...',
                'Формирование отчета...',
                'Завершение...'
            ];
            
            const statusIndex = Math.floor(progress / 10);
            if (statusIndex < statuses.length) {
                scanStatus.textContent = statuses[statusIndex];
            }
            
            setTimeout(simulateScan, stepTime);
        };
        
        simulateScan();
    }
    
    // Сохранение сервера
    function saveServer(ip, port) {
        fetch('api/save_server.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ip: ip, port: port })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Сервер сохранен в базу данных!', 'success');
            } else {
                showNotification('Ошибка сохранения: ' + data.error, 'error');
            }
        });
    }
    
    // Показать детальные результаты
    function showDetailedResults() {
        const modal = document.getElementById('results-modal');
        const content = document.getElementById('detailed-results');
        
        content.innerHTML = `
            <div class="detailed-scan-results">
                <h4>Детальная статистика сканирования</h4>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">24</div>
                        <div class="stat-label">Серверов найдено</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">87%</div>
                        <div class="stat-label">Доступность</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">1.2с</div>
                        <div class="stat-label">Средний пинг</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">1.20.4</div>
                        <div class="stat-label">Популярная версия</div>
                    </div>
                </div>
                <div class="chart-container">
                    <h5>Распределение по версиям:</h5>
                    <div class="chart">
                        <div class="chart-bar" style="width: 40%">1.20.4 (40%)</div>
                        <div class="chart-bar" style="width: 25%">1.19.2 (25%)</div>
                        <div class="chart-bar" style="width: 15%">1.18.1 (15%)</div>
                        <div class="chart-bar" style="width: 10%">1.17.1 (10%)</div>
                        <div class="chart-bar" style="width: 10%">Другие (10%)</div>
                    </div>
                </div>
            </div>
        `;
        
        modal.style.display = 'block';
    }
    
    // Экспорт результатов сканирования
    function exportScanResults() {
        exportResults('json');
    }
    
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
    
    // Закрытие модальных окон
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
    </script>
    
    <style>
    /* Стили для парсера серверов */
    .vip-info, .hacker-info {
        background-color: rgba(255, 0, 255, 0.1);
        border-left: 4px solid var(--terminal-purple);
        padding: 15px;
        border-radius: 4px;
        margin: 20px 0;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .hacker-info {
        background-color: rgba(255, 0, 0, 0.1);
        border-left-color: var(--terminal-red);
    }
    
    .vip-info i, .hacker-info i {
        font-size: 24px;
        color: var(--terminal-purple);
    }
    
    .hacker-info i {
        color: var(--terminal-red);
    }
    
    .parser-interface {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin: 30px 0;
    }
    
    @media (max-width: 1024px) {
        .parser-interface {
            grid-template-columns: 1fr;
        }
    }
    
    .parser-terminal {
        background-color: var(--terminal-bg);
        border-radius: 8px;
        border: 2px solid var(--terminal-cyan);
        box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
        overflow: hidden;
    }
    
    .parser-welcome {
        padding: 20px;
        text-align: center;
    }
    
    .parser-welcome .ascii-art {
        color: var(--terminal-cyan);
        font-size: 12px;
        line-height: 1.2;
        margin: 20px 0;
    }
    
    .vip-text {
        color: var(--terminal-purple);
        font-weight: bold;
    }
    
    .hacker-text {
        color: var(--terminal-red);
        font-weight: bold;
    }
    
    .available-commands {
        margin-top: 30px;
        text-align: left;
    }
    
    .available-commands h4 {
        color: var(--terminal-green);
        margin-bottom: 15px;
    }
    
    .commands-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .command-card {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 8px;
        border: 1px solid var(--terminal-border);
        transition: all 0.3s;
    }
    
    .command-card:hover {
        border-color: var(--terminal-green);
        transform: translateY(-3px);
    }
    
    .command-card.hacker {
        border-color: var(--terminal-red);
        background-color: rgba(255, 0, 0, 0.05);
    }
    
    .command-card.hacker:hover {
        border-color: var(--terminal-red);
    }
    
    .command-card code {
        background-color: rgba(0, 255, 0, 0.1);
        padding: 2px 8px;
        border-radius: 4px;
        color: var(--terminal-green);
        font-family: 'Courier New', monospace;
        display: block;
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    .command-card.hacker code {
        background-color: rgba(255, 0, 0, 0.1);
        color: var(--terminal-red);
    }
    
    .command-card p {
        color: #aaa;
        font-size: 13px;
        margin: 0;
    }
    
    .parser-sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .sidebar-section {
        background-color: rgba(20, 20, 20, 0.8);
        border-radius: 8px;
        padding: 20px;
        border: 1px solid var(--terminal-border);
    }
    
    .sidebar-section h3 {
        color: var(--terminal-cyan);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
    }
    
    .info-box {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 4px;
    }
    
    .info-box p {
        margin: 8px 0;
        display: flex;
        justify-content: space-between;
    }
    
    .vip-badge {
        background-color: var(--terminal-purple);
        color: white;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .hacker-badge {
        background: linear-gradient(45deg, var(--terminal-red), var(--terminal-purple));
        color: white;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .scan-history {
        max-height: 200px;
        overflow-y: auto;
    }
    
    .history-item {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 8px;
        border-left: 3px solid var(--terminal-green);
    }
    
    .history-network {
        color: var(--terminal-cyan);
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .history-info {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
    }
    
    .servers-found {
        color: var(--terminal-green);
    }
    
    .scan-time {
        color: #888;
    }
    
    .no-history {
        color: #666;
        text-align: center;
        font-style: italic;
        padding: 20px;
    }
    
    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .quick-action {
        background-color: rgba(0, 255, 0, 0.1);
        border: 1px solid var(--terminal-green);
        color: var(--terminal-green);
        padding: 12px;
        border-radius: 4px;
        cursor: pointer;
        text-align: left;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .quick-action:hover {
        background-color: rgba(0, 255, 0, 0.2);
        transform: translateY(-2px);
    }
    
    .quick-action.hacker {
        background-color: rgba(255, 0, 0, 0.1);
        border-color: var(--terminal-red);
        color: var(--terminal-red);
    }
    
    .quick-action.hacker:hover {
        background-color: rgba(255, 0, 0, 0.2);
    }
    
    /* Стили для сканирования */
    .scan-interactive {
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
        margin: 20px 0;
        border: 1px solid var(--terminal-border);
    }
    
    .interactive-form .form-group {
        margin-bottom: 20px;
    }
    
    .interactive-form label {
        display: block;
        color: var(--terminal-cyan);
        margin-bottom: 8px;
        font-weight: bold;
    }
    
    .interactive-form input {
        width: 100%;
        padding: 10px;
        background-color: #000;
        border: 1px solid var(--terminal-border);
        color: var(--terminal-text);
        border-radius: 4px;
        font-family: 'Courier New', monospace;
    }
    
    .interactive-form small {
        color: #888;
        font-size: 12px;
        display: block;
        margin-top: 5px;
    }
    
    .btn-scan-start {
        background-color: var(--terminal-green);
        color: #000;
        border: none;
        padding: 12px 30px;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
    }
    
    .btn-scan-start:hover {
        background-color: #00cc00;
        transform: translateY(-2px);
    }
    
    .scan-progress {
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
        margin: 20px 0;
        border: 1px solid var(--terminal-cyan);
    }
    
    .progress-container {
        position: relative;
        height: 30px;
        background-color: #000;
        border-radius: 15px;
        margin: 20px 0;
        overflow: hidden;
        border: 1px solid var(--terminal-border);
    }
    
    .progress-bar {
        height: 100%;
        width: 100%;
        position: relative;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--terminal-green), var(--terminal-cyan));
        border-radius: 15px;
        transition: width 0.5s ease;
    }
    
    .progress-text {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        text-shadow: 0 0 5px #000;
    }
    
    .scan-details {
        margin-top: 20px;
        padding: 15px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 4px;
    }
    
    .scan-details p {
        margin: 8px 0;
    }
    
    /* Результаты сканирования */
    .scan-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
    }
    
    .summary-item {
        text-align: center;
    }
    
    .summary-label {
        display: block;
        color: #888;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .summary-value {
        display: block;
        color: var(--terminal-green);
        font-size: 24px;
        font-weight: bold;
    }
    
    .summary-value.success {
        color: var(--terminal-green);
    }
    
    .servers-found {
        margin-top: 20px;
    }
    
    .server-item {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 4px solid var(--terminal-cyan);
    }
    
    .server-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .server-ip {
        color: var(--terminal-cyan);
        font-weight: bold;
        font-family: 'Courier New', monospace;
    }
    
    .server-players {
        color: var(--terminal-yellow);
        font-size: 14px;
    }
    
    .server-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .server-version {
        color: #aaa;
        font-size: 14px;
    }
    
    .btn-save-server {
        background-color: var(--terminal-purple);
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .more-servers {
        text-align: center;
        color: #888;
        font-style: italic;
        margin: 15px 0;
    }
    
    .scan-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .btn-view-details, .btn-export-scan {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-view-details {
        background-color: var(--terminal-cyan);
        color: #000;
    }
    
    .btn-export-scan {
        background-color: var(--terminal-purple);
        color: white;
    }
    
    /* Список серверов */
    .servers-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .servers-table th {
        background-color: rgba(0, 0, 0, 0.5);
        padding: 12px;
        text-align: left;
        color: var(--terminal-cyan);
        border: 1px solid var(--terminal-border);
    }
    
    .servers-table td {
        padding: 12px;
        border: 1px solid var(--terminal-border);
    }
    
    .servers-table tr:nth-child(even) {
        background-color: rgba(0, 0, 0, 0.2);
    }
    
    .btn-scan-server {
        background-color: var(--terminal-green);
        color: #000;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .list-info {
        color: #888;
        font-size: 14px;
        margin-top: 10px;
    }
    
    /* Детали сервера */
    .server-details {
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
        margin: 20px 0;
        border: 1px solid var(--terminal-border);
    }
    
    .server-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        color: #888;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .info-value {
        color: var(--terminal-green);
        font-weight: bold;
        font-size: 16px;
    }
    
    .server-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .btn-rescan, .btn-test, .btn-copy {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-rescan {
        background-color: var(--terminal-green);
        color: #000;
    }
    
    .btn-test {
        background-color: var(--terminal-cyan);
        color: #000;
    }
    
    .btn-copy {
        background-color: var(--terminal-yellow);
        color: #000;
    }
    
    /* Быстрое сканирование */
    .quick-scan {
        margin: 40px 0;
        padding: 30px;
        background-color: rgba(20, 20, 20, 0.8);
        border-radius: 8px;
        border: 2px solid var(--terminal-purple);
    }
    
    .quick-scan h3 {
        color: var(--terminal-purple);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .quick-scan-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .scan-preset {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 25px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid var(--terminal-border);
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .scan-preset:hover {
        border-color: var(--terminal-purple);
        transform: translateY(-5px);
        background-color: rgba(255, 0, 255, 0.05);
    }
    
    .preset-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(45deg, var(--terminal-purple), var(--terminal-cyan));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 24px;
    }
    
    .scan-preset h4 {
        color: var(--terminal-cyan);
        margin-bottom: 10px;
    }
    
    .scan-preset p {
        color: var(--terminal-green);
        font-family: 'Courier New', monospace;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .preset-info {
        color: #888;
        font-size: 12px;
    }
    
    /* Модальное окно */
    .modal-content.large {
        max-width: 900px;
    }
    
    .server-full-info {
        padding: 20px;
    }
    
    .server-full-info h4 {
        color: var(--terminal-cyan);
        margin-bottom: 20px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-item label {
        color: #888;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .info-item value {
        color: var(--terminal-green);
        font-weight: bold;
        font-size: 16px;
    }
    
    .server-description {
        margin-top: 20px;
        padding: 15px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 4px;
    }
    
    .server-description h5 {
        color: var(--terminal-cyan);
        margin-bottom: 10px;
    }
    
    .modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .btn-export {
        flex: 1;
        padding: 10px;
        background-color: var(--terminal-purple);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-copy {
        background-color: var(--terminal-yellow);
        color: #000;
    }
    
    .detailed-scan-results {
        padding: 20px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    
    .stat-card {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid var(--terminal-border);
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: var(--terminal-green);
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #888;
        font-size: 14px;
    }
    
    .chart-container {
        margin-top: 30px;
    }
    
    .chart-container h5 {
        color: var(--terminal-cyan);
        margin-bottom: 15px;
    }
    
    .chart {
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
        padding: 10px;
    }
    
    .chart-bar {
        background: linear-gradient(90deg, var(--terminal-green), var(--terminal-cyan));
        margin: 5px 0;
        padding: 8px 15px;
        border-radius: 4px;
        color: #000;
        font-weight: bold;
        transition: width 1s ease;
    }
    
    /* Уведомления */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background-color: rgba(20, 20, 20, 0.95);
        border-left: 4px solid #00ff00;
        border-radius: 4px;
        color: white;
        display: flex;
        align-items: center;
        gap: 10px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        z-index: 10000;
        max-width: 400px;
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success {
        border-left-color: var(--terminal-green);
    }
    
    .notification-error {
        border-left-color: var(--terminal-red);
    }
    
    .notification-info {
        border-left-color: var(--terminal-cyan);
    }
    
    @media (max-width: 768px) {
        .quick-scan-grid {
            grid-template-columns: 1fr;
        }
        
        .server-actions {
            flex-direction: column;
        }
        
        .scan-actions {
            flex-direction: column;
        }
        
        .modal-actions {
            flex-direction: column;
        }
        
        .server-info-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>
</body>
</html>