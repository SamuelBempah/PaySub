<?php
session_start();
require_once 'includes/db.php';

global $pdo;

// Fetch status from database
try {
    $stmt = $pdo->query("SELECT * FROM server_status");
    $status = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status[$row['component_key']] = [
            'name' => $row['name'],
            'status' => $row['status'],
            'details' => $row['details'],
        ];
    }
} catch (Exception $e) {
    file_put_contents('status_error.log', date('Y-m-d H:i:s') . " - Database: " . $e->getMessage() . "\n", FILE_APPEND);
    $status['backend']['status'] = 'Offline';
    $status['backend']['details'] = 'Database connection failed.';
}

// Check actual database connectivity
try {
    $pdo->query("SELECT 1");
    if (!isset($status['backend']['status']) || $status['backend']['status'] !== 'Offline') {
        $status['backend']['status'] = 'Online';
        $status['backend']['details'] = 'Database and server running smoothly.';
    }
} catch (Exception $e) {
    $status['backend']['status'] = 'Offline';
    $status['backend']['details'] = 'Database connection failed.';
    file_put_contents('status_error.log', date('Y-m-d H:i:s') . " - Backend: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Status | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 0 20px rgba(0, 123, 255, 0.2);
        }
        .sidebar:hover {
            box-shadow: 0 0 30px rgba(0, 123, 255, 0.4);
        }
        .sidebar .logo {
            font-size: 1.8rem;
            color: #007bff;
            margin-bottom: 30px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        .sidebar a {
            display: block;
            color: #e0e0e0;
            padding: 10px;
            text-decoration: none;
            margin: 10px 0;
            border-radius: 4px;
            transition: background 0.3s, transform 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #007bff;
            color: #fff;
            transform: scale(1.05);
        }
        .main-content {
            margin-left: 250px;
            padding: 40px;
            width: calc(100% - 250px);
            animation: fadeIn 1s ease-in;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f0f1c;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.3);
            transition: transform 0.3s;
        }
        .header:hover {
            transform: translateY(-3px);
        }
        .hamburger {
            font-size: 1.5rem;
            cursor: pointer;
            color: #e0e0e0;
            display: none;
            transition: transform 0.3s;
        }
        .hamburger:hover {
            transform: rotate(90deg);
        }
        .user-info {
            color: #e0e0e0;
            font-size: 1rem;
            transition: color 0.3s;
        }
        .user-info:hover {
            color: #007bff;
        }
        .status-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .status-container h1 {
            font-size: 2.8rem;
            color: #007bff;
            margin-bottom: 1rem;
            text-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
            text-align: center;
            animation: glow 2s infinite alternate;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .status-card {
            background: #0f0f1c;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.3);
            opacity: 0;
            animation: slideIn 0.5s forwards;
        }
        .status-card:nth-child(1) { animation-delay: 0.1s; }
        .status-card:nth-child(2) { animation-delay: 0.2s; }
        .status-card:nth-child(3) { animation-delay: 0.3s; }
        .status-card:nth-child(4) { animation-delay: 0.4s; }
        .status-card:nth-child(5) { animation-delay: 0.5s; }
        .status-card:nth-child(6) { animation-delay: 0.6s; }
        .status-card:nth-child(7) { animation-delay: 0.7s; }
        .status-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 0 25px rgba(0, 123, 255, 0.5);
        }
        .status-card h3 {
            font-size: 1.5rem;
            color: #e0e0e0;
            margin-bottom: 10px;
            transition: color 0.3s;
        }
        .status-card:hover h3 {
            color: #007bff;
        }
        .status-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        .status-indicator::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: left 0.3s;
        }
        .status-indicator:hover::before {
            left: 100%;
        }
        .status-indicator.online {
            background: #28a745;
            color: #fff;
            animation: pulseStatus 2s infinite;
        }
        .status-indicator.offline {
            background: #dc3545;
            color: #fff;
        }
        .status-indicator.degraded {
            background: #ffc107;
            color: #000;
        }
        .status-card p {
            font-size: 0.9rem;
            color: #b0b0b0;
            margin-top: 10px;
            line-height: 1.4;
            transition: color 0.3s;
        }
        .status-card:hover p {
            color: #e0e0e0;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        @keyframes glow {
            0% { text-shadow: 0 0 10px rgba(0, 123, 255, 0.5); }
            100% { text-shadow: 0 0 20px rgba(0, 123, 255, 0.8); }
        }
        @keyframes pulseStatus {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
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
            <a href="dashboard">Dashboard</a>
            <a href="status" class="active">Server Status</a>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
                <a href="update_status">Update Status</a>
            <?php endif; ?>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?></div>
            </header>
            <div class="status-container">
                <h1>Server Status</h1>
                <div class="status-grid">
                    <?php foreach ($status as $key => $component): ?>
                        <div class="status-card">
                            <h3><?php echo htmlspecialchars($component['name']); ?></h3>
                            <div class="status-indicator <?php echo strtolower($component['status']); ?>">
                                <?php echo htmlspecialchars($component['status']); ?>
                            </div>
                            <p><?php echo htmlspecialchars($component['details']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Refresh animation on status change
        document.querySelectorAll('.status-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.animation = 'none';
                card.offsetHeight; // Trigger reflow
                card.style.animation = 'slideIn 0.5s forwards';
            });
        });
    </script>
</body>
</html>