<?php
session_start();
require '../config/database.php';

// Restrict access to buyers or tenants only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'buyer' && $_SESSION['role'] !== 'tenant')) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

/* ================= FETCH USER DATA ================= */
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$full_name = $user['first_name'] . " " . $user['last_name'];
$profile_image = $user['profile_image'] ?? 'default.png';

/* ================= STATISTICS ================= */
// Total saved properties
$stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_favorites = $stmt->fetchColumn();

// Unread messages
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetchColumn();

// Total properties viewed (if you track views)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM property_views WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_views = $stmt->fetchColumn();

// If tenant: total rental payments made (paid)
if ($role === 'tenant') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM rental_payments 
        WHERE tenant_id = ? AND payment_status = 'paid'
    ");
    $stmt->execute([$user_id]);
    $total_payments = $stmt->fetchColumn();
} else {
    $total_payments = 0;
}

/* ================= RECENT SAVED PROPERTIES ================= */
$stmt = $pdo->prepare("
    SELECT f.*, p.title, p.price, p.currency, p.status, p.property_type, p.id as property_id,
           d.namedistrict,
           (SELECT image_url FROM property_images 
            WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM favorites f
    JOIN properties p ON f.property_id = p.id
    LEFT JOIN districts d ON p.district = d.districtcode
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
    LIMIT 4
");
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= RECENT MESSAGES ================= */
$stmt = $pdo->prepare("
    SELECT m.*, 
           u.first_name, u.last_name,
           p.title as property_title
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    LEFT JOIN properties p ON m.property_id = p.id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($role) ?> Dashboard - RwandaPropertyHub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }
        body {
            background-color: #f4f6f9;
        }
        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 50px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        .logo {
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
        }
        .nav-links a {
            margin-right: 20px;
            text-decoration: none;
            color: #333;
        }
        .user-menu {
            position: relative;
            cursor: pointer;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .dropdown {
            position: absolute;
            right: 0;
            top: 50px;
            background: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border-radius: 8px;
            display: none;
            min-width: 180px;
        }
        .dropdown a {
            display: block;
            padding: 12px;
            text-decoration: none;
            color: #333;
        }
        .dropdown a:hover {
            background: #f4f6f9;
        }
        /* Main content */
        .content {
            padding: 100px 50px 50px;
        }
        h1 {
            margin-bottom: 30px;
            font-size: 28px;
        }
        .welcome {
            margin-bottom: 20px;
            font-size: 18px;
        }
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #666;
        }
        .stat-card p {
            font-size: 28px;
            font-weight: bold;
            color: #28a745;
            margin: 0;
        }
        /* Recent sections */
        .recent-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .recent-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .recent-card h2 {
            margin-bottom: 20px;
            font-size: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .property-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .property-item {
            border: 1px solid #eee;
            border-radius: 6px;
            overflow: hidden;
        }
        .property-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }
        .property-info {
            padding: 8px;
        }
        .property-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
        }
        .property-info .price {
            color: #28a745;
            font-weight: bold;
            font-size: 12px;
        }
        .message-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .message-item:last-child {
            border-bottom: none;
        }
        .message-sender {
            font-weight: 600;
        }
        .message-preview {
            color: #666;
            font-size: 14px;
            margin: 3px 0;
        }
        .message-date {
            font-size: 12px;
            color: #999;
        }
        .no-image {
            background: #eee;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 12px;
        }
        @media (max-width: 900px) {
            .navbar {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
            }
            .content {
                padding: 150px 20px 30px;
            }
            .recent-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="content">
    <h1><?= ucfirst($role) ?> Dashboard</h1>
    <div class="welcome">Welcome, <?= htmlspecialchars($full_name) ?>!</div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Saved Properties</h3>
            <p><?= $total_favorites ?></p>
        </div>
        <div class="stat-card">
            <h3>Unread Messages</h3>
            <p><?= $unread_messages ?></p>
        </div>
        <div class="stat-card">
            <h3>Properties Viewed</h3>
            <p><?= $total_views ?></p>
        </div>
        <?php if ($role === 'tenant'): ?>
        <div class="stat-card">
            <h3>Payments Made</h3>
            <p><?= $total_payments ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity (no links) -->
    <div class="recent-grid">
        <!-- Recent Saved Properties -->
        <div class="recent-card">
            <h2>Recent Saved Properties</h2>
            <?php if (empty($favorites)): ?>
                <p>You haven't saved any properties yet.</p>
            <?php else: ?>
                <div class="property-list">
                    <?php foreach ($favorites as $fav): 
                        $price_display = $fav['status'] == 'for_rent' 
                            ? number_format($fav['price']) . ' ' . $fav['currency'] . '/mo'
                            : number_format($fav['price']) . ' ' . $fav['currency'];
                    ?>
                    <div class="property-item">
                        <?php if (!empty($fav['image']) && file_exists('../uploads/property/' . $fav['image'])): ?>
                            <img src="../uploads/property/<?= htmlspecialchars($fav['image']) ?>" alt="<?= htmlspecialchars($fav['title']) ?>">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                        <div class="property-info">
                            <h4><?= htmlspecialchars(substr($fav['title'], 0, 15)) . (strlen($fav['title']) > 15 ? '...' : '') ?></h4>
                            <div class="price"><?= $price_display ?></div>
                            <!-- Link removed -->
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- "View All" link removed -->
            <?php endif; ?>
        </div>

        <!-- Recent Messages -->
        <div class="recent-card">
            <h2>Recent Messages</h2>
            <?php if (empty($messages)): ?>
                <p>No messages yet.</p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                <div class="message-item">
                    <div class="message-sender">
                        <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                        <?php if (!$msg['is_read']): ?>
                            <span style="background:#28a745; color:white; padding:2px 6px; border-radius:10px; font-size:10px; margin-left:8px;">New</span>
                        <?php endif; ?>
                    </div>
                    <div class="message-preview">
                        <?= htmlspecialchars(substr($msg['message_text'], 0, 50)) . (strlen($msg['message_text']) > 50 ? '...' : '') ?>
                    </div>
                    <div class="message-date">
                        <?= date('M j, Y', strtotime($msg['created_at'])) ?>
                        <?php if ($msg['property_title']): ?>
                            · Re: <?= htmlspecialchars(substr($msg['property_title'], 0, 20)) ?>
                        <?php endif; ?>
                    </div>
                    <!-- "Read More" link removed -->
                </div>
                <?php endforeach; ?>
                <!-- "View All" link removed -->
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Profile Modal -->
<div class="modal" id="profileModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center;">
    <div class="modal-content" style="background:white; padding:30px; width:400px; border-radius:10px;">
        <span class="close" onclick="closeProfileModal()" style="float:right; cursor:pointer; font-weight:bold;">&times;</span>
        <h3>Update Profile</h3>
        <form method="POST" enctype="multipart/form-data" action="../dashboard.php">
            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
            <label>Profile Image</label>
            <input type="file" name="profile_image" accept="image/*">
            <button type="submit" name="update_profile" style="margin-top:15px;">Save Changes</button>
        </form>
    </div>
</div>

<script>
function toggleDropdown() {
    let menu = document.getElementById("dropdownMenu");
    menu.style.display = menu.style.display === "block" ? "none" : "block";
}
function openProfileModal() {
    document.getElementById("profileModal").style.display = "flex";
}
function closeProfileModal() {
    document.getElementById("profileModal").style.display = "none";
}
window.onclick = function(e) {
    if (!e.target.closest('.user-menu')) {
        document.getElementById("dropdownMenu").style.display = "none";
    }
}
</script>

</body>
</html>