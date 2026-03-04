<?php
session_start();
require '../config/database.php';

// Restrict access to agents only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header("Location: ../auth/login.php");
    exit();
}

$agent_id = $_SESSION['user_id'];

/* ================= FETCH AGENT DATA ================= */
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$agent_id]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);

$full_name = $agent['first_name'] . " " . $agent['last_name'];
$profile_image = $agent['profile_image'] ?? 'default.png';

/* ================= STATISTICS ================= */
// Total properties listed by this agent
$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE owner_id = ?");
$stmt->execute([$agent_id]);
$total_properties = $stmt->fetchColumn();

// Active rentals (for_rent status)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM properties 
    WHERE owner_id = ? AND status = 'for_rent'
");
$stmt->execute([$agent_id]);
$active_rentals = $stmt->fetchColumn();

// Total messages received (as receiver)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->execute([$agent_id]);
$unread_messages = $stmt->fetchColumn();

// Total rental income from properties they own (if any)
$stmt = $pdo->prepare("
    SELECT SUM(rp.amount)
    FROM rental_payments rp
    JOIN properties p ON rp.property_id = p.id
    WHERE p.owner_id = ? AND rp.payment_status = 'paid'
");
$stmt->execute([$agent_id]);
$total_income = $stmt->fetchColumn();
$total_income = $total_income ?: 0;

// Recent properties
$stmt = $pdo->prepare("
    SELECT p.*, 
        (SELECT image_url FROM property_images 
         WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM properties p
    WHERE p.owner_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$agent_id]);
$recent_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - RwandaPropertyHub</title>
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
        /* Recent properties */
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .property-item {
            border: 1px solid #eee;
            border-radius: 6px;
            overflow: hidden;
        }
        .property-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .property-info {
            padding: 10px;
        }
        .property-info h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        .property-info .price {
            color: #28a745;
            font-weight: bold;
            font-size: 14px;
        }
        .no-image {
            background: #eee;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 12px;
        }
        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
            }
            .content {
                padding: 150px 20px 30px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
<div class="content">
    <h1>Agent Dashboard</h1>
    <div class="welcome">Welcome, <?= htmlspecialchars($full_name) ?>!</div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Listings</h3>
            <p><?= $total_properties ?></p>
        </div>
        <div class="stat-card">
            <h3>Active Rentals</h3>
            <p><?= $active_rentals ?></p>
        </div>
        <div class="stat-card">
            <h3>Unread Messages</h3>
            <p><?= $unread_messages ?></p>
        </div>
        <div class="stat-card">
            <h3>Rental Income</h3>
            <p><?= number_format($total_income) ?> RWF</p>
        </div>
    </div>

    <!-- Recent Properties (No Links) -->
    <div class="recent-card">
        <h2>Your Recent Properties</h2>
        <?php if (empty($recent_properties)): ?>
            <p>You haven't listed any properties yet.</p>
        <?php else: ?>
            <div class="property-list">
                <?php foreach ($recent_properties as $prop): 
                    $price_display = $prop['status'] == 'for_rent' 
                        ? number_format($prop['price']) . ' ' . $prop['currency'] . '/mo'
                        : number_format($prop['price']) . ' ' . $prop['currency'];
                ?>
                <div class="property-item">
                    <?php if (!empty($prop['image']) && file_exists('../uploads/property/' . $prop['image'])): ?>
                        <img src="../uploads/property/<?= htmlspecialchars($prop['image']) ?>" alt="<?= htmlspecialchars($prop['title']) ?>">
                    <?php else: ?>
                        <div class="no-image">No Image</div>
                    <?php endif; ?>
                    <div class="property-info">
                        <h4><?= htmlspecialchars(substr($prop['title'], 0, 20)) . (strlen($prop['title']) > 20 ? '...' : '') ?></h4>
                        <div class="price"><?= $price_display ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Profile Modal -->
<div class="modal" id="profileModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center;">
    <div class="modal-content" style="background:white; padding:30px; width:400px; border-radius:10px;">
        <span class="close" onclick="closeProfileModal()" style="float:right; cursor:pointer; font-weight:bold;">&times;</span>
        <h3>Update Profile</h3>
        <form method="POST" enctype="multipart/form-data" action="../dashboard.php">
            <input type="text" name="first_name" value="<?= htmlspecialchars($agent['first_name']) ?>" required>
            <input type="text" name="last_name" value="<?= htmlspecialchars($agent['last_name']) ?>" required>
            <input type="text" name="phone" value="<?= htmlspecialchars($agent['phone']) ?>" required>
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