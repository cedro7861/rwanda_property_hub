<?php
session_start();
require '../config/database.php';

// Get property ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>Property not found</h1>";
    exit();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
    $reviewer_id = $_SESSION['user_id'];
    $reviewed_user_id = intval($_POST['reviewed_user_id']);
    $property_id = intval($_POST['property_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        // Check if user already reviewed this property
        $check = $pdo->prepare("SELECT id FROM reviews WHERE reviewer_id = ? AND property_id = ?");
        $check->execute([$reviewer_id, $property_id]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO reviews (reviewer_id, reviewed_user_id, property_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$reviewer_id, $reviewed_user_id, $property_id, $rating, $comment]);
            $review_success = "Thank you for your review!";
        } else {
            $review_error = "You have already reviewed this property.";
        }
    } else {
        $review_error = "Please select a rating and enter a comment.";
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
    $tenant_id = $_SESSION['user_id'];
    $property_id = intval($_POST['property_id']);
    $amount = floatval($_POST['amount']);
    $payment_status = 'pending';
    $payment_date = date('Y-m-d');
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if ($amount <= 0) {
        $payment_error = "Please enter a valid amount.";
    } else {
        // Verify property exists and user is not the owner (optional)
        $stmt = $pdo->prepare("SELECT owner_id FROM properties WHERE id = ?");
        $stmt->execute([$property_id]);
        $prop = $stmt->fetch();
        if (!$prop) {
            $payment_error = "Property not found.";
        } elseif ($prop['owner_id'] == $tenant_id) {
            $payment_error = "You cannot pay for your own property.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO rental_payments (property_id, tenant_id, amount, payment_status, payment_date, due_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$property_id, $tenant_id, $amount, $payment_status, $payment_date, $due_date]);
            $payment_success = "Payment recorded successfully! (Status: pending)";
        }
    }
}

// Fetch property details with location names
$stmt = $pdo->prepare("
    SELECT p.*, 
           d.namedistrict, 
           s.namesector, 
           c.nameCell,
           u.first_name, u.last_name, u.email, u.phone, u.role, u.id as owner_id
    FROM properties p
    LEFT JOIN districts d ON p.district = d.districtcode
    LEFT JOIN sectors s ON p.sector = s.sectorcode
    LEFT JOIN cells c ON p.cells = c.codecell
    LEFT JOIN users u ON p.owner_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>Property not found</h1>";
    exit();
}

// Fetch all images for this property
$imgStmt = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch existing reviews for this property
$reviewStmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name 
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    WHERE r.property_id = ?
    ORDER BY r.created_at DESC
");
$reviewStmt->execute([$id]);
$reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to format price
function formatPrice($price, $currency, $status) {
    $formatted = number_format($price) . ' ' . $currency;
    if ($status == 'for_rent') {
        $formatted .= ' / month';
    }
    return $formatted;
}

// Build full location string
$locationParts = [];
if ($property['namedistrict']) $locationParts[] = $property['namedistrict'];
if ($property['namesector']) $locationParts[] = $property['namesector'];
if ($property['nameCell']) $locationParts[] = $property['nameCell'];
$location = implode(', ', $locationParts);

// Check if current user has already reviewed
$user_reviewed = false;
if (isset($_SESSION['user_id'])) {
    foreach ($reviews as $rev) {
        if ($rev['reviewer_id'] == $_SESSION['user_id']) {
            $user_reviewed = true;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($property['title']) ?> - RwandaPropertyHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background-color: #f4f6f9;
            color: #333;
        }

        /* ================= NAVBAR ================= */
        header {
            position: absolute;
            width: 100%;
            padding: 20px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: white;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        nav a {
            text-decoration: none;
            color: white;
            font-weight: 500;
        }

        .auth-buttons a {
            padding: 8px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }

        .login {
            border: 1px solid white;
            color: white;
        }

        .signup {
            background: #28a745;
            color: white;
        }

        /* ================= PAGE HERO ================= */
        .page-hero {
            height: 300px;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),
                        url('../images/property-header.jpg') center/cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .page-hero h1 {
            font-size: 42px;
            font-weight: 600;
        }

        /* ================= MAIN CONTENT ================= */
        .container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .property-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .property-header h2 {
            font-size: 32px;
            font-weight: 600;
        }

        .property-price {
            font-size: 28px;
            color: #28a745;
            font-weight: bold;
        }

        .property-price small {
            font-size: 16px;
            color: #666;
            font-weight: normal;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            background: #28a745;
            color: white;
            margin-left: 15px;
        }

        /* Two-column layout for image + contact */
        .top-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        /* Carousel */
        .carousel-item img {
            height: 400px;
            object-fit: cover;
            width: 100%;
            border-radius: 8px;
        }

        .no-image {
            background: #eee;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 18px;
            border-radius: 8px;
        }

        /* Agent card */
        .agent-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            height: fit-content;
        }

        .agent-card h4 {
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .agent-info p {
            margin: 10px 0;
        }

        .contact-btn, .pay-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 16px;
            margin-top: 10px;
            width: 100%;
            cursor: pointer;
        }

        .pay-btn {
            background: #007bff;
        }

        .contact-btn:hover {
            background: #218838;
        }

        .pay-btn:hover {
            background: #0056b3;
        }

        /* Details section below */
        .details-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-top: 30px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-item .label {
            font-size: 14px;
            color: #777;
            margin-bottom: 5px;
        }

        .detail-item .value {
            font-size: 18px;
            font-weight: 500;
        }

        .description {
            line-height: 1.7;
            margin-top: 30px;
        }

        /* Reviews section */
        .reviews-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-top: 30px;
        }

        .review-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .review-item:last-child {
            border-bottom: none;
        }
        .reviewer-name {
            font-weight: 600;
        }
        .review-date {
            font-size: 0.85rem;
            color: #777;
        }
        .rating {
            color: #ffc107;
        }

        .review-form {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .star-rating {
            direction: rtl;
            display: inline-block;
            padding: 0;
        }
        .star-rating input[type="radio"] {
            display: none;
        }
        .star-rating label {
            color: #bbb;
            font-size: 24px;
            padding: 0;
            cursor: pointer;
            transition: all 0.2s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input[type="radio"]:checked ~ label {
            color: #f5b301;
        }

        @media(max-width: 900px) {
            header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
            }
            .top-row {
                grid-template-columns: 1fr;
            }
            .property-header h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="logo">RwandaPropertyHub</div>
    <nav>
        <a href="../index.php">Home</a>
        <a href="#">Buy</a>
        <a href="#">Rent</a>
        <a href="#">Sell</a>
        <a href="#">Market Trends</a>
        <div class="auth-buttons">
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="../dashboard.php" class="login">Dashboard</a>
                <a href="../auth/logout.php" class="signup">Logout</a>
            <?php else: ?>
                <a href="../auth/login.php" class="login">Login</a>
                <a href="../auth/login.php" class="signup">Sign Up</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<section class="page-hero">
    <h1><?= htmlspecialchars($property['title']) ?></h1>
</section>

<div class="container">
    <div class="property-header">
        <div>
            <h2><?= htmlspecialchars($property['title']) ?></h2>
            <span class="status-badge"><?= str_replace('_', ' ', $property['status']) ?></span>
        </div>
        <div class="property-price">
            <?= formatPrice($property['price'], $property['currency'], $property['status']) ?>
        </div>
    </div>

    <!-- Two‑column row: image carousel (left) + contact card (right) -->
    <div class="top-row">
        <!-- Left: Carousel -->
        <div>
            <?php if (!empty($images)): ?>
            <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <?php foreach($images as $index => $img): ?>
                        <button type="button" data-bs-target="#propertyCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index == 0 ? 'active' : '' ?>" aria-current="<?= $index == 0 ? 'true' : '' ?>" aria-label="Slide <?= $index+1 ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner">
                    <?php foreach($images as $index => $img): ?>
                        <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
                            <img src="../uploads/property/<?= htmlspecialchars($img) ?>" class="d-block w-100" alt="Property Image">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
            <?php else: ?>
                <div class="no-image">No Images Available</div>
            <?php endif; ?>
        </div>

        <!-- Right: Contact Owner Card + Pay Button -->
        <div>
            <div class="agent-card">
                <h4>Contact Owner</h4>
                <div class="agent-info">
                    <p><strong><?= htmlspecialchars($property['first_name'] . ' ' . $property['last_name']) ?></strong></p>
                    <?php if($property['role'] == 'agent'): ?>
                        <p><span class="badge bg-info">Agent</span></p>
                    <?php endif; ?>
                    <p><i class="bi bi-envelope"></i> <?= htmlspecialchars($property['email']) ?></p>
                    <?php if($property['phone']): ?>
                        <p><i class="bi bi-phone"></i> <?= htmlspecialchars($property['phone']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <button class="contact-btn" onclick="window.location.href='../messages/chat.php?user=<?= $property['owner_id'] ?>'">
                        Send Message
                    </button>
                <?php else: ?>
                    <button class="contact-btn" onclick="window.location.href='../auth/login.php'">
                        Login to Contact
                    </button>
                <?php endif; ?>
            </div>

            <!-- Pay Button (only if logged in and not the owner) -->
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $property['owner_id']): ?>
                <button class="pay-btn" data-bs-toggle="modal" data-bs-target="#paymentModal">
                    <i class="bi bi-credit-card"></i> Pay Rent
                </button>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <button class="pay-btn" onclick="window.location.href='../auth/login.php'">
                    Login to Pay
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Details Section -->
    <div class="details-card">
        <h3>Property Details</h3>
        <div class="details-grid">
            <div class="detail-item">
                <span class="label">Property Type</span>
                <span class="value"><?= ucfirst($property['property_type']) ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Status</span>
                <span class="value"><?= str_replace('_', ' ', ucfirst($property['status'])) ?></span>
            </div>
            <?php if($property['property_type'] != 'land'): ?>
            <div class="detail-item">
                <span class="label">Bedrooms</span>
                <span class="value"><?= $property['bedrooms'] ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Bathrooms</span>
                <span class="value"><?= $property['bathrooms'] ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-item">
                <span class="label">Size</span>
                <span class="value"><?= $property['size_sqm'] ? number_format($property['size_sqm']).' sqm' : 'N/A' ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Location</span>
                <span class="value"><?= htmlspecialchars($location) ?></span>
            </div>
            <?php if(!empty($property['address'])): ?>
            <div class="detail-item">
                <span class="label">Address</span>
                <span class="value"><?= htmlspecialchars($property['address']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="description">
            <h4>Description</h4>
            <p><?= nl2br(htmlspecialchars($property['description'])) ?></p>
        </div>
    </div>

    <!-- Reviews Section -->
    <div class="reviews-section">
        <h3>Reviews</h3>
        <?php if (empty($reviews)): ?>
            <p class="text-muted">No reviews yet.</p>
        <?php else: ?>
            <?php foreach ($reviews as $rev): ?>
            <div class="review-item">
                <div class="d-flex justify-content-between">
                    <span class="reviewer-name"><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></span>
                    <span class="review-date"><?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                </div>
                <div class="rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star<?= $i <= $rev['rating'] ? '-fill' : '' ?>"></i>
                    <?php endfor; ?>
                </div>
                <p class="mt-2"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id']) && !$user_reviewed && $_SESSION['user_id'] != $property['owner_id']): ?>
        <div class="review-form">
            <h5>Leave a Review</h5>
            <?php if (isset($review_error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($review_error) ?></div>
            <?php endif; ?>
            <?php if (isset($review_success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($review_success) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="reviewed_user_id" value="<?= $property['owner_id'] ?>">
                <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Rating</label>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5"><label for="star5" class="bi bi-star-fill"></label>
                        <input type="radio" id="star4" name="rating" value="4"><label for="star4" class="bi bi-star-fill"></label>
                        <input type="radio" id="star3" name="rating" value="3"><label for="star3" class="bi bi-star-fill"></label>
                        <input type="radio" id="star2" name="rating" value="2"><label for="star2" class="bi bi-star-fill"></label>
                        <input type="radio" id="star1" name="rating" value="1"><label for="star1" class="bi bi-star-fill"></label>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="comment" class="form-label">Comment</label>
                    <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                </div>
                <button type="submit" name="submit_review" class="btn btn-success">Submit Review</button>
            </form>
        </div>
        <?php elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $property['owner_id']): ?>
            <p class="text-muted mt-3">You cannot review your own property.</p>
        <?php elseif (!isset($_SESSION['user_id'])): ?>
            <p class="text-muted mt-3"><a href="../auth/login.php">Login</a> to leave a review.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">Make a Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if (isset($payment_error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($payment_error) ?></div>
                    <?php endif; ?>
                    <?php if (isset($payment_success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($payment_success) ?></div>
                    <?php endif; ?>
                    <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount (RWF)</label>
                        <input type="number" class="form-control" id="amount" name="amount" value="<?= $property['price'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Due Date (optional)</label>
                        <input type="date" class="form-control" id="due_date" name="due_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_payment" class="btn btn-primary">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>