<?php
session_start();
require '../config/database.php';

// Restrict access to landlords only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: ../auth/login.php");
    exit();
}

$landlord_id = $_SESSION['user_id'];

/* ================= STATISTICS ================= */
// Total properties
$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE owner_id = ?");
$stmt->execute([$landlord_id]);
$total_properties = $stmt->fetchColumn();

// Active rentals (for_rent status)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM properties 
    WHERE owner_id = ? AND status = 'for_rent'
");
$stmt->execute([$landlord_id]);
$active_rentals = $stmt->fetchColumn();

// Total rental income from paid payments
$stmt = $pdo->prepare("
    SELECT SUM(rp.amount)
    FROM rental_payments rp
    JOIN properties p ON rp.property_id = p.id
    WHERE p.owner_id = ? AND rp.payment_status = 'paid'
");
$stmt->execute([$landlord_id]);
$total_income = $stmt->fetchColumn();
$total_income = $total_income ?: 0;

// Recent properties (last 5)
$stmt = $pdo->prepare("
    SELECT p.*, 
        (SELECT image_url FROM property_images 
         WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM properties p
    WHERE p.owner_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$landlord_id]);
$recent_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the shared header (which already includes Bootstrap)
include '../includes/header.php';
?>
<!-- Main Content -->
<div class="container mt-4">
    <h1 class="mb-4">Landlord Dashboard</h1>
    <p class="lead">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? ($landlord['first_name'] . ' ' . $landlord['last_name'])) ?>!</p>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-md-4">
            <div class="card text-center h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-muted">Total Properties</h5>
                    <p class="display-4 text-success fw-bold"><?= $total_properties ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <div class="card text-center h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-muted">Active Rentals</h5>
                    <p class="display-4 text-success fw-bold"><?= $active_rentals ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <div class="card text-center h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-muted">Rental Income</h5>
                    <p class="display-6 text-success fw-bold"><?= number_format($total_income) ?> RWF</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Properties -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Recent Properties</h5>
        </div>
        <div class="card-body">
            <?php if (empty($recent_properties)): ?>
                <p class="text-muted">You haven't listed any properties yet.</p>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($recent_properties as $prop): 
                        $price_display = $prop['status'] == 'for_rent' 
                            ? number_format($prop['price']) . ' ' . $prop['currency'] . '/mo'
                            : number_format($prop['price']) . ' ' . $prop['currency'];
                    ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card h-100">
                            <?php if (!empty($prop['image']) && file_exists('../uploads/property/' . $prop['image'])): ?>
                                <img src="../uploads/property/<?= htmlspecialchars($prop['image']) ?>" class="card-img-top" style="height: 150px; object-fit: cover;" alt="<?= htmlspecialchars($prop['title']) ?>">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                                    <span class="text-muted">No Image</span>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h6 class="card-title"><?= htmlspecialchars(substr($prop['title'], 0, 20)) . (strlen($prop['title']) > 20 ? '...' : '') ?></h6>
                                <p class="card-text text-success fw-bold"><?= $price_display ?></p>
                                <p class="card-text"><small class="text-muted">Status: <?= str_replace('_', ' ', $prop['status']) ?></small></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- No footer or extra scripts needed; header already includes them -->
</body>
</html>