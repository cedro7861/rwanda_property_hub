<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__) . '/config/database.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];
$role = $_SESSION['role'] ?? $user['role'];
$profile_image = $user['profile_image'] ?? 'default.png';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $phone      = trim($_POST['phone']);
    $image_name = $profile_image; // keep current by default

    $upload_dir = dirname(__DIR__) . '/uploads/profile/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Handle image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
            $new_name = "user_{$user_id}_" . time() . ".{$ext}";
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                // Delete old image if not default
                if ($profile_image !== 'default.png' && file_exists($upload_dir . $profile_image)) {
                    unlink($upload_dir . $profile_image);
                }
                $image_name = $new_name;
            }
        }
    }

    // Update database
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, profile_image = ? WHERE id = ?");
    $stmt->execute([$first_name, $last_name, $phone, $image_name, $user_id]);

    // Refresh user data
    $_SESSION['full_name'] = $first_name . ' ' . $last_name;
    $user['first_name'] = $first_name;
    $user['last_name'] = $last_name;
    $user['phone'] = $phone;
    $user['profile_image'] = $image_name;
    $profile_image = $image_name;
    $full_name = $first_name . ' ' . $last_name;
}

// Determine current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Build navigation links based on role
$nav_links = [];

// Dashboard URL by role
$dashboard_url = '';
if ($role === 'admin') {
    $dashboard_url = '../admin/dashboard.php';
} elseif ($role === 'landlord') {
    $dashboard_url = '../landlord/dashboard.php';
} elseif ($role === 'agent') {
    $dashboard_url = '../agent/dashboard.php';
} elseif (in_array($role, ['buyer', 'tenant'])) {
    $dashboard_url = '../users/dashboard.php';
} else {
    $dashboard_url = '#'; // fallback
}
$nav_links[] = ['url' => $dashboard_url, 'label' => 'Dashboard'];

// Universal Messages link for all logged-in users
$nav_links[] = ['url' => '../messages/chats.php', 'label' => 'Messages'];

// Role-specific links
if ($role === 'admin') {
    $nav_links[] = ['url' => '../users/manage_users.php', 'label' => 'Manage Users'];
    $nav_links[] = ['url' => '../admin/properties.php', 'label' => 'View Properties'];
    $nav_links[] = ['url' => '../admin/manage_ads.php', 'label' => 'Manage Ads'];
    $nav_links[] = ['url' => '#', 'label' => 'Reports']; // placeholder
} elseif (in_array($role, ['agent', 'landlord'])) {
    $nav_links[] = ['url' => '../landlord/manage_property.php', 'label' => 'Manage Properties'];
    $nav_links[] = ['url' => '../property/properties.php', 'label' => 'My Properties'];
    // Ad links for users
    $nav_links[] = ['url' => '../users/submit_ad.php', 'label' => 'Ads'];
} elseif (in_array($role, ['buyer', 'tenant'])) {
    $nav_links[] = ['url' => '../property/view_properties.php', 'label' => 'Browse Properties'];
    $nav_links[] = ['url' => '../users/favorites.php', 'label' => 'Saved Properties'];
    // Ad links for users
    $nav_links[] = ['url' => '../users/submit_ad.php', 'label' => 'Ads'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>RwandaPropertyHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #28a745;
            --primary-dark: #218838;
            --text: #333;
            --text-light: #666;
            --bg-light: #f4f6f9;
            --white: #fff;
            --shadow: 0 2px 10px rgba(0,0,0,0.05);
            --shadow-lg: 0 5px 20px rgba(0,0,0,0.08);
            --border: #ddd;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text);
            padding-top: 70px; /* space for fixed navbar */
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 2rem;
            background: var(--white);
            box-shadow: var(--shadow);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .logo:hover {
            color: var(--primary-dark);
        }

        .nav-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text);
            cursor: pointer;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            padding: 0.5rem 0;
            border-bottom: 2px solid transparent;
            transition: color 0.2s, border-color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* User menu */
        .user-menu {
            position: relative;
            cursor: pointer;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 30px;
            transition: background 0.2s;
        }

        .user-info:hover {
            background: var(--bg-light);
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .user-info div {
            line-height: 1.3;
        }

        .user-info strong {
            font-size: 0.95rem;
        }

        .user-info small {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .dropdown {
            position: absolute;
            right: 0;
            top: 110%;
            background: var(--white);
            box-shadow: var(--shadow-lg);
            border-radius: 8px;
            display: none;
            min-width: 200px;
            overflow: hidden;
            z-index: 1001;
        }

        .dropdown a {
            display: block;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: var(--text);
            transition: background 0.2s;
        }

        .dropdown a:hover {
            background: var(--bg-light);
        }

        .dropdown a i {
            width: 20px;
            margin-right: 8px;
            color: var(--primary);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .modal-content {
            background: var(--white);
            padding: 2rem;
            width: 90%;
            max-width: 400px;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            position: relative;
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #aaa;
            transition: color 0.2s;
        }

        .close:hover {
            color: var(--text);
        }

        .modal h3 {
            margin-bottom: 1.5rem;
            color: var(--text);
        }

        .modal input[type="text"],
        .modal input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .modal button {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary);
            border: none;
            color: var(--white);
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .modal button:hover {
            background: var(--primary-dark);
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }

            .nav-toggle {
                display: block;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--white);
                flex-direction: column;
                gap: 0;
                padding: 1rem 0;
                box-shadow: var(--shadow);
            }

            .nav-links.show {
                display: flex;
            }

            .nav-links a {
                width: 100%;
                padding: 0.75rem 2rem;
                border-bottom: 1px solid #eee;
            }

            .nav-links a:last-child {
                border-bottom: none;
            }

            .user-info div {
                display: none; /* hide name on mobile, keep avatar */
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="../index.php" class="logo">RwandaPropertyHub</a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
        <i class="fas fa-bars"></i>
    </button>

    <div class="nav-links" id="navLinks">
        <?php foreach ($nav_links as $link): 
            $active = (basename($link['url']) === $current_page) ? 'class="active"' : '';
        ?>
            <a href="<?= $link['url'] ?>" <?= $active ?>><?= $link['label'] ?></a>
        <?php endforeach; ?>
    </div>

    <div class="user-menu" id="userMenu">
        <div class="user-info">
            <img src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>" alt="Profile">
            <div>
                <strong><?= htmlspecialchars($full_name) ?></strong><br>
                <small><?= htmlspecialchars(ucfirst($role)) ?></small>
            </div>
        </div>
        <div class="dropdown" id="dropdownMenu">
            <a href="#" onclick="openProfileModal(event)"><i class="fas fa-user-cog"></i> Profile Settings</a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>

<!-- Profile Modal -->
<div class="modal" id="profileModal">
    <div class="modal-content">
        <span class="close" onclick="closeProfileModal()">&times;</span>
        <h3>Update Profile</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" placeholder="First Name" required>
            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" placeholder="Last Name" required>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="Phone Number" required>
            <label style="display:block; text-align:left; margin-bottom:0.25rem;">Profile Image</label>
            <input type="file" name="profile_image" accept="image/*">
            <button type="submit" name="update_profile">Save Changes</button>
        </form>
    </div>
</div>

<script>
// Toggle mobile navigation
const navToggle = document.getElementById('navToggle');
const navLinks = document.getElementById('navLinks');
navToggle.addEventListener('click', () => {
    navLinks.classList.toggle('show');
});

// Close mobile nav when a link is clicked
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('show');
    });
});

// User menu dropdown
const userMenu = document.getElementById('userMenu');
const dropdown = document.getElementById('dropdownMenu');

userMenu.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
});

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!userMenu.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

// Modal functions
function openProfileModal(e) {
    e.preventDefault();
    document.getElementById('profileModal').style.display = 'flex';
    dropdown.style.display = 'none';
}

function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
}

// Close modal when clicking outside content
window.addEventListener('click', (e) => {
    const modal = document.getElementById('profileModal');
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});
</script>
</body>
</html>