// Основные функции HackCraft
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация терминального ввода
    initTerminalInput();
    
    // Инициализация анимаций
    initAnimations();
    
    // Инициализация системы команд
    initCommandSystem();
});

// Инициализация терминального ввода
function initTerminalInput() {
    const terminalInputs = document.querySelectorAll('.terminal-input');
    
    terminalInputs.forEach(input => {
        // Автодополнение по Tab
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                const commands = ['/register', '/login', '/help', '/clear', '/status', '/cd', '/decode'];
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
                const history = JSON.parse(localStorage.getItem('terminalHistory') || '[]');
                if (history.length > 0) {
                    if (!this.historyIndex && this.historyIndex !== 0) {
                        this.historyIndex = history.length;
                    }
                    if (this.historyIndex > 0) {
                        this.historyIndex--;
                        this.value = history[this.historyIndex];
                    }
                }
            }
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const history = JSON.parse(localStorage.getItem('terminalHistory') || '[]');
                if (this.historyIndex !== undefined) {
                    if (this.historyIndex < history.length - 1) {
                        this.historyIndex++;
                        this.value = history[this.historyIndex];
                    } else {
                        this.historyIndex = history.length;
                        this.value = '';
                    }
                }
            }
        });
        
        // Сохранение истории при отправке
        input.closest('form')?.addEventListener('submit', function() {
            const command = input.value.trim();
            if (command) {
                const history = JSON.parse(localStorage.getItem('terminalHistory') || '[]');
                history.push(command);
                if (history.length > 50) history.shift(); // Ограничение истории
                localStorage.setItem('terminalHistory', JSON.stringify(history));
            }
        });
    });
}

// Инициализация анимаций
function initAnimations() {
    // Анимация мигающего курсора
    const blinkCursor = document.querySelector('.blink-cursor');
    if (blinkCursor) {
        setInterval(() => {
            blinkCursor.style.opacity = blinkCursor.style.opacity === '0' ? '1' : '0';
        }, 500);
    }
    
    // Анимация прогресс-бара
    const xpBars = document.querySelectorAll('.xp-progress');
    xpBars.forEach(bar => {
        const targetWidth = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = targetWidth;
        }, 300);
    });
}

// Система команд терминала
function initCommandSystem() {
    const commandForm = document.querySelector('.terminal-input-form');
    if (!commandForm) return;
    
    commandForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const input = this.querySelector('.terminal-input');
        const command = input.value.trim();
        
        if (command === '/help') {
            showHelp();
            input.value = '';
        } else if (command === '/clear') {
            clearTerminal();
            input.value = '';
        } else if (command.startsWith('/decode ')) {
            const text = command.substring(8);
            decodeText(text);
            input.value = '';
        }
    });
}

// Показать справку по командам
function showHelp() {
    const output = document.querySelector('.terminal-output') || document.querySelector('.terminal-body');
    if (!output) return;
    
    const helpText = `
<div class="help-section">
    <h3>Доступные команды:</h3>
    <div class="command-item">
        <span class="command-name">help</span>
        <span class="command-desc">- Показать эту справку</span>
    </div>
    <div class="command-item">
        <span class="command-name">clear</span>
        <span class="command-desc">- Очистить экран терминала</span>
    </div>
    <div class="command-item">
        <span class="command-name">status</span>
        <span class="command-desc">- Показать статус аккаунта</span>
    </div>
    <div class="command-item">
        <span class="command-name">cd [страница]</span>
        <span class="command-desc">- Перейти на страницу сайта</span>
    </div>
    <div class="command-item">
        <span class="command-name">decode [текст]</span>
        <span class="command-desc">- Расшифровать текст</span>
    </div>
    <div class="command-item">
        <span class="command-name">/steal_data</span>
        <span class="command-desc">- Начать задание "SQL-инъекция"</span>
    </div>
</div>`;
    
    output.innerHTML = helpText;
}

// Очистить терминал
function clearTerminal() {
    const output = document.querySelector('.terminal-output');
    if (output) {
        output.innerHTML = '';
    }
}

// Дешифровка текста
function decodeText(encodedText) {
    // Простая демо-дешифровка (в реальности будет серверная)
    const output = document.querySelector('.terminal-output');
    if (!output) return;
    
    const decoded = encodedText
        .split('')
        .map(char => String.fromCharCode(char.charCodeAt(0) - 1))
        .join('');
    
    output.innerHTML = `
<div class="decoding-result">
    <h4>Результат дешифровки:</h4>
    <div class="decoded-text">
        ${decoded}
    </div>
    <div class="achievement-unlocked">
        <i class="fas fa-trophy"></i> Достижение разблокировано: "Внимательная личность"
    </div>
</div>`;
    
    // Симуляция получения достижения
    setTimeout(() => {
        showNotification('Достижение получено: Внимательная личность!');
    }, 1000);
}

// Уведомления
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Анимация появления
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Автоматическое скрытие
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Инициализация системы заданий
function initTasks() {
    const taskButtons = document.querySelectorAll('.task-start-btn');
    taskButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = this.dataset.taskId;
            startTask(taskId);
        });
    });
}

function startTask(taskId) {
    // В реальности AJAX запрос к серверу
    showNotification('Задание начато! Проверьте терминал для выполнения.', 'success');
    
    // Симуляция выполнения задания
    setTimeout(() => {
        showNotification('Задание выполнено! +100 опыта', 'success');
        updateXP(100);
    }, 2000);
}

function updateXP(amount) {
    const xpElement = document.querySelector('.current-xp');
    const progressBar = document.querySelector('.xp-progress');
    
    if (xpElement && progressBar) {
        const currentXP = parseInt(xpElement.textContent) || 0;
        const newXP = currentXP + amount;
        xpElement.textContent = newXP;
        
        // Обновление прогресс-бара (упрощенно)
        const newWidth = Math.min((newXP % 1000) / 10, 100);
        progressBar.style.width = newWidth + '%';
    }
}

// Система блокировки для админ-панели
function initAdminSecurity() {
    const loginForm = document.getElementById('admin-login-form');
    if (!loginForm) return;
    
    let attempts = parseInt(localStorage.getItem('adminLoginAttempts') || '0');
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (attempts >= 3) {
            const blockTime = localStorage.getItem('adminBlockTime');
            if (blockTime && Date.now() < parseInt(blockTime)) {
                showNotification('Доступ заблокирован! Подождите 5 минут или задонатьте 100 рублей.', 'error');
                return;
            } else {
                attempts = 0;
                localStorage.removeItem('adminBlockTime');
            }
        }
        
        // Проверка логина (демо)
        const username = this.querySelector('[name="username"]').value;
        const password = this.querySelector('[name="password"]').value;
        
        if (username === 'admin' && password === 'admin123') {
            showNotification('Вход выполнен успешно!', 'success');
            attempts = 0;
            localStorage.setItem('adminLoginAttempts', '0');
            // Перенаправление в админ-панель
            setTimeout(() => window.location.href = 'admin-panel.php', 1000);
        } else {
            attempts++;
            localStorage.setItem('adminLoginAttempts', attempts.toString());
            
            if (attempts >= 3) {
                const blockUntil = Date.now() + 5 * 60 * 1000; // 5 минут
                localStorage.setItem('adminBlockTime', blockUntil.toString());
                showNotification('Слишком много попыток! Доступ заблокирован на 5 минут.', 'error');
            } else {
                showNotification(`Неверные данные! Осталось попыток: ${3 - attempts}`, 'error');
            }
        }
    });
}

// Парсер Minecraft серверов (VIP/Hacker функции)
function initServerParser() {
    const scanBtn = document.getElementById('scan-servers');
    if (!scanBtn) return;
    
    scanBtn.addEventListener('click', function() {
        const network = prompt('Введите сеть (например 114.172.0.0/16):');
        if (!network) return;
        
        const maxIps = prompt('Введите максимум IP для проверки (Не больше 999):');
        if (!maxIps || parseInt(maxIps) > 999) {
            showNotification('Максимум 999 IP!', 'error');
            return;
        }
        
        const ports = prompt('Введите диапазон портов (25560-25570):');
        if (!ports || !ports.includes('-')) {
            showNotification('Неверный формат портов!', 'error');
            return;
        }
        
        // Симуляция сканирования
        showNotification(`Сканирование ${network} начато...`, 'info');
        
        setTimeout(() => {
            const results = Math.floor(Math.random() * 20) + 5;
            showNotification(`Найдено ${results} серверов Minecraft!`, 'success');
            updateServerList(results);
        }, 3000);
    });
}

function updateServerList(count) {
    const serverList = document.getElementById('server-list');
    if (!serverList) return;
    
    let html = '<h3>Найденные серверы:</h3>';
    for (let i = 1; i <= count; i++) {
        const ip = `192.168.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}`;
        const port = 25560 + Math.floor(Math.random() * 10);
        const players = Math.floor(Math.random() * 100);
        
        html += `
        <div class="server-item">
            <span class="server-ip">${i}. ${ip}:${port}</span>
            <span class="server-players">👥 ${players}/100</span>
            <button class="btn-scan-server" onclick="scanServer(${i})">Сканировать</button>
        </div>`;
    }
    
    serverList.innerHTML = html;
}

function scanServer(index) {
    showNotification(`Сканирование сервера #${index}...`, 'info');
    // В реальности AJAX запрос
}

// Добавление стилей для уведомлений
const style = document.createElement('style');
style.textContent = `
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
    border-left-color: #00ff00;
}

.notification-error {
    border-left-color: #ff5555;
}

.notification-info {
    border-left-color: #00ffff;
}

.server-item {
    background-color: rgba(30, 30, 30, 0.8);
    padding: 10px;
    margin: 5px 0;
    border-radius: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-scan-server {
    background-color: #00ffff;
    color: black;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}
`;
document.head.appendChild(style);