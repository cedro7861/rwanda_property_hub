<?php
session_start();
require '../config/database.php';

// Only admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ================= TOGGLE VERIFICATION ================= */
if (isset($_POST['toggle_verify'])) {
    $user_id = intval($_POST['user_id']);
    $new_status = intval($_POST['new_status']); // 1 or 0
    $stmt = $pdo->prepare("UPDATE users SET is_verified = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);
    header("Location: manage_users.php");
    exit();
}

/* ================= TOGGLE SUBSCRIPTION ================= */
if (isset($_POST['toggle_subscription'])) {
    $user_id = intval($_POST['user_id']);
    $new_status = intval($_POST['new_status']); // 1 or 0
    $stmt = $pdo->prepare("UPDATE users SET subscription = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);
    header("Location: manage_users.php");
    exit();
}

/* ================= EDIT USER ================= */
if (isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Validate email uniqueness (excluding current user)
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $user_id]);
    if ($check->fetch()) {
        $error = "Email already in use by another user.";
        $_SESSION['edit_error'] = $error;
        header("Location: manage_users.php");
        exit();
    }

    // Handle profile image upload
    $image_name = $_POST['current_image']; // keep current by default
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
            $upload_dir = dirname(__DIR__) . '/uploads/profile/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_name = "user_{$user_id}_" . time() . ".{$ext}";
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                // Delete old image if not default
                $old_image = $_POST['current_image'];
                if ($old_image !== 'default.png' && file_exists($upload_dir . $old_image)) {
                    unlink($upload_dir . $old_image);
                }
                $image_name = $new_name;
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, profile_image = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$first_name, $last_name, $email, $phone, $image_name, $user_id]);
    $_SESSION['edit_success'] = "User updated successfully.";
    header("Location: manage_users.php");
    exit();
}

// Retrieve session messages and clear them
$error = $_SESSION['edit_error'] ?? '';
$success = $_SESSION['edit_success'] ?? '';
unset($_SESSION['edit_error'], $_SESSION['edit_success']);

// Fetch users for display
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }
    .table th {
        background-color: #28a745;
        color: white;
    }
    .btn-icon {
        margin-right: 4px;
    }
</style>

<div class="container mt-4">
    <h1 class="mb-4">Manage Users</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table id="usersTable" class="table table-bordered table-hover bg-white">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Verified</th>
                    <th>Subscription</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <img src="../uploads/profile/<?= htmlspecialchars($user['profile_image'] ?? 'default.png') ?>" class="user-avatar">
                    </td>
                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['phone'] ?? '') ?></td>
                    <td><?= ucfirst($user['role']) ?></td>
                    <td>
                        <?php if ($user['is_verified']): ?>
                            <span class="badge bg-success">Verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Not Verified</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['subscription']): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Paid</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Edit button -->
                        <button class="btn btn-sm btn-primary edit-btn" 
                                data-id="<?= $user['id'] ?>"
                                data-first="<?= htmlspecialchars($user['first_name']) ?>"
                                data-last="<?= htmlspecialchars($user['last_name']) ?>"
                                data-email="<?= htmlspecialchars($user['email']) ?>"
                                data-phone="<?= htmlspecialchars($user['phone']) ?>"
                                data-image="<?= htmlspecialchars($user['profile_image'] ?? 'default.png') ?>"
                                data-bs-toggle="modal" 
                                data-bs-target="#editUserModal">
                            <i class="bi bi-pencil"></i> Edit
                        </button>

                        <!-- Toggle verification (Enable/Disable) -->
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $user['is_verified'] ? 0 : 1 ?>">
                                <button type="submit" name="toggle_verify" class="btn btn-sm <?= $user['is_verified'] ? 'btn-warning' : 'btn-success' ?>">
                                    <i class="bi <?= $user['is_verified'] ? 'bi-x-circle' : 'bi-check-circle' ?>"></i>
                                    <?= $user['is_verified'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Toggle subscription -->
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $user['subscription'] ? 0 : 1 ?>">
                                <button type="submit" name="toggle_subscription" class="btn btn-sm <?= $user['subscription'] ? 'btn-secondary' : 'btn-info' ?>">
                                    <i class="bi bi-credit-card"></i>
                                    <?= $user['subscription'] ? 'Remove Paid' : 'Mark Paid' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <input type="hidden" name="current_image" id="edit_current_image">

                    <div class="mb-3">
                        <label for="edit_first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="edit_profile_image" class="form-label">Profile Image (optional)</label>
                        <input type="file" class="form-control" id="edit_profile_image" name="profile_image" accept="image/*">
                        <small class="text-muted">Leave empty to keep current image.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="edit_user">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// Populate edit modal with user data
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_user_id').value = this.dataset.id;
        document.getElementById('edit_first_name').value = this.dataset.first;
        document.getElementById('edit_last_name').value = this.dataset.last;
        document.getElementById('edit_email').value = this.dataset.email;
        document.getElementById('edit_phone').value = this.dataset.phone;
        document.getElementById('edit_current_image').value = this.dataset.image;
    });
});

// Initialize DataTables
$(document).ready(function() {
    $('#usersTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        language: {
            search: "Search users:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            infoEmpty: "Showing 0 to 0 of 0 users",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });
});
</script>

<!-- Bootstrap JS (already in header, but included for safety) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>