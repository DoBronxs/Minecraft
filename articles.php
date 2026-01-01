<?php
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Получение информации о пользователе
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT level, vip_level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Получение статей с учетом уровня пользователя
$search = isset($_GET['search']) ? "%" . $_GET['search'] . "%" : "%";
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

$sql = "SELECT * FROM articles WHERE (title LIKE ? OR description LIKE ?)";
$params = [$search, $search];

// Фильтр по уровню
$sql .= " AND required_level <= ?";
$params[] = $user['level'];

// Фильтр по категории (если есть)
if ($category !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY required_level, created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// Получение уникальных категорий
$categories = $pdo->query("SELECT DISTINCT category FROM articles WHERE category IS NOT NULL")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статьи | HackCraft</title>
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
                <li><a href="articles.php" class="nav-link active"><i class="fas fa-newspaper"></i> Статьи</a></li>
                <li><a href="tasks.php" class="nav-link"><i class="fas fa-tasks"></i> Задания</a></li>
                <li><a href="terminal.php" class="nav-link"><i class="fas fa-terminal"></i> Терминал</a></li>
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
        <!-- Заголовок и поиск -->
        <div class="page-header">
            <h1><i class="fas fa-newspaper"></i> База знаний HackCraft</h1>
            <p class="subtitle">Изучайте технические материалы по хакингу Minecraft серверов</p>
            
            <div class="search-filter">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Поиск статей..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                    
                    <div class="filter-options">
                        <select name="category" onchange="this.form.submit()">
                            <option value="all" <?php echo $category == 'all' ? 'selected' : ''; ?>>Все категории</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category']; ?>" 
                                        <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'newest') ? 'selected' : ''; ?>>Сначала новые</option>
                            <option value="oldest" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'oldest') ? 'selected' : ''; ?>>Сначала старые</option>
                            <option value="level" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'level') ? 'selected' : ''; ?>>По уровню</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Информация о шифровании -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <p>Некоторые статьи зашифрованы. Для их чтения скопируйте текст и используйте команду <code>decode [текст]</code> в терминале</p>
        </div>

        <!-- Сетка статей -->
        <div class="articles-grid">
            <?php if (count($articles) > 0): ?>
                <?php foreach ($articles as $article): ?>
                    <div class="article-card <?php echo $article['is_encrypted'] ? 'article-encrypted' : ''; ?>">
                        <div class="article-header">
                            <span class="article-level">Ур. <?php echo $article['required_level']; ?></span>
                            <?php if ($article['is_encrypted']): ?>
                                <span class="encrypted-badge"><i class="fas fa-lock"></i> Зашифровано</span>
                            <?php endif; ?>
                        </div>
                        
                        <h3 class="article-title">
                            <a href="article.php?id=<?php echo $article['id']; ?>">
                                <?php echo htmlspecialchars($article['title']); ?>
                            </a>
                        </h3>
                        
                        <p class="article-desc">
                            <?php echo htmlspecialchars($article['description']); ?>
                        </p>
                        
                        <div class="article-footer">
                            <span class="article-date">
                                <i class="far fa-calendar"></i> 
                                <?php echo date('d.m.Y', strtotime($article['created_at'])); ?>
                            </span>
                            
                            <?php if ($article['is_encrypted']): ?>
                                <button class="btn-copy-encrypted" 
                                        data-text="<?php echo htmlspecialchars(encrypt_text($article['content'])); ?>"
                                        onclick="copyEncryptedText(this)">
                                    <i class="far fa-copy"></i> Копировать текст
                                </button>
                            <?php else: ?>
                                <a href="article.php?id=<?php echo $article['id']; ?>" class="btn-read">
                                    <i class="fas fa-book-open"></i> Читать
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>Статьи не найдены</h3>
                    <p>Попробуйте изменить параметры поиска или повысьте уровень для доступа к материалам</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Пагинация (если нужно) -->
        <?php if (count($articles) > 12): ?>
        <div class="pagination">
            <a href="#" class="page-link active">1</a>
            <a href="#" class="page-link">2</a>
            <a href="#" class="page-link">3</a>
            <span class="page-dots">...</span>
            <a href="#" class="page-link">10</a>
            <a href="#" class="page-link next">Следующая <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php endif; ?>

        <!-- Быстрый терминал для дешифровки -->
        <div class="quick-decode-terminal">
            <h3><i class="fas fa-unlock-alt"></i> Быстрая дешифровка</h3>
            <div class="terminal-output" id="decode-output">
                <p>Вставьте зашифрованный текст и нажмите Decode</p>
            </div>
            <div class="decode-controls">
                <textarea id="encoded-text" placeholder="Вставьте зашифрованный текст здесь..."></textarea>
                <div class="decode-actions">
                    <button onclick="quickDecode()" class="btn-decode">
                        <i class="fas fa-unlock"></i> Decode
                    </button>
                    <button onclick="clearDecode()" class="btn-clear">
                        <i class="fas fa-trash"></i> Очистить
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно уведомления -->
    <div id="copy-notification" class="notification-modal">
        <div class="notification-content">
            <i class="fas fa-check-circle"></i>
            <p>Текст скопирован в буфер обмена!</p>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // Копирование зашифрованного текста
    function copyEncryptedText(button) {
        const text = button.getAttribute('data-text');
        navigator.clipboard.writeText(text).then(() => {
            showCopyNotification();
        }).catch(err => {
            console.error('Ошибка копирования:', err);
            alert('Скопируйте текст вручную');
        });
    }
    
    // Показать уведомление о копировании
    function showCopyNotification() {
        const notification = document.getElementById('copy-notification');
        notification.style.display = 'block';
        
        setTimeout(() => {
            notification.style.display = 'none';
        }, 2000);
    }
    
    // Быстрая дешифровка
    function quickDecode() {
        const encodedText = document.getElementById('encoded-text').value.trim();
        const output = document.getElementById('decode-output');
        
        if (!encodedText) {
            output.innerHTML = '<p class="error">Введите текст для дешифровки</p>';
            return;
        }
        
        // Отправка на сервер для дешифровки
        fetch('api/decode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ text: encodedText })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                output.innerHTML = `
                    <div class="decode-result">
                        <h4>Результат дешифровки:</h4>
                        <div class="decoded-content">
                            ${data.decoded}
                        </div>
                        <div class="decode-success">
                            <i class="fas fa-trophy"></i> Достижение получено: "Внимательная личность"
                        </div>
                    </div>
                `;
                
                // Показать уведомление о достижении
                setTimeout(() => {
                    showNotification('Достижение получено: Внимательная личность!', 'success');
                }, 500);
            } else {
                output.innerHTML = `<p class="error">Ошибка дешифровки: ${data.error}</p>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            output.innerHTML = '<p class="error">Ошибка соединения с сервером</p>';
        });
    }
    
    // Очистка поля дешифровки
    function clearDecode() {
        document.getElementById('encoded-text').value = '';
        document.getElementById('decode-output').innerHTML = 
            '<p>Вставьте зашифрованный текст и нажмите Decode</p>';
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
    </script>
    
    <style>
    /* Стили для страницы статей */
    .page-header {
        margin: 30px 0;
        text-align: center;
    }
    
    .search-filter {
        background-color: rgba(20, 20, 20, 0.8);
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        border: 1px solid var(--terminal-border);
    }
    
    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        justify-content: center;
    }
    
    .search-box {
        flex: 1;
        max-width: 400px;
        display: flex;
    }
    
    .search-box input {
        flex: 1;
        padding: 10px 15px;
        background-color: #000;
        border: 1px solid var(--terminal-border);
        border-right: none;
        border-radius: 4px 0 0 4px;
        color: var(--terminal-text);
        font-family: 'Courier New', monospace;
    }
    
    .search-box button {
        background-color: var(--terminal-green);
        color: #000;
        border: none;
        padding: 0 20px;
        border-radius: 0 4px 4px 0;
        cursor: pointer;
        font-weight: bold;
    }
    
    .filter-options {
        display: flex;
        gap: 10px;
    }
    
    .filter-options select {
        padding: 10px;
        background-color: #000;
        border: 1px solid var(--terminal-border);
        color: var(--terminal-text);
        border-radius: 4px;
        font-family: 'Courier New', monospace;
    }
    
    .info-box {
        background-color: rgba(0, 100, 100, 0.2);
        border-left: 4px solid var(--terminal-cyan);
        padding: 15px;
        margin: 20px 0;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .info-box i {
        color: var(--terminal-cyan);
        font-size: 24px;
    }
    
    .articles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin: 30px 0;
    }
    
    .article-card {
        background-color: rgba(25, 25, 25, 0.8);
        border-radius: 8px;
        padding: 20px;
        transition: all 0.3s;
        border: 1px solid var(--terminal-border);
    }
    
    .article-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 255, 255, 0.2);
    }
    
    .article-card.article-encrypted {
        border: 2px dashed var(--terminal-yellow);
        background-color: rgba(50, 50, 0, 0.2);
    }
    
    .article-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .article-level {
        background-color: var(--terminal-purple);
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .encrypted-badge {
        background-color: var(--terminal-yellow);
        color: #000;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .article-title {
        color: var(--terminal-cyan);
        margin-bottom: 10px;
        font-size: 18px;
    }
    
    .article-title a {
        color: inherit;
        text-decoration: none;
    }
    
    .article-title a:hover {
        color: var(--terminal-green);
    }
    
    .article-desc {
        color: #aaa;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 15px;
        min-height: 60px;
    }
    
    .article-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid var(--terminal-border);
    }
    
    .article-date {
        color: #888;
        font-size: 12px;
    }
    
    .btn-copy-encrypted, .btn-read {
        padding: 8px 15px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 14px;
        cursor: pointer;
        border: none;
        transition: all 0.3s;
    }
    
    .btn-copy-encrypted {
        background-color: var(--terminal-yellow);
        color: #000;
    }
    
    .btn-copy-encrypted:hover {
        background-color: #cccc00;
        transform: translateY(-2px);
    }
    
    .btn-read {
        background-color: var(--terminal-green);
        color: #000;
    }
    
    .btn-read:hover {
        background-color: #00cc00;
        transform: translateY(-2px);
    }
    
    .no-results {
        grid-column: 1 / -1;
        text-align: center;
        padding: 50px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
        border: 2px dashed var(--terminal-border);
    }
    
    .no-results i {
        font-size: 48px;
        color: var(--terminal-border);
        margin-bottom: 20px;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin: 40px 0;
    }
    
    .page-link {
        padding: 8px 15px;
        background-color: rgba(0, 0, 0, 0.3);
        color: var(--terminal-text);
        text-decoration: none;
        border-radius: 4px;
        border: 1px solid var(--terminal-border);
        transition: all 0.3s;
    }
    
    .page-link:hover {
        border-color: var(--terminal-green);
        background-color: rgba(0, 255, 0, 0.1);
    }
    
    .page-link.active {
        background-color: var(--terminal-green);
        color: #000;
        font-weight: bold;
    }
    
    .page-dots {
        color: #666;
        padding: 0 5px;
    }
    
    .page-link.next {
        background-color: var(--terminal-purple);
        color: white;
    }
    
    .quick-decode-terminal {
        background-color: var(--terminal-bg);
        border-radius: 8px;
        padding: 20px;
        margin: 40px 0;
        border: 2px solid var(--terminal-green);
    }
    
    .quick-decode-terminal h3 {
        color: var(--terminal-green);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    #decode-output {
        min-height: 100px;
        margin-bottom: 20px;
        background-color: rgba(0, 20, 0, 0.3);
    }
    
    .decode-controls {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 15px;
    }
    
    #encoded-text {
        background-color: #000;
        border: 1px solid var(--terminal-border);
        color: var(--terminal-text);
        padding: 15px;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        resize: vertical;
        min-height: 100px;
    }
    
    .decode-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .btn-decode, .btn-clear {
        padding: 12px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 10px;
        justify-content: center;
    }
    
    .btn-decode {
        background-color: var(--terminal-green);
        color: #000;
    }
    
    .btn-decode:hover {
        background-color: #00cc00;
        transform: translateY(-2px);
    }
    
    .btn-clear {
        background-color: var(--terminal-red);
        color: white;
    }
    
    .btn-clear:hover {
        background-color: #ff3333;
        transform: translateY(-2px);
    }
    
    .decode-result {
        padding: 15px;
        background-color: rgba(0, 0, 0, 0.5);
        border-radius: 4px;
    }
    
    .decoded-content {
        padding: 15px;
        background-color: #000;
        border-radius: 4px;
        margin: 10px 0;
        font-family: 'Courier New', monospace;
        white-space: pre-wrap;
        word-break: break-word;
    }
    
    .decode-success {
        background-color: rgba(255, 215, 0, 0.2);
        border: 1px solid #FFD700;
        color: #FFD700;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    /* Модальное окно уведомления */
    .notification-modal {
        display: none;
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
    }
    
    .notification-content {
        background-color: rgba(0, 255, 0, 0.9);
        color: #000;
        padding: 15px 25px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 15px;
        animation: slideIn 0.3s ease;
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
        .articles-grid {
            grid-template-columns: 1fr;
        }
        
        .search-form {
            flex-direction: column;
        }
        
        .search-box {
            max-width: 100%;
        }
        
        .filter-options {
            flex-direction: column;
            width: 100%;
        }
        
        .decode-controls {
            grid-template-columns: 1fr;
        }
    }
    </style>
</body>
</html>