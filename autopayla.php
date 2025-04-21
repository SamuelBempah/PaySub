<?php
session_start();
require_once 'includes/auth.php'; // Include auth.php for is_logged_in()

// Redirect to login if not authenticated
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet AutoPayla 1 - PaySub's Trained Bot</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css"> <!-- Assuming shared styles -->
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); /* Dark, techy gradient */
            color: #e0e0e0;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }
        .barter-app {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: #0f0f1c;
            padding: 20px;
            position: fixed;
            height: 100%;
            transition: transform 0.3s ease;
        }
        .sidebar .logo {
            font-size: 1.8rem;
            color: #007bff;
            margin-bottom: 30px;
            text-align: center;
        }
        .sidebar a {
            display: block;
            color: #e0e0e0;
            padding: 10px;
            text-decoration: none;
            margin: 10px 0;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #007bff;
            color: #fff;
        }
        .main-content {
            margin-left: 250px;
            padding: 40px;
            width: calc(100% - 250px);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f0f1c;
            padding: 15px 30px;
            border-radius: 8px;
        }
        .hamburger {
            font-size: 1.5rem;
            cursor: pointer;
            color: #e0e0e0;
            display: none;
        }
        .user-info {
            color: #e0e0e0;
            font-size: 1rem;
        }
        .autopayla-container {
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
        }
        .autopayla-container h1 {
            font-size: 2.8rem;
            color: #007bff;
            margin-bottom: 1rem;
            text-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }
        .autopayla-container p {
            font-size: 1.2rem;
            color: #b0b0b0;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .bot-terminal {
            background: #0f0f1c;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
            font-family: 'Courier New', monospace;
            color: #00ff00;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.3);
        }
        .terminal-header {
            background: #333;
            padding: 5px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #007bff;
        }
        .terminal-title {
            font-size: 0.9rem;
            color: #e0e0e0;
        }
        .terminal-controls span {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: 5px;
        }
        .terminal-controls .red { background: #ff5555; }
        .terminal-controls .yellow { background: #ffaa00; }
        .terminal-controls .green { background: #55ff55; }
        .bot-terminal::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(transparent, rgba(0, 123, 255, 0.1));
            animation: scanline 4s linear infinite;
        }
        .bot-terminal::after {
            content: '0101 1010 0011 1100';
            position: absolute;
            top: 0;
            right: 10px;
            color: rgba(0, 255, 0, 0.2);
            font-size: 0.8rem;
            animation: datastream 10s linear infinite;
        }
        .terminal-line {
            opacity: 0;
            margin: 5px 0;
            white-space: nowrap;
            overflow: hidden;
        }
        .terminal-line.active {
            opacity: 1;
            animation: type 0.5s steps(40, end);
        }
        .terminal-line.success {
            color: #00ff00; /* Green for success */
        }
        @keyframes type {
            from { width: 0; }
            to { width: 100%; }
        }
        @keyframes scanline {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(100%); }
        }
        @keyframes datastream {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(100%); }
        }
        .bot-terminal.flicker {
            animation: flicker 0.1s infinite alternate;
        }
        @keyframes flicker {
            0% { opacity: 1; }
            100% { opacity: 0.95; }
        }
        .bot-animation {
            margin: 30px 0;
            position: relative;
        }
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #333;
            border-radius: 5px;
            margin-top: 20px;
            overflow: hidden;
        }
        .progress {
            width: 0;
            height: 100%;
            background: #28a745;
            animation: load 5s ease-in-out infinite;
        }
        @keyframes load {
            0% { width: 0; }
            50% { width: 100%; }
            100% { width: 0; }
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .hamburger {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="dashboard" class="active">Dashboard</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Guest'); ?></div>
            </header>
            <div class="autopayla-container">
                <h1>Meet AutoPayla 1</h1>
                <p>
                    AutoPayla 1 is PaySub’s specialized bot, engineered to manage your subscriptions effortlessly. 
                    Trained for two weeks during beta testing, it navigates websites, securely handles credentials, 
                    applies payments, and sends SMS updates—all without you lifting a finger. 
                    This isn’t AI; it’s a purpose-built bot, optimized for reliability and ready for future upgrades.
                </p>
                <div class="bot-animation">
                    <div class="bot-terminal">
                        <div class="terminal-header">
                            <div class="terminal-title">AutoPayla 1: Subscription Engine v1.0</div>
                            <div class="terminal-controls">
                                <span class="red"></span>
                                <span class="yellow"></span>
                                <span class="green"></span>
                            </div>
                        </div>
                        <div class="terminal-line">> Initializing AutoPayla 1...</div>
                        <div class="terminal-line">> Connecting to subscription platform...</div>
                        <div class="terminal-line">> HTTP GET /login [200 OK]</div>
                        <div class="terminal-line">> Encrypting credentials with AES-256...</div>
                        <div class="terminal-line">> Inserting credentials...</div>
                        <div class="terminal-line">> Validating subscription plan...</div>
                        <div class="terminal-line">> Verifying card details...</div>
                        <div class="terminal-line payment-method">> Applying payment method: Visa ending in 3649...</div>
                        <div class="terminal-line">> Sending SMS: Subscription processing... [DELIVERED]</div>
                        <div class="terminal-line">> HTTP POST /api/payment [200 OK]</div>
                        <div class="terminal-line">> Confirming transaction receipt...</div>
                        <div class="terminal-line">> Sending SMS: Subscription active... [DELIVERED]</div>
                        <div class="terminal-line">> Updating subscription status...</div>
                        <div class="terminal-line success">> Success! Subscription active.</div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress"></div>
                    </div>
                </div>
                <p>
                    AutoPayla 1 handles everything, from secure logins to payment initialization, 
                    keeping you informed with SMS updates every step of the way.
                </p>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Terminal animations
        document.addEventListener('DOMContentLoaded', () => {
            const terminal = document.querySelector('.bot-terminal');
            const lines = document.querySelectorAll('.terminal-line');
            let delay = 0;

            // Flicker effect for 2 seconds
            terminal.classList.add('flicker');
            setTimeout(() => {
                terminal.classList.remove('flicker');
            }, 2000);

            // Randomly select card (Visa or Mastercard)
            const cards = ['Visa ending in 3649', 'Mastercard ending in 6003'];
            const selectedCard = cards[Math.floor(Math.random() * cards.length)];
            document.querySelector('.payment-method').textContent = `> Applying payment method: ${selectedCard}...`;

            // Typing animation for each line
            lines.forEach((line, index) => {
                const isProcessing = line.textContent.includes('...') || line.classList.contains('success');
                const lineDelay = isProcessing ? 1500 : 800; // Slower for processing lines
                setTimeout(() => {
                    line.classList.add('active');
                }, delay);
                delay += lineDelay;
            });
        });
    </script>
</body>
</html>