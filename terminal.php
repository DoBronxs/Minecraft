<?php
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, level, vip_level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Терминал | HackCraft</title>
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
                <li><a href="terminal.php" class="nav-link active"><i class="fas fa-terminal"></i> Терминал</a></li>
                <li><a href="server-parser.php" class="nav-link"><i class="fas fa-server"></i> Парсер серверов</a></li>
                <li><a href="donate.php" class="nav-link"><i class="fas fa-gem"></i> Донат</a></li>
            </ul>
            <div class="user-info">
                <span class="user-level">Уровень <?php echo $user['level']; ?></span>
                <a href="logout.php" class="logout-btn" title="Выйти"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Полноэкранный терминал -->
        <div class="full-terminal">
            <div class="terminal-header">
                <div class="terminal-buttons">
                    <span class="close" onclick="window.location.href='dashboard.php'"></span>
                    <span class="minimize" onclick="minimizeTerminal()"></span>
                    <span class="maximize" onclick="toggleFullscreen()"></span>
                </div>
                <span class="terminal-title">
                    <i class="fas fa-terminal"></i> HackCraft Terminal 
                    <span class="terminal-status">Connected as <?php echo htmlspecialchars($user['username']); ?></span>
                </span>
            </div>
            
            <div class="terminal-body" id="terminal-output">
                <!-- Начальное сообщение -->
                <div class="terminal-welcome">
                    <pre class="ascii-art">
░█░█░█▀▀░█░░░█▀▀░█▀█░█▀▄░█▀▀
░█▀█░█▀▀░█░░░█▀▀░█▀█░█▀▄░▀▀█
░▀░▀░▀▀▀░▀▀▀░▀▀▀░▀░▀░▀░▀░▀▀▀
                    </pre>
                    <p class="typewriter">HackCraft Terminal v2.0.1 | Minecraft Hacking Environment</p>
                    <p>Введите <span class="command">help</span> для списка команд</p>
                    <p>Используйте <span class="command">Tab</span> для автодополнения и <span class="command">↑/↓</span> для истории команд</p>
                </div>
                
                <!-- История команд будет добавляться сюда -->
            </div>
            
            <!-- Ввод команды -->
            <div class="terminal-input-container">
                <div class="input-line">
                    <span class="prompt">
                        <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="host">@hackcraft</span>
                        <span class="path">:~$</span>
                    </span>
                    <input type="text" id="terminal-command" class="terminal-input" 
                           autocomplete="off" autofocus spellcheck="false">
                    <button onclick="executeCommand()" class="enter-btn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                <div class="input-hints">
                    <span id="command-hint"></span>
                    <span class="hint-keys">
                        <kbd>Tab</kbd> автодополнение • 
                        <kbd>↑/↓</kbd> история • 
                        <kbd>Ctrl+L</kbd> очистить
                    </span>
                </div>
            </div>
            
            <!-- Быстрый доступ к командам -->
            <div class="quick-commands">
                <h4>Быстрые команды:</h4>
                <div class="quick-commands-grid">
                    <button class="quick-command" onclick="insertCommand('help')">
                        <i class="fas fa-question-circle"></i> help
                    </button>
                    <button class="quick-command" onclick="insertCommand('status')">
                        <i class="fas fa-info-circle"></i> status
                    </button>
                    <button class="quick-command" onclick="insertCommand('clear')">
                        <i class="fas fa-broom"></i> clear
                    </button>
                    <button class="quick-command" onclick="insertCommand('cd articles')">
                        <i class="fas fa-newspaper"></i> cd articles
                    </button>
                    <button class="quick-command" onclick="insertCommand('cd tasks')">
                        <i class="fas fa-tasks"></i> cd tasks
                    </button>
                    <?php if ($user['vip_level'] !== 'none'): ?>
                        <button class="quick-command vip" onclick="insertCommand('cd parser')">
                            <i class="fas fa-server"></i> cd parser
                        </button>
                        <button class="quick-command vip" onclick="insertCommand('scan')">
                            <i class="fas fa-search"></i> scan
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Справка по командам -->
        <div class="terminal-help-sidebar">
            <h3><i class="fas fa-book"></i> Справка по командам</h3>
            <div class="help-categories">
                <div class="help-category">
                    <h4>Основные команды</h4>
                    <div class="command-help">
                        <code>help</code>
                        <span>Показать справку по командам</span>
                    </div>
                    <div class="command-help">
                        <code>clear</code>
                        <span>Очистить экран терминала</span>
                    </div>
                    <div class="command-help">
                        <code>status</code>
                        <span>Показать статус аккаунта</span>
                    </div>
                </div>
                
                <div class="help-category">
                    <h4>Навигация</h4>
                    <div class="command-help">
                        <code>cd [страница]</code>
                        <span>Перейти на страницу сайта</span>
                    </div>
                    <div class="command-help">
                        <code>cd articles</code>
                        <span>Перейти к статьям</span>
                    </div>
                    <div class="command-help">
                        <code>cd tasks</code>
                        <span>Перейти к заданиям</span>
                    </div>
                    <div class="command-help">
                        <code>cd parser</code>
                        <span>Парсер серверов (VIP)</span>
                    </div>
                </div>
                
                <div class="help-category">
                    <h4>Работа с текстом</h4>
                    <div class="command-help">
                        <code>decode [текст]</code>
                        <span>Расшифровать текст</span>
                    </div>
                    <div class="command-help">
                        <code>encode [текст]</code>
                        <span>Зашифровать текст</span>
                    </div>
                </div>
                
                <div class="help-category">
                    <h4>Задания</h4>
                    <div class="command-help">
                        <code>/steal_data</code>
                        <span>Начать SQL-инъекцию</span>
                    </div>
                    <div class="command-help">
                        <code>/bypass_firewall</code>
                        <span>Обойти файрвол</span>
                    </div>
                    <div class="command-help">
                        <code>/decrypt_message</code>
                        <span>Дешифровать сообщение</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // История команд
    let commandHistory = JSON.parse(localStorage.getItem('terminalHistory') || '[]');
    let historyIndex = commandHistory.length;
    
    // Элементы DOM
    const terminalInput = document.getElementById('terminal-command');
    const terminalOutput = document.getElementById('terminal-output');
    const commandHint = document.getElementById('command-hint');
    
    // Доступные команды
    const availableCommands = {
        'help': 'Показать справку по командам',
        'clear': 'Очистить экран терминала',
        'status': 'Показать статус аккаунта',
        'cd': 'Перейти на страницу сайта',
        'decode': 'Расшифровать текст',
        'encode': 'Зашифровать текст',
        '/steal_data': 'Начать задание: SQL-инъекция',
        '/bypass_firewall': 'Начать задание: Обход файрвола',
        '/decrypt_message': 'Начать задание: Дешифровка сообщения',
        '/bruteforce_password': 'Начать задание: Брутфорс пароля',
        'scan': 'Сканировать сеть (VIP)',
        'server': 'Работа с серверами (Hacker)'
    };
    
    // Инициализация терминала
    document.addEventListener('DOMContentLoaded', function() {
        terminalInput.focus();
        
        // Обработка нажатия Enter
        terminalInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                executeCommand();
            }
        });
        
        // Обработка Tab для автодополнения
        terminalInput.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                autoCompleteCommand();
            }
            
            // Стрелки вверх/вниз для истории
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (historyIndex > 0) {
                    historyIndex--;
                    terminalInput.value = commandHistory[historyIndex] || '';
                }
            }
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (historyIndex < commandHistory.length - 1) {
                    historyIndex++;
                    terminalInput.value = commandHistory[historyIndex] || '';
                } else {
                    historyIndex = commandHistory.length;
                    terminalInput.value = '';
                }
            }
            
            // Ctrl+L для очистки
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                clearTerminal();
            }
        });
        
        // Подсказки при вводе
        terminalInput.addEventListener('input', function() {
            updateCommandHint();
        });
    });
    
    // Выполнение команды
    function executeCommand() {
        const command = terminalInput.value.trim();
        if (!command) return;
        
        // Добавляем команду в историю
        commandHistory.push(command);
        if (commandHistory.length > 100) commandHistory.shift();
        localStorage.setItem('terminalHistory', JSON.stringify(commandHistory));
        historyIndex = commandHistory.length;
        
        // Отображаем команду в терминале
        addOutputLine(`<span class="command-line">${command}</span>`, 'user');
        
        // Обрабатываем команду
        processCommand(command);
        
        // Очищаем поле ввода
        terminalInput.value = '';
        updateCommandHint();
        
        // Прокручиваем вниз
        terminalOutput.scrollTop = terminalOutput.scrollHeight;
    }
    
    // Обработка команды
    function processCommand(command) {
        const args = command.split(' ');
        const cmd = args[0].toLowerCase();
        
        switch (cmd) {
            case 'help':
                showHelp();
                break;
                
            case 'clear':
                clearTerminal();
                break;
                
            case 'status':
                showStatus();
                break;
                
            case 'cd':
                handleCdCommand(args);
                break;
                
            case 'decode':
                handleDecodeCommand(args.slice(1).join(' '));
                break;
                
            case 'encode':
                handleEncodeCommand(args.slice(1).join(' '));
                break;
                
            case 'scan':
                if ('<?php echo $user['vip_level']; ?>' === 'none') {
                    addOutputLine('<span class="error">Ошибка: Требуется VIP доступ. Используйте команду donate для получения доступа</span>');
                } else {
                    startScanning();
                }
                break;
                
            case '/steal_data':
            case '/bypass_firewall':
            case '/decrypt_message':
            case '/bruteforce_password':
                startTask(cmd);
                break;
                
            default:
                if (cmd.startsWith('/')) {
                    addOutputLine(`<span class="error">Неизвестная команда: ${cmd}. Введите help для списка команд</span>`);
                } else {
                    addOutputLine(`<span class="error">Команда не найдена: ${cmd}</span>`);
                }
        }
    }
    
    // Показать справку
    function showHelp() {
        let helpText = '<div class="help-output">';
        helpText += '<h4>Доступные команды:</h4>';
        helpText += '<table class="help-table">';
        
        for (const [cmd, desc] of Object.entries(availableCommands)) {
            helpText += `<tr><td><code>${cmd}</code></td><td>${desc}</td></tr>`;
        }
        
        helpText += '</table>';
        helpText += '<p>Используйте <code>Tab</code> для автодополнения команд</p>';
        helpText += '</div>';
        
        addOutputLine(helpText);
    }
    
    // Показать статус
    function showStatus() {
        const status = `
<div class="status-output">
    <h4>Статус аккаунта:</h4>
    <div class="status-grid">
        <div class="status-item">
            <span class="status-label">Пользователь:</span>
            <span class="status-value"><?php echo htmlspecialchars($user['username']); ?></span>
        </div>
        <div class="status-item">
            <span class="status-label">Уровень:</span>
            <span class="status-value"><?php echo $user['level']; ?></span>
        </div>
        <div class="status-item">
            <span class="status-label">VIP статус:</span>
            <span class="status-value"><?php echo $user['vip_level']; ?></span>
        </div>
        <div class="status-item">
            <span class="status-label">Сессия:</span>
            <span class="status-value active">Активна</span>
        </div>
    </div>
    <div class="status-actions">
        <button onclick="executeCommand('cd dashboard')" class="btn-status">Перейти в дашборд</button>
        <button onclick="executeCommand('cd tasks')" class="btn-status">Перейти к заданиям</button>
    </div>
</div>`;
        addOutputLine(status);
    }
    
    // Обработка команды cd
    function handleCdCommand(args) {
        if (args.length < 2) {
            addOutputLine('<span class="error">Использование: cd [страница]</span>');
            return;
        }
        
        const page = args[1].toLowerCase();
        const pages = {
            'articles': 'articles.php',
            'tasks': 'tasks.php',
            'parser': 'server-parser.php',
            'dashboard': 'dashboard.php',
            'donate': 'donate.php',
            'terminal': 'terminal.php'
        };
        
        if (pages[page]) {
            if (page === 'parser' && '<?php echo $user['vip_level']; ?>' === 'none') {
                addOutputLine('<span class="error">Доступ запрещен. Требуется VIP подписка. Используйте команду donate</span>');
            } else {
                addOutputLine(`<span class="success">Перенаправление на ${page}...</span>`);
                setTimeout(() => {
                    window.location.href = pages[page];
                }, 1000);
            }
        } else {
            addOutputLine(`<span class="error">Страница не найдена: ${page}. Доступные: ${Object.keys(pages).join(', ')}</span>`);
        }
    }
    
    // Обработка команды decode
    function handleDecodeCommand(text) {
        if (!text) {
            addOutputLine('<span class="error">Использование: decode [текст]</span>');
            return;
        }
        
        // Отправка на сервер для дешифровки
        fetch('api/decode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ text: text })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const output = `
<div class="decode-output">
    <h4>Результат дешифровки:</h4>
    <div class="decoded-text">${data.decoded}</div>
    <div class="decode-info">
        <span class="success">✓ Текст успешно расшифрован</span>
        ${data.achievement ? '<span class="achievement">🎉 ' + data.achievement + '</span>' : ''}
    </div>
</div>`;
                addOutputLine(output);
                
                // Показать уведомление о достижении
                if (data.achievement) {
                    showNotification(data.achievement, 'success');
                }
            } else {
                addOutputLine(`<span class="error">Ошибка дешифровки: ${data.error}</span>`);
            }
        })
        .catch(error => {
            addOutputLine('<span class="error">Ошибка соединения с сервером</span>');
            console.error('Error:', error);
        });
    }
    
    // Обработка команды encode
    function handleEncodeCommand(text) {
        if (!text) {
            addOutputLine('<span class="error">Использование: encode [текст]</span>');
            return;
        }
        
        // Отправка на сервер для шифрования
        fetch('api/encode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ text: text })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const output = `
<div class="encode-output">
    <h4>Результат шифрования:</h4>
    <div class="encoded-text">${data.encoded}</div>
    <div class="encode-info">
        <span class="success">✓ Текст успешно зашифрован</span>
        <button onclick="copyToClipboard('${data.encoded.replace(/'/g, "\\'")}')" class="btn-copy">
            <i class="far fa-copy"></i> Копировать
        </button>
    </div>
</div>`;
                addOutputLine(output);
            } else {
                addOutputLine(`<span class="error">Ошибка шифрования: ${data.error}</span>`);
            }
        })
        .catch(error => {
            addOutputLine('<span class="error">Ошибка соединения с сервером</span>');
            console.error('Error:', error);
        });
    }
    
    // Начать сканирование (для VIP)
    function startScanning() {
        addOutputLine('<span class="info">Запуск сканирования сети...</span>');
        
        // Симуляция сканирования
        setTimeout(() => {
            addOutputLine('<span class="success">✓ Найдено 15 Minecraft серверов</span>');
            addOutputLine('<span class="info">Используйте команду server list для просмотра</span>');
        }, 2000);
    }
    
    // Начать задание
    function startTask(taskCommand) {
        addOutputLine(`<span class="info">Запуск задания: ${taskCommand}...</span>`);
        
        // Отправка на сервер
        fetch('api/start_task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ command: taskCommand })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addOutputLine(`
<div class="task-started">
    <h4>Задание начато!</h4>
    <p>${data.instructions}</p>
    <div class="task-details">
        <strong>Цель:</strong> ${data.objective}<br>
        <strong>Награда:</strong> ${data.reward} XP<br>
        <strong>Сложность:</strong> ${data.difficulty}
    </div>
</div>`);
            } else {
                addOutputLine(`<span class="error">Ошибка: ${data.error}</span>`);
            }
        })
        .catch(error => {
            addOutputLine('<span class="error">Ошибка соединения с сервером</span>');
            console.error('Error:', error);
        });
    }
    
    // Добавить строку вывода в терминал
    function addOutputLine(content, type = 'system') {
        const line = document.createElement('div');
        line.className = `terminal-line ${type}`;
        line.innerHTML = content;
        terminalOutput.appendChild(line);
        
        // Автопрокрутка
        terminalOutput.scrollTop = terminalOutput.scrollHeight;
    }
    
    // Очистить терминал
    function clearTerminal() {
        terminalOutput.innerHTML = '';
        addOutputLine('<span class="info">Терминал очищен</span>');
    }
    
    // Автодополнение команд
    function autoCompleteCommand() {
        const input = terminalInput.value;
        const matchingCommands = Object.keys(availableCommands).filter(cmd => 
            cmd.startsWith(input)
        );
        
        if (matchingCommands.length === 1) {
            terminalInput.value = matchingCommands[0] + ' ';
        } else if (matchingCommands.length > 1) {
            // Показать возможные варианты
            const suggestions = matchingCommands.map(cmd => 
                `<code>${cmd}</code> - ${availableCommands[cmd]}`
            ).join('<br>');
            
            addOutputLine(`<div class="autocomplete-suggestions">${suggestions}</div>`);
        }
    }
    
    // Обновить подсказку
    function updateCommandHint() {
        const input = terminalInput.value.trim();
        if (!input) {
            commandHint.textContent = '';
            return;
        }
        
        const matchingCommand = Object.keys(availableCommands).find(cmd => 
            cmd.startsWith(input.split(' ')[0])
        );
        
        if (matchingCommand) {
            commandHint.textContent = availableCommands[matchingCommand];
        } else {
            commandHint.textContent = 'Неизвестная команда. Нажмите Tab для подсказок';
        }
    }
    
    // Вставить команду в поле ввода
    function insertCommand(command) {
        terminalInput.value = command;
        terminalInput.focus();
        updateCommandHint();
    }
    
    // Копировать в буфер обмена
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Текст скопирован в буфер обмена!', 'success');
        }).catch(err => {
            console.error('Ошибка копирования:', err);
        });
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
    
    // Минимизация терминала
    function minimizeTerminal() {
        const terminal = document.querySelector('.full-terminal');
        terminal.style.transform = 'scale(0.8)';
        terminal.style.opacity = '0.7';
        setTimeout(() => {
            terminal.style.transform = '';
            terminal.style.opacity = '';
        }, 300);
    }
    
    // Полноэкранный режим
    function toggleFullscreen() {
        const elem = document.querySelector('.full-terminal');
        
        if (!document.fullscreenElement) {
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
    }
    </script>
    
    <style>
    /* Стили для терминала */
    .full-terminal {
        background-color: var(--terminal-bg);
        border-radius: 8px;
        border: 2px solid var(--terminal-green);
        box-shadow: 0 0 30px rgba(0, 255, 0, 0.3);
        overflow: hidden;
        margin-bottom: 20px;
        transition: all 0.3s;
    }
    
    .terminal-header {
        background: linear-gradient(to right, #222, #333);
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--terminal-border);
    }
    
    .terminal-buttons span {
        cursor: pointer;
    }
    
    .terminal-title {
        color: var(--terminal-green);
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .terminal-status {
        color: #888;
        font-size: 12px;
        font-weight: normal;
    }
    
    .terminal-body {
        padding: 20px;
        height: 500px;
        overflow-y: auto;
        font-family: 'Courier New', monospace;
        background-color: #000;
    }
    
    .terminal-welcome {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .terminal-welcome .ascii-art {
        color: var(--terminal-green);
        font-size: 14px;
        line-height: 1.2;
        margin: 20px 0;
    }
    
    .terminal-line {
        margin-bottom: 10px;
        line-height: 1.5;
    }
    
    .terminal-line.user {
        opacity: 0.9;
    }
    
    .command-line {
        color: var(--terminal-cyan);
        font-weight: bold;
    }
    
    .error {
        color: var(--terminal-red);
    }
    
    .success {
        color: var(--terminal-green);
    }
    
    .info {
        color: var(--terminal-cyan);
    }
    
    .terminal-input-container {
        background-color: rgba(0, 0, 0, 0.5);
        padding: 15px;
        border-top: 1px solid var(--terminal-border);
    }
    
    .input-line {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .prompt {
        color: var(--terminal-green);
        font-weight: bold;
        white-space: nowrap;
        margin-right: 10px;
        display: flex;
        align-items: center;
        gap: 2px;
    }
    
    .prompt .username {
        color: var(--terminal-purple);
    }
    
    .prompt .host {
        color: var(--terminal-cyan);
    }
    
    .prompt .path {
        color: var(--terminal-yellow);
    }
    
    .terminal-input {
        flex: 1;
        background: transparent;
        border: none;
        color: var(--terminal-text);
        font-family: 'Courier New', monospace;
        font-size: 16px;
        padding: 5px;
        outline: none;
    }
    
    .input-hints {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #666;
    }
    
    .input-hints kbd {
        background-color: #333;
        padding: 2px 6px;
        border-radius: 3px;
        border: 1px solid #666;
    }
    
    .quick-commands {
        background-color: rgba(20, 20, 20, 0.8);
        padding: 15px;
        border-top: 1px solid var(--terminal-border);
    }
    
    .quick-commands h4 {
        color: var(--terminal-cyan);
        margin-bottom: 10px;
    }
    
    .quick-commands-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .quick-command {
        background-color: rgba(0, 255, 0, 0.1);
        border: 1px solid var(--terminal-green);
        color: var(--terminal-green);
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .quick-command:hover {
        background-color: rgba(0, 255, 0, 0.2);
        transform: translateY(-2px);
    }
    
    .quick-command.vip {
        background-color: rgba(255, 0, 255, 0.1);
        border-color: var(--terminal-purple);
        color: var(--terminal-purple);
    }
    
    .terminal-help-sidebar {
        background-color: rgba(20, 20, 20, 0.8);
        border-radius: 8px;
        padding: 20px;
        border: 1px solid var(--terminal-border);
    }
    
    .terminal-help-sidebar h3 {
        color: var(--terminal-cyan);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .help-categories {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .help-category h4 {
        color: var(--terminal-green);
        margin-bottom: 10px;
        padding-bottom: 5px;
        border-bottom: 1px solid var(--terminal-border);
    }
    
    .command-help {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 8px;
        padding: 8px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 4px;
    }
    
    .command-help code {
        background-color: rgba(0, 255, 0, 0.1);
        padding: 2px 8px;
        border-radius: 4px;
        color: var(--terminal-green);
        font-family: 'Courier New', monospace;
        white-space: nowrap;
        min-width: 150px;
    }
    
    .command-help span {
        color: #aaa;
        font-size: 14px;
        flex: 1;
    }
    
    /* Стили для вывода команд */
    .help-output, .status-output, .decode-output, .encode-output, .task-started {
        padding: 15px;
        background-color: rgba(0, 20, 0, 0.3);
        border-radius: 4px;
        margin: 10px 0;
        border-left: 3px solid var(--terminal-green);
    }
    
    .help-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }
    
    .help-table td {
        padding: 8px;
        border-bottom: 1px solid var(--terminal-border);
        vertical-align: top;
    }
    
    .help-table code {
        background-color: rgba(0, 255, 0, 0.1);
        padding: 2px 6px;
        border-radius: 3px;
        color: var(--terminal-green);
    }
    
    .status-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin: 15px 0;
    }
    
    .status-item {
        display: flex;
        flex-direction: column;
    }
    
    .status-label {
        color: #888;
        font-size: 12px;
        margin-bottom: 5px;
    }
    
    .status-value {
        color: var(--terminal-green);
        font-weight: bold;
    }
    
    .status-value.active {
        color: var(--terminal-cyan);
    }
    
    .status-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
    .btn-status {
        background-color: var(--terminal-green);
        color: #000;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
    }
    
    .btn-status:hover {
        background-color: #00cc00;
    }
    
    .decoded-text, .encoded-text {
        padding: 15px;
        background-color: #000;
        border-radius: 4px;
        margin: 10px 0;
        font-family: 'Courier New', monospace;
        white-space: pre-wrap;
        word-break: break-word;
        border: 1px solid var(--terminal-border);
    }
    
    .decode-info, .encode-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
    }
    
    .achievement {
        background-color: rgba(255, 215, 0, 0.2);
        color: #FFD700;
        padding: 5px 10px;
        border-radius: 4px;
        font-weight: bold;
    }
    
    .btn-copy {
        background-color: var(--terminal-yellow);
        color: #000;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .task-details {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
        border-left: 3px solid var(--terminal-purple);
    }
    
    .autocomplete-suggestions {
        background-color: rgba(0, 0, 0, 0.5);
        padding: 10px;
        border-radius: 4px;
        border: 1px solid var(--terminal-border);
        font-size: 14px;
    }
    
    @media (max-width: 768px) {
        .container {
            display: flex;
            flex-direction: column;
        }
        
        .terminal-body {
            height: 400px;
        }
        
        .status-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-commands-grid {
            flex-direction: column;
        }
        
        .terminal-help-sidebar {
            order: -1;
            margin-bottom: 20px;
        }
    }
    </style>
</body>
</html>