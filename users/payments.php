<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['tenant', 'buyer'])) {
    header("Location: ../auth/login.php");
    exit();
}
$tenant_id = $_SESSION['user_id'];

// Fetch payments for this tenant
$stmt = $pdo->prepare("
    SELECT 
        rp.*,
        p.title AS property_title,
        p.currency,
        u.first_name AS owner_first,
        u.last_name AS owner_last
    FROM rental_payments rp
    JOIN properties p ON rp.property_id = p.id
    JOIN users u ON p.owner_id = u.id
    WHERE rp.tenant_id = ?
    ORDER BY rp.created_at DESC
");
$stmt->execute([$tenant_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<div class="container mt-4">
    <h1 class="mb-4">My Payments</h1>

    <?php if (empty($payments)): ?>
        <div class="alert alert-info">You have no payment records.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Property</th>
                        <th>Owner</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                        <th>Due Date</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['property_title']) ?></td>
                        <td><?= htmlspecialchars($p['owner_first'] . ' ' . $p['owner_last']) ?></td>
                        <td><?= number_format($p['amount']) ?> <?= $p['currency'] ?></td>
                        <td>
                            <span class="badge bg-<?= $p['payment_status'] === 'paid' ? 'success' : ($p['payment_status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($p['payment_status']) ?>
                            </span>
                        </td>
                        <td><?= $p['payment_date'] ? date('M j, Y', strtotime($p['payment_date'])) : 'N/A' ?></td>
                        <td><?= $p['due_date'] ? date('M j, Y', strtotime($p['due_date'])) : 'N/A' ?></td>
                        <td><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
