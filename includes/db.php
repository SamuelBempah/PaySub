<?php
$host = 'sql213.infinityfree.com';
$dbname = 'if0_38722729_subscription_platform';
$username = 'if0_38722729'; // Default XAMPP MySQL user
$password = 'awKlGo9ZBtEGLV';     // Default XAMPP MySQL password (empty)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>