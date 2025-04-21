<?php
require_once '../includes/auth.php';
if (!is_admin()) {
    header("Location: ../login.php");
    exit;
}

global $pdo;
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        // Add new plan
        $name = $_POST['name'];
        $image = $_POST['image'];
        $plans = implode(',', array_map(fn($p, $c) => "$p:$c", $_POST['plan_names'], $_POST['plan_costs']));
        $stmt = $pdo->prepare("INSERT INTO subscription_plans (name, image, plans) VALUES (?, ?, ?)");
        $stmt->execute([$name, $image, $plans]);
        $message = "Plan added successfully!";
    } elseif (isset($_POST['delete'])) {
        // Delete plan
        $plan_id = $_POST['plan_id'];
        // Check if the plan is in use by any subscriptions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE plan_id = ? AND status IN ('pending', 'processing', 'active')");
        $stmt->execute([$plan_id]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            $message = "Cannot delete plan: It is currently in use by $count active subscription(s).";
        } else {
            $stmt = $pdo->prepare("DELETE FROM subscription_plans WHERE id = ?");
            $stmt->execute([$plan_id]);
            $message = "Plan deleted successfully!";
        }
    } elseif (isset($_POST['edit'])) {
        // Edit plan
        $plan_id = $_POST['plan_id'];
        $name = $_POST['name'];
        $image = $_POST['image'];
        $plans = implode(',', array_map(fn($p, $c) => "$p:$c", $_POST['plan_names'], $_POST['plan_costs']));
        $stmt = $pdo->prepare("UPDATE subscription_plans SET name = ?, image = ?, plans = ? WHERE id = ?");
        $stmt->execute([$name, $image, $plans, $plan_id]);
        $message = "Plan updated successfully!";
    }
}

$plans = $pdo->query("SELECT * FROM subscription_plans")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Plans | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub Admin</div>
            <a href="index">Dashboard</a>
            <a href="manage_deposits">Manage Deposits</a>
            <a href="manage_subs">Manage Subscriptions</a>
            <a href="manage_plans" class="active">Manage Plans</a>
            <a href="../logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo $_SESSION['email']; ?></div>
            </header>
            <?php if ($message): ?>
                <div class="toast <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                    <span class="icon"><?php echo strpos($message, 'successfully') !== false ? '✔' : '✖'; ?></span>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <div class="loading" id="loading">
                <div class="spinner"></div>
            </div>
            <div class="card">
                <h2>Add New Plan</h2>
                <form method="POST" onsubmit="showLoading()">
                    <input type="text" name="name" placeholder="Plan Name" required>
                    <input type="text" name="image" placeholder="Image URL">
                    <div id="plans-container">
                        <div class="plan-field flex mb-2">
                            <input type="text" name="plan_names[]" placeholder="Plan (e.g., Monthly)" required class="w-1/2 mr-2">
                            <input type="number" name="plan_costs[]" placeholder="Cost (GHS)" step="0.01" required class="w-1/2">
                        </div>
                    </div>
                    <button type="button" onclick="addPlanField()" class="bg-gray-500">Add Another Plan</button>
                    <button type="submit" name="add">Add Plan</button>
                </form>
            </div>
            <div class="card">
                <h2>Existing Plans</h2>
                <?php if (empty($plans)): ?>
                    <p>No plans available.</p>
                <?php else: ?>
                    <div class="plans-container">
                        <?php foreach ($plans as $plan): ?>
                            <div class="plan-item">
                                <div class="plan-details">
                                    <?php if ($plan['image']): ?>
                                        <img src="<?php echo $plan['image']; ?>" alt="<?php echo $plan['name']; ?>" class="plan-image">
                                    <?php else: ?>
                                        <div class="plan-placeholder"><?php echo $plan['name']; ?></div>
                                    <?php endif; ?>
                                    <div class="plan-info">
                                        <h3><?php echo $plan['name']; ?></h3>
                                        <p>Plans: <?php echo $plan['plans']; ?></p>
                                    </div>
                                </div>
                                <div class="plan-actions">
                                    <button onclick="showEditForm(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>', '<?php echo htmlspecialchars($plan['image']); ?>', '<?php echo htmlspecialchars($plan['plans']); ?>')" class="bg-blue-500">Edit</button>
                                    <form method="POST" class="inline" onsubmit="showLoading()">
                                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                        <button type="submit" name="delete" class="bg-red-500">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditForm()">&times;</span>
            <h2>Edit Plan</h2>
            <form method="POST" onsubmit="showLoading()">
                <input type="hidden" name="plan_id" id="edit_plan_id">
                <input type="text" name="name" id="edit_name" placeholder="Plan Name" required>
                <input type="text" name="image" id="edit_image" placeholder="Image URL">
                <div id="edit_plans_container">
                    <!-- Plan fields will be populated dynamically -->
                </div>
                <button type="button" onclick="addEditPlanField()" class="bg-gray-500">Add Another Plan</button>
                <button type="submit" name="edit">Update Plan</button>
            </form>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        function showLoading() {
            document.getElementById('loading').classList.add('active');
        }

        function addPlanField() {
            const container = document.getElementById('plans-container');
            const div = document.createElement('div');
            div.className = 'plan-field flex mb-2';
            div.innerHTML = `
                <input type="text" name="plan_names[]" placeholder="Plan (e.g., Monthly)" required class="w-1/2 mr-2">
                <input type="number" name="plan_costs[]" placeholder="Cost (GHS)" step="0.01" required class="w-1/2">
            `;
            container.appendChild(div);
        }

        function addEditPlanField() {
            const container = document.getElementById('edit_plans_container');
            const div = document.createElement('div');
            div.className = 'plan-field flex mb-2';
            div.innerHTML = `
                <input type="text" name="plan_names[]" placeholder="Plan (e.g., Monthly)" required class="w-1/2 mr-2">
                <input type="number" name="plan_costs[]" placeholder="Cost (GHS)" step="0.01" required class="w-1/2">
            `;
            container.appendChild(div);
        }

        function showEditForm(id, name, image, plans) {
            document.getElementById('edit_plan_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_image').value = image;

            const container = document.getElementById('edit_plans_container');
            container.innerHTML = '';
            const planArray = plans.split(',').map(plan => {
                const [planName, cost] = plan.split(':');
                return { name: planName, cost: cost };
            });

            planArray.forEach(plan => {
                const div = document.createElement('div');
                div.className = 'plan-field flex mb-2';
                div.innerHTML = `
                    <input type="text" name="plan_names[]" value="${plan.name}" placeholder="Plan (e.g., Monthly)" required class="w-1/2 mr-2">
                    <input type="number" name="plan_costs[]" value="${plan.cost}" placeholder="Cost (GHS)" step="0.01" required class="w-1/2">
                `;
                container.appendChild(div);
            });

            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditForm() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>