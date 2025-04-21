<?php
// Function to handle user logout
function logout() {
    session_start();
    session_destroy();
    header('Location: login');
    exit();
}

require_once 'includes/auth.php';
logout();
?>