<?php
// dashboard/index.php — Role-оор чиглүүлэгч
require_once __DIR__ . '/../middleware.php';
requireLogin();
loadUserRole($pdo);
redirectToDashboard();
