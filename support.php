<?php
require_once 'includes/auth.php';
if (!is_logged_in()) {
    header("Location: login");
    exit;
}

global $pdo;
$user_id = $_SESSION['user_id'];

// Fetch user's phone number
$user = $pdo->prepare("SELECT phone_number FROM users WHERE id = ?");
$user->execute([$user_id]);
$user_data = $user->fetch();
$user_phone = !empty($user_data['phone_number']) ? '233' . substr($user_data['phone_number'], 1) : null;

// Admin phone number
$admin_phone = '233559118581';

// Pre-trained prompts for auto-responses with numbered mapping
$pre_trained_prompts = [
    1 => [
        'keyword' => 'deposit',
        'response' => 'To deposit funds, go to the "Deposit Funds" section on your dashboard, enter the amount, and follow the instructions to complete the payment.'
    ],
    2 => [
        'keyword' => 'subscription',
        'response' => 'You can subscribe to a plan by visiting the "Available Plans" section on your dashboard and clicking "Subscribe" on the plan of your choice.'
    ],
    3 => [
        'keyword' => 'balance',
        'response' => 'Your current wallet balance is displayed on your dashboard under "PaySub Wallet Balance."'
    ],
    4 => [
        'keyword' => 'help',
        'response' => 'I’m here to assist! Please provide more details, or you can contact support directly via this chat.'
    ]
];

// Check if this is the user's first visit (no chat history)
$messages = $pdo->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE user_id = ?");
$messages->execute([$user_id]);
$chat_count = $messages->fetch()['count'];

if ($chat_count == 0) {
    $greeting = "Hello! Welcome to PaySub Support.\nPlease choose a number for your issue:\n1. Deposit\n2. Subscription\n3. Balance\n4. General Help\nIf you don't see your issue, type your message, and I'll forward it to an agent.";
    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, sender) VALUES (?, ?, 'bot')");
    $stmt->execute([$user_id, $greeting]);
}

// Handle AJAX message submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        // Insert user message into the database
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, sender) VALUES (?, ?, 'user')");
        $stmt->execute([$user_id, $message]);

        // Send SMS to admin for every message
        if ($admin_phone) {
            $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
            $from = 'PaySub';
            $admin_message = "A user sent a message: \"$message\". Please respond in the admin panel.";
            $admin_message_encoded = urlencode($admin_message);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://sms.arkesel.com/sms/api?action=send-sms&api_key=$api_key&to=$admin_phone&from=$from&sms=$admin_message_encoded",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
            curl_exec($curl);
            curl_close($curl);
        }

        // Check if the message is a number corresponding to an issue
        $auto_response = null;
        $message = trim($message);
        if (is_numeric($message) && isset($pre_trained_prompts[(int)$message])) {
            $auto_response = $pre_trained_prompts[(int)$message]['response'];
        } else {
            $auto_response = "I don't recognize that option. Your message has been forwarded to an agent. You'll hear back soon!";
        }

        // Insert bot response
        if ($auto_response) {
            $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, sender) VALUES (?, ?, 'bot')");
            $stmt->execute([$user_id, $auto_response]);
        }

        // Return JSON response for AJAX
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'auto_response' => $auto_response]);
        exit;
    }
}

// Fetch chat messages
$messages = $pdo->prepare("SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
$messages->execute([$user_id]);
$chat_history = $messages->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log a sample bot message to verify content
if (!empty($chat_history)) {
    foreach ($chat_history as $msg) {
        if ($msg['sender'] === 'bot') {
            file_put_contents('debug.log', "Raw bot message: " . $msg['message'] . "\n", FILE_APPEND);
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .chatBtn {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: none;
            background-color: #FFE53B;
            background-image: linear-gradient(147deg, #FFE53B, #FF2525, #FFE53B);
            cursor: pointer;
            padding-top: 3px;
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.164);
            position: relative;
            background-size: 300%;
            background-position: left;
            transition-duration: 1s;
        }

        .tooltip {
            position: absolute;
            top: -40px;
            opacity: 0;
            background-color: rgb(255, 180, 82);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition-duration: .5s;
            pointer-events: none;
            letter-spacing: 0.5px;
        }

        .chatBtn:hover .tooltip {
            opacity: 1;
            transition-duration: .5s;
        }

        .chatBtn:hover {
            background-position: right;
            transition-duration: 1s;
        }

        .chat-container {
            display: none;
            position: fixed;
            bottom: 80px;
            right: 20px;
            z-index: 1000;
        }

        .chat-container.active {
            display: block;
        }

        .typing-indicator {
            display: none;
            font-style: italic;
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .typing-indicator.active {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            background-color: #6b7280;
            border-radius: 50%;
            animation: blink 1.4s infinite both;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes blink {
            0% { opacity: 0.2; }
            20% { opacity: 1; }
            100% { ARISING opacity: 0.2; }
        }
    </style>
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="dashboard">Dashboard</a>
            <a href="deposit">Deposit Funds</a>
            <a href="support" class="active">Support</a>
            <?php if (is_admin()): ?><a href="admin/index">Admin</a><?php endif; ?>
            <a href="logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo $_SESSION['email']; ?></div>
            </header>
            <div class="card">
                <h2>Live Chat Support</h2>
                <p>Click the chat button in the bottom-right corner to start a conversation with our support team.</p>
            </div>
        </div>
    </div>

    <!-- Chat Bubble Button -->
    <button class="chatBtn fixed bottom-5 right-5 z-50">
        <svg height="1.6em" fill="white" xml:space="preserve" viewBox="0 0 1000 1000" y="0px" x="0px" version="1.1">
            <path d="M881.1,720.5H434.7L173.3,941V720.5h-54.4C58.8,720.5,10,671.1,10,610.2v-441C10,108.4,58.8,59,118.9,59h762.2C941.2,59,990,108.4,990,169.3v441C990,671.1,941.2,720.5,881.1,720.5L881.1,720.5z M935.6,169.3c0-30.4-24.4-55.2-54.5-55.2H118.9c-30.1,0-54.5,24.7-54.5,55.2v441c0,30.4,24.4,55.1,54.5,55.1h54.4h54.4v110.3l163.3-110.2H500h381.1c30.1,0,54.5-24.7,54.5-55.1V169.3L935.6,169.3z M717.8,444.8c-30.1,0-54.4-24.7-54.4-55.1c0-30.4,24.3-55.2,54.4-55.2c30.1,0,54.5,24.7,54.5,55.2C772.2,420.2,747.8,444.8,717.8,444.8L717.8,444.8z M500,444.8c-30.1,0-54.4-24.7-54.4-55.1c0-30.4,24.3-55.2,54.4-55.2c30.1,0,54.4,24.7,54.4,55.2C554.4,420.2,530.1,444.8,500,444.8L500,444.8z M282.2,444.8c-30.1,0-54.5-24.7-54.5-55.1c0-30.4,24.4-55.2,54.5-55.2c30.1,0,54.4,24.7,54.4,55.2C336.7,420.2,312.3,444.8,282.2,444.8L282.2,444.8z"></path>
        </svg>
        <span class="tooltip">Chat</span>
    </button>

    <!-- Chat Container -->
    <div class="chat-container max-w-md bg-white shadow-md rounded-lg overflow-hidden" id="chatContainer">
        <div class="flex flex-col h-[400px]">
            <div class="px-4 py-3 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">PaySub Support</h2>
                    <div class="bg-green-400 text-white text-xs px-2 py-1 rounded-full">Online</div>
                </div>
            </div>
            <div class="flex-1 p-3 overflow-y-auto flex flex-col space-y-2" id="chatDisplay">
                <?php foreach ($chat_history as $msg): ?>
                    <div class="chat-message <?php echo $msg['sender'] === 'user' ? 'self-end bg-blue-400' : ($msg['sender'] === 'bot' ? 'self-start bg-gray-300' : 'self-start bg-green-400'); ?> text-gray-900 max-w-xs rounded-lg px-3 py-1.5 text-sm">
                        <strong><?php echo $msg['sender'] === 'user' ? 'You' : ($msg['sender'] === 'bot' ? 'Bot' : 'Support'); ?>:</strong>
                        <?php echo nl2br(htmlspecialchars($msg['message'], ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>
                        <small class="block text-xs opacity-75"><?php echo $msg['created_at']; ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="px-3 py-2 border-t border-gray-200">
                <div class="flex gap-2">
                    <input placeholder="Type your message..." class="flex-1 p-2 border rounded-lg bg-gray-50 border-gray-300 text-sm" id="chatInput" type="text">
                    <button class="bg-blue-400 hover:bg-blue-500 text-white font-bold py-1.5 px-3 rounded-lg transition duration-300 ease-in-out text-sm" id="sendButton">Send</button>
                </div>
                <div class="typing-indicator" id="typingIndicator">
                    <span>Bot is typing</span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
                <div class="text-gray-500 text-xs mt-2 text-center">Made by Samuel Bempah</div>
            </div>
        </div>
    </div>

    <!-- Audio element for soothing success sound from hosted link -->
    <audio id="chatAlertSound" preload="auto" src="https://paysub.great-site.net/ping.mp3"></audio>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Function to play alert sound with error handling
        function playAlertSound() {
            const chatAlertSound = document.getElementById('chatAlertSound');
            chatAlertSound.currentTime = 0; // Reset to start
            const playPromise = chatAlertSound.play();
            if (playPromise !== undefined) {
                playPromise.catch(error => {
                    console.error('Audio play failed:', error);
                    // Fallback: Try playing after a short delay
                    setTimeout(() => {
                        chatAlertSound.play().catch(err => console.error('Retry failed:', err));
                    }, 100);
                });
            }
        }

        // Toggle chat container visibility
        const chatBtn = document.querySelector('.chatBtn');
        const chatContainer = document.getElementById('chatContainer');
        chatBtn.addEventListener('click', function() {
            chatContainer.classList.toggle('active');
            if (chatContainer.classList.contains('active')) {
                const chatBox = document.getElementById('chatDisplay');
                chatBox.scrollTop = chatBox.scrollHeight;
                playAlertSound();
            }
        });

        // Auto-open chat after 10 seconds
        window.onload = function() {
            const chatBox = document.getElementById('chatDisplay');
            chatBox.scrollTop = chatBox.scrollHeight;
            setTimeout(() => {
                if (!chatContainer.classList.contains('active')) {
                    chatContainer.classList.add('active');
                    chatBox.scrollTop = chatBox.scrollHeight;
                    playAlertSound();
                }
            }, 10000); // 10s delay
        };

        // Handle message sending via AJAX
        document.getElementById('sendButton').addEventListener('click', function() {
            const chatInput = document.getElementById('chatInput');
            const message = chatInput.value.trim();
            if (message) {
                // Add user message to chat display
                const chatDisplay = document.getElementById('chatDisplay');
                const userMessage = document.createElement('div');
                userMessage.className = 'chat-message self-end bg-blue-400 text-gray-900 max-w-xs rounded-lg px-3 py-1.5 text-sm';
                userMessage.innerHTML = `<strong>You:</strong> ${message}<small class="block text-xs opacity-75">${new Date().toLocaleString()}</small>`;
                chatDisplay.appendChild(userMessage);
                chatDisplay.scrollTop = chatDisplay.scrollHeight;

                // Clear input
                chatInput.value = '';

                // Delay before showing typing indicator
                setTimeout(() => {
                    // Show typing indicator
                    const typingIndicator = document.getElementById('typingIndicator');
                    typingIndicator.classList.add('active');

                    // Send message via AJAX
                    fetch('support.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'message=' + encodeURIComponent(message)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Simulate typing delay (3 seconds)
                            setTimeout(() => {
                                typingIndicator.classList.remove('active');
                                // Add bot response
                                const botMessage = document.createElement('div');
                                botMessage.className = 'chat-message self-start bg-gray-300 text-gray-900 max-w-xs rounded-lg px-3 py-1.5 text-sm';
                                botMessage.innerHTML = `<strong>Bot:</strong> ${data.auto_response.replace(/\n/g, '<br>')}<small class="block text-xs opacity-75">${new Date().toLocaleString()}</small>`;
                                chatDisplay.appendChild(botMessage);
                                chatDisplay.scrollTop = chatDisplay.scrollHeight;
                                playAlertSound();
                            }, 3000); // 3s typing delay
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        typingIndicator.classList.remove('active');
                    });
                }, 3000); // 3s delay before typing indicator
            }
        });

        // Allow sending message with Enter key
        document.getElementById('chatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('sendButton').click();
            }
        });
    </script>
</body>
</html>