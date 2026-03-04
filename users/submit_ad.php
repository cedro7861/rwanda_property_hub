<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch admin user (first admin) for contact
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();
$admin_id = $admin ? $admin['id'] : 0;

// Check if user has paid subscription (subscription = 1)
$stmt = $pdo->prepare("SELECT subscription FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$is_paid = ($stmt->fetchColumn() == 1);

$error = '';
$success = '';

// Handle message to admin (if not paid)
if (!$is_paid && isset($_POST['send_message_to_admin'])) {
    $message = trim($_POST['message']);
    if (!empty($message) && $admin_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->execute([$user_id, $admin_id, $message]);
        $success = "Your message has been sent to the admin. You will be contacted shortly.";
    } else {
        $error = "Please enter a message.";
    }
}

// Handle ad submission (only if paid)
if ($is_paid && isset($_POST['submit_ad'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $target_url = trim($_POST['target_url']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Validate dates
    if (empty($start_date) || empty($end_date)) {
        $error = "Start and end dates are required.";
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $error = "End date must be after start date.";
    }

    // Handle file upload
    if (empty($error) && isset($_FILES['ad_file']) && $_FILES['ad_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['ad_file'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime'];
        $max_size = 40 * 1024 * 1024; // 40MB

        if (!in_array($file['type'], $allowed_types)) {
            $error = "Invalid file type. Allowed: JPG, PNG, GIF, MP4, MOV.";
        } elseif ($file['size'] > $max_size) {
            $error = "File too large. Max 10MB.";
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'ad_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../uploads/ads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $destination = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Determine media type
                $media_type = 'image';
                if (strpos($file['type'], 'video') !== false) {
                    $media_type = 'video';
                } elseif ($file['type'] === 'image/gif') {
                    $media_type = 'gif';
                }

                // Insert into database
                $stmt = $pdo->prepare("
                    INSERT INTO ads (user_id, title, description, media_type, file_path, target_url, start_date, end_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$user_id, $title, $description, $media_type, $filename, $target_url, $start_date, $end_date]);

                $success = "Ad submitted successfully! It will be reviewed by admin.";
            } else {
                $error = "Failed to upload file.";
            }
        }
    } elseif (empty($error)) {
        $error = "Please select a file.";
    }
}
include '../includes/header.php';

?>
<div class="container mt-4">
    <h2>Submit New Ad</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($is_paid): ?>
        <!-- Ad submission form for users with paid subscription -->
        <form method="POST" enctype="multipart/form-data" class="mt-3">
            <div class="mb-3">
                <label for="title" class="form-label">Ad Title</label>
                <input type="text" class="form-control" id="title" name="title" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description (optional)</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label for="target_url" class="form-label">Target URL (where ad links to)</label>
                <input type="url" class="form-control" id="target_url" name="target_url" placeholder="https://example.com">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="ad_file" class="form-label">Upload Ad (Image, GIF, Video – max 10MB)</label>
                <input type="file" class="form-control" id="ad_file" name="ad_file" accept="image/*,video/*" required>
            </div>
            <button type="submit" name="submit_ad" class="btn btn-success">Submit Ad</button>
        </form>
    <?php else: ?>
        <!-- Contact admin form for users without paid subscription -->
        <div class="alert alert-warning">You need a paid subscription to submit ads. Please contact the admin to inquire about subscription plans.</div>
        <?php if ($admin_id > 0): ?>
            <form method="POST" class="mt-3">
                <div class="mb-3">
                    <label for="message" class="form-label">Your Message to Admin</label>
                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="I would like to know about subscription plans..." required></textarea>
                </div>
                <button type="submit" name="send_message_to_admin" class="btn btn-primary">Send Message</button>
            </form>
        <?php else: ?>
            <div class="alert alert-danger">Unable to contact admin. Please try again later.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>