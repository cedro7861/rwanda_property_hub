<?php
session_start();
require '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

/* ================= FETCH ADMIN DATA ================= */
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$full_name = $admin['first_name'] . " " . $admin['last_name'];

/* ================= FETCH STATISTICS ================= */
// Total users
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Total properties
$total_properties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();

// Total messages
$total_messages = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();

// Total reviews
$total_reviews = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();

// Total rental payments
$total_payments = $pdo->query("SELECT COUNT(*) FROM rental_payments")->fetchColumn();

// Sum of all paid rental income
$total_income = $pdo->query("SELECT SUM(amount) FROM rental_payments WHERE payment_status = 'paid'")->fetchColumn();
$total_income = $total_income ?: 0;

// Recent users (last 5)
$recent_users = $pdo->query("SELECT id, first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Recent properties (last 5)
$recent_properties = $pdo->query("
    SELECT p.id, p.title, p.price, p.currency, p.status, p.created_at, u.first_name, u.last_name 
    FROM properties p
    JOIN users u ON p.owner_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">Admin Dashboard</h1>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body">
                    <h3 class="display-4 text-success"><?= $total_users ?></h3>
                    <p class="text-muted">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body">
                    <h3 class="display-4 text-success"><?= $total_properties ?></h3>
                    <p class="text-muted">Properties Listed</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body">
                    <h3 class="display-4 text-success"><?= $total_messages ?></h3>
                    <p class="text-muted">Messages</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body">
                    <h3 class="display-4 text-success"><?= $total_reviews ?></h3>
                    <p class="text-muted">Reviews</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body">
                    <h3 class="display-4 text-success"><?= $total_payments ?></h3>
                    <p class="text-muted">Rental Payments</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body">
                    <h3 class="display-6 text-success"><?= number_format($total_income) ?> RWF</h3>
                    <p class="text-muted">Total Paid Income</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <!-- Recent Users -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent Users</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= ucfirst($u['role']) ?></td>
                                <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Properties -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent Properties</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Owner</th>
                                <th>Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_properties as $p): 
                                $price_display = number_format($p['price']) . ' ' . $p['currency'];
                                if ($p['status'] == 'for_rent') $price_display .= '/mo';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars(substr($p['title'], 0, 30)) . (strlen($p['title']) > 30 ? '...' : '') ?></td>
                                <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                <td><?= $price_display ?></td>
                                <td><span class="badge bg-<?= $p['status']=='for_rent'?'info':'success' ?>"><?= str_replace('_', ' ', $p['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS is already included in header, but we add it here just in case -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>