<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../config/database.php';
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='container mt-5'><h4>You are not logged in.</h4></div>";
    exit();
}

$current_user_id = $_SESSION['user_id'];
$selected_user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;

/* ================= SEND MESSAGE ================= */
if (isset($_POST['message_text']) && isset($_POST['receiver_id'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message_text']);

    if ($message != "") {
        $stmt = $pdo->prepare("INSERT INTO messages(sender_id, receiver_id, message_text) VALUES(?,?,?)");
        $stmt->execute([$current_user_id, $receiver_id, $message]);
    }
    exit();
}

/* ================= LOAD MESSAGES ================= */
if (isset($_GET['load_messages']) && isset($_GET['chat_user'])) {
    $chat_user = intval($_GET['chat_user']);

    $stmt = $pdo->prepare("
        SELECT * FROM messages
        WHERE (sender_id=? AND receiver_id=?)
        OR (sender_id=? AND receiver_id=?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$current_user_id, $chat_user, $chat_user, $current_user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$messages) {
        echo "<div class='text-center text-muted'>No messages yet. Start chatting!</div>";
    } else {
        foreach ($messages as $msg) {
            $class = $msg['sender_id'] == $current_user_id ? 'sent' : 'received';
            echo "<div class='message $class'>" . htmlspecialchars($msg['message_text']) . "</div>";
        }
    }

    exit();
}

/* ================= SELECTED USER ================= */
$selectedUser = null;

// Get all users except current, using correct column names
$stmt = $pdo->prepare("SELECT id, first_name, last_name, profile_image FROM users WHERE id != ?");
$stmt->execute([$current_user_id]);
$chatUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no user selected, default to first in list
if ($selected_user_id == 0 && count($chatUsers) > 0) {
    $selected_user_id = $chatUsers[0]['id'];
}

if ($selected_user_id != 0) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_image FROM users WHERE id = ?");
    $stmt->execute([$selected_user_id]);
    $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
.chat-wrapper { margin-top: 5%; }
.chat-container { display: flex; height: 75vh; background: #f0f2f5; border-radius: 10px; overflow: hidden; }
.chat-sidebar { width: 280px; background: #fff; border-right: 1px solid #ddd; padding: 15px; overflow-y: auto; }
.chat-user { display: flex; align-items: center; padding: 8px; border-radius: 8px; text-decoration: none; color: #333; margin-bottom: 8px; transition: 0.2s; }
.chat-user:hover { background: #f2f2f2; }
.chat-user img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; object-fit: cover; }
.chat-main { flex: 1; display: flex; flex-direction: column; background: #e5ddd5; }
.chat-header { background: #fff; padding: 12px; border-bottom: 1px solid #ddd; display: flex; align-items: center; }
.chat-header img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; object-fit: cover; }
.chat-messages { flex: 1; padding: 15px; overflow-y: auto; }
.message { max-width: 60%; padding: 10px 14px; border-radius: 18px; margin-bottom: 10px; font-size: 14px; word-wrap: break-word; }
.sent { background: #25d366; color: #fff; margin-left: auto; }
.received { background: #fff; }
.chat-input-area { background: #fff; padding: 12px; border-top: 1px solid #ddd; }
.chat-input-wrapper { display: flex; align-items: center; background: #f0f2f5; border-radius: 30px; padding: 6px 10px; }
.chat-input { flex: 1; border: none; outline: none; background: transparent; padding: 10px; }
.send-btn { background: #25d366; border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; }
</style>

<div class="container chat-wrapper">
    <div class="chat-container">

        <!-- SIDEBAR -->
        <div class="chat-sidebar">
            <h5>Chats</h5>
            <button data-bs-toggle="modal" data-bs-target="#newChatModal" class="btn btn-success w-100 mb-3">+ New Chat</button>
            <?php foreach ($chatUsers as $user): 
                $name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                $img = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
            ?>
                <a href="chats.php?user=<?= $user['id'] ?>" class="chat-user">
                    <img src="../uploads/profile/<?= $img ?>" alt="profile">
                    <?= $name ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- MAIN CHAT -->
        <div class="chat-main">

            <?php if ($selectedUser): 
                $selected_name = htmlspecialchars($selectedUser['first_name'] . ' ' . $selectedUser['last_name']);
                $selected_img = !empty($selectedUser['profile_image']) ? $selectedUser['profile_image'] : 'default.png';
            ?>
                <div class="chat-header">
                    <img src="../uploads/profile/<?= $selected_img ?>" alt="profile">
                    <b><?= $selected_name ?></b>
                </div>

                <div class="chat-messages" id="chatBox"></div>

                <form id="sendForm" class="chat-input-area">
                    <input type="hidden" name="receiver_id" value="<?= $selected_user_id ?>">
                    <div class="chat-input-wrapper">
                        <input type="text" name="message_text" id="msgInput" class="chat-input" placeholder="Type message..." required>
                        <button type="submit" class="send-btn">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </form>

            <?php else: ?>
                <div class="d-flex justify-content-center align-items-center h-100">
                    <h5>Select a user to start chatting</h5>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="newChatModal">
    <div class="modal-dialog">
        <div class="modal-content p-3">
            <h5>Start New Chat</h5>
            <hr>
            <?php foreach ($chatUsers as $u): 
                $name = htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
                $img = !empty($u['profile_image']) ? $u['profile_image'] : 'default.png';
            ?>
                <a href="chats.php?user=<?= $u['id'] ?>" class="chat-user">
                    <img src="../uploads/profile/<?= $img ?>" alt="profile">
                    <?= $name ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
let selectedUser = "<?= $selected_user_id ?>";

function loadMessages() {
    if (selectedUser == 0) return;
    fetch("chats.php?load_messages=1&chat_user=" + selectedUser)
        .then(res => res.text())
        .then(data => {
            let box = document.getElementById("chatBox");
            box.innerHTML = data;
            box.scrollTop = box.scrollHeight;
        });
}

setInterval(loadMessages, 2000);
loadMessages();

document.getElementById("sendForm")?.addEventListener("submit", function(e){
    e.preventDefault();
    let form = new FormData(this);

    fetch("chats.php", {
        method: "POST",
        body: form
    }).then(() => {
        document.getElementById("msgInput").value = "";
        loadMessages();
    });
});
</script>