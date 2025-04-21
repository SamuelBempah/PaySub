<?php
session_start();
require_once 'db.php';

function signup($email, $password, $full_name, $phone_number) {
    global $pdo;
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return "Email already exists.";
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into the database
    $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, phone_number, balance, is_admin) VALUES (?, ?, ?, ?, 0.00, 0)");
    $stmt->execute([$email, $hashed_password, $full_name, $phone_number]); // Line 20
    
    return "Signup successful! Please login.";
}

function login($email, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        return "Login successful!";
    }
    
    return "Invalid email or password.";
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['is_admin'] == 1;
}

function update_balance($user_id, $amount) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
}
?>