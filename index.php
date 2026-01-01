<?php
require_once 'config.php';

// Перенаправление в зависимости от статуса авторизации
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: register.php");
}
exit();
?>