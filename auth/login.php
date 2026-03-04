<?php
session_start();
require '../config/database.php';

$error = "";
$register_error = "";
$register_success = "";

/* ================= LOGIN ================= */
if (isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "All fields are required!";
    } else {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            if ($user['is_verified'] == 1) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['first_name'] . " " . $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_image'] = $user['profile_image'] ?? 'default.png';

                // Role-based redirection
                switch ($user['role']) {
                    case 'admin':
                        header("Location: ../admin/dashboard.php");
                        break;
                    case 'agent':
                        header("Location: ../agent/dashboard.php");
                        break;
                    case 'landlord':
                        header("Location: ../landlord/dashboard.php");
                        break;
                    case 'buyer':
                    case 'tenant':
                        header("Location: ../users/dashboard.php");
                        break;
                    default:
                        header("Location: ../includes/dashboard.php");
                }
                exit();

            } else {
                $error = "Please verify your email first.";
            }

        } else {
            $error = "Invalid email or password.";
        }
    }
}

/* ================= REGISTER ================= */
if (isset($_POST['register'])) {

    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['reg_email']);
    $phone      = trim($_POST['phone']);
    $password   = trim($_POST['reg_password']);
    $role       = trim($_POST['role']);

    $allowed_roles = ['buyer','tenant','agent','landlord'];

    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($role)
    ) {
        $register_error = "All fields are required!";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Invalid email format!";
    }
    elseif (!in_array($role, $allowed_roles)) {
        $register_error = "Invalid role selected!";
    }
    else {

        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            $register_error = "Email already exists!";
        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users 
                (first_name, last_name, email, phone, password, role, is_verified, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");

            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $phone,
                $hashed_password,
                $role
            ]);

            $register_success = "Registration successful! You can now login.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login - RwandaPropertyHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{font-family:Segoe UI;background:#f4f6f9;margin:0}
.container{display:flex;justify-content:center;align-items:center;height:100vh}
.box{background:white;padding:40px;width:380px;border-radius:10px;box-shadow:0 5px 20px rgba(0,0,0,0.08)}
input,select{width:100%;padding:10px;margin-bottom:12px;border:1px solid #ddd;border-radius:6px}
button{width:100%;padding:10px;background:#28a745;color:white;border:none;border-radius:6px;cursor:pointer}
button:hover{background:#218838}
.error{color:red;text-align:center;margin-bottom:10px}
.success{color:green;text-align:center;margin-bottom:10px}
.link{text-align:center;margin-top:10px}
.link a{color:#28a745;text-decoration:none;font-weight:bold}

/* Back to Home link */
.back-home{margin-bottom:15px;text-align:left}
.back-home a{color:#28a745;text-decoration:none;font-size:14px}
.back-home a:hover{text-decoration:underline}

/* Modal */
.modal{
display:none;
position:fixed;
top:0;left:0;
width:100%;height:100%;
background:rgba(0,0,0,0.6);
justify-content:center;
align-items:center;
}

.modal-content{
background:white;
padding:30px;
width:400px;
border-radius:10px;
position:relative;
}

.close{
position:absolute;
right:15px;
top:10px;
cursor:pointer;
font-weight:bold;
font-size:18px;
}
</style>
</head>
<body>

<div class="container">
<div class="box">

    <!-- Back to Home link -->
    <div class="back-home">
        <a href="../index.php">← Back to Home</a>
    </div>

<h2 style="text-align:center">Login</h2>

<?php if($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if($register_success): ?>
<div class="success"><?= htmlspecialchars($register_success) ?></div>
<?php endif; ?>

<form method="POST">
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit" name="login">Login</button>
</form>

<div class="link">
Don't have account? 
<a href="#" onclick="openModal()">Register</a>
</div>

</div>
</div>

<!-- REGISTER MODAL -->
<div class="modal" id="registerModal">
<div class="modal-content">

<span class="close" onclick="closeModal()">&times;</span>

<h3 style="text-align:center">Register</h3>

<?php if($register_error): ?>
<div class="error"><?= htmlspecialchars($register_error) ?></div>
<script>document.addEventListener("DOMContentLoaded",()=>openModal());</script>
<?php endif; ?>

<form method="POST">
<input type="text" name="first_name" placeholder="First Name" required>
<input type="text" name="last_name" placeholder="Last Name" required>
<input type="email" name="reg_email" placeholder="Email" required>
<input type="text" name="phone" placeholder="Phone" required>

<select name="role" required>
    <option value="">Select Role</option>
    <option value="buyer">Buyer</option>
    <option value="tenant">Tenant</option>
    <option value="agent">Agent</option>
    <option value="landlord">Landlord</option>
</select>

<input type="password" name="reg_password" placeholder="Password" required>
<button type="submit" name="register">Register</button>
</form>

</div>
</div>

<script>
function openModal(){
document.getElementById("registerModal").style.display="flex";
}
function closeModal(){
document.getElementById("registerModal").style.display="none";
}
</script>

</body>
</html>