<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
 $_SESSION = [];
session_destroy();
header("Location: " . BASE_URL . "index.php");
exit;