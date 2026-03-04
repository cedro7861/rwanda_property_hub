<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../includes/header.php';

// Handle approval/rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    if (in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE ads SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $success = "Ad " . $status . " successfully!";
    }
}

// Fetch all ads with user info
$stmt = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM ads a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
");
$ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container mt-4">
    <h2>Manage Ads</h2>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (empty($ads)): ?>
        <p>No ads found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Title</th>
                        <th>Media</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ads as $ad): ?>
                    <tr>
                        <td><?= $ad['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($ad['first_name'] . ' ' . $ad['last_name']) ?><br>
                            <small><?= $ad['email'] ?></small>
                        </td>
                        <td><?= htmlspecialchars($ad['title']) ?></td>
                        <td>
                            <?php if ($ad['media_type'] === 'image' || $ad['media_type'] === 'gif'): ?>
                                <img src="../uploads/ads/<?= $ad['file_path'] ?>" width="100" alt="">
                            <?php else: ?>
                                <video width="100" controls><source src="../uploads/ads/<?= $ad['file_path'] ?>"></video>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $ad['start_date'] ?> to <?= $ad['end_date'] ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $ad['status'] === 'approved' ? 'success' : ($ad['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                <?= ucfirst($ad['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($ad['status'] === 'pending'): ?>
                                <a href="?action=approve&id=<?= $ad['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this ad?')">Approve</a>
                                <a href="?action=reject&id=<?= $ad['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this ad?')">Reject</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
