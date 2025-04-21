<?php
require_once 'includes/auth.php';
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

global $pdo;
$plans = $pdo->query("SELECT * FROM subscription_plans")->fetchAll(PDO::FETCH_ASSOC);
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $plan_id = $_POST['plan_id'];
    $chosen_plan = $_POST['chosen_plan'];
    $credentials = $_POST['credentials'];
    $stmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, chosen_plan, credentials) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $plan_id, $chosen_plan, $credentials]);
    $message = "Subscription submitted! Awaiting admin approval.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="dashboard">Dashboard</a>
            <a href="deposit">Deposit Funds</a>
            <a href="subscriptions" class="active">Subscriptions</a>
            <?php if (is_admin()): ?><a href="admin/index">Admin</a><?php endif; ?>
            <a href="logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo $_SESSION['email']; ?></div>
            </header>
            <?php if ($message): ?>
                <div class="toast success">
                    <span class="icon">✔</span>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <div class="loading" id="loading">
                <div class="spinner"></div>
            </div>
            <div class="card">
                <h2>Available Subscriptions</h2>
                <?php foreach ($plans as $plan): ?>
                    <div class="card mb-4">
                        <h2><?php echo $plan['name']; ?></h2>
                        <?php if ($plan['image']): ?>
                            <img src="<?php echo $plan['image']; ?>" alt="<?php echo $plan['name']; ?>" class="w-24 h-24 object-cover my-2">
                        <?php endif; ?>
                        <form method="POST" onsubmit="showLoading()">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                            <select name="chosen_plan" class="w-full">
                                <?php foreach (explode(',', $plan['plans']) as $option): ?>
                                    <?php [$name, $cost] = explode(':', $option); ?>
                                    <option value="<?php echo $name; ?>"><?php echo "$name - GHS $cost"; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="credentials" placeholder="Enter login credentials" required>
                            <button type="submit">Subscribe</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
        function showLoading() {
            document.getElementById('loading').classList.add('active');
        }
    </script>
</body>
</html>