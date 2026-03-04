<?php
session_start();
require '../config/database.php';

// ================= HANDLE FAVORITE TOGGLE (AJAX) =================
if (isset($_POST['action']) && in_array($_POST['action'], ['add', 'remove']) && isset($_POST['property_id'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'error' => ''];

    if (!isset($_SESSION['user_id'])) {
        $response['error'] = 'Not logged in';
        echo json_encode($response);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $property_id = intval($_POST['property_id']);
    $action = $_POST['action'];

    if ($property_id <= 0) {
        $response['error'] = 'Invalid property ID';
        echo json_encode($response);
        exit();
    }

    // Verify property exists
    $stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ?");
    $stmt->execute([$property_id]);
    if (!$stmt->fetch()) {
        $response['error'] = 'Property not found';
        echo json_encode($response);
        exit();
    }

    if ($action === 'add') {
        // Check if already favorited
        $check = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND property_id = ?");
        $check->execute([$user_id, $property_id]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, property_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $property_id]);
            $response = ['success' => true, 'action' => 'added'];
        } else {
            $response['error'] = 'Already favorited';
        }
    } else { // remove
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND property_id = ?");
        $stmt->execute([$user_id, $property_id]);
        $response = ['success' => true, 'action' => 'removed'];
    }

    echo json_encode($response);
    exit();
}

// ================= REST OF THE PAGE (SEARCH, DISPLAY) =================

// Fetch distinct provinces for search dropdown
$provinces = $pdo->query("SELECT provincecode, provincename FROM provinces")->fetchAll(PDO::FETCH_ASSOC);

// Get user's favorites if logged in
$favorites = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT property_id FROM favorites WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Build the query with filters
$sql = "
    SELECT 
        p.*,
        d.namedistrict,
        s.namesector,
        c.nameCell,
        u.first_name AS owner_first,
        u.last_name AS owner_last,
        u.is_verified
    FROM properties p
    LEFT JOIN districts d ON p.district = d.districtcode
    LEFT JOIN sectors s ON p.sector = s.sectorcode
    LEFT JOIN cells c ON p.cells = c.codecell
    LEFT JOIN users u ON p.owner_id = u.id
    WHERE p.status IN ('for_sale', 'for_rent')
    AND u.is_verified = 1
";
$params = [];

// Apply search filters
if (!empty($_GET['location'])) {
    $sql .= " AND (p.district = ? OR p.sector = ? OR p.cells = ?)";
    $params[] = $_GET['location'];
    $params[] = $_GET['location'];
    $params[] = $_GET['location'];
}
if (!empty($_GET['type'])) {
    $sql .= " AND p.property_type = ?";
    $params[] = $_GET['type'];
}
if (!empty($_GET['min_price'])) {
    $sql .= " AND p.price >= ?";
    $params[] = $_GET['min_price'];
}
if (!empty($_GET['max_price'])) {
    $sql .= " AND p.price <= ?";
    $params[] = $_GET['max_price'];
}
if (!empty($_GET['bedrooms'])) {
    $sql .= " AND p.bedrooms >= ?";
    $params[] = $_GET['bedrooms'];
}

$sql .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Properties - RwandaPropertyHub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .container { margin-top: 30px; margin-bottom: 50px; }
        .search-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .property-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .property-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .carousel-inner img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            background-color: rgba(0,0,0,0.3);
            border-radius: 50%;
            padding: 10px;
        }
        .carousel-indicators {
            margin-bottom: 0;
        }
        .carousel-indicators button {
            background-color: #28a745;
        }
        .no-image {
            height: 180px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        .card-body {
            padding: 15px;
            flex: 1;
        }
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .price {
            color: #28a745;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 5px 0;
        }
        .details {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }
        .details i {
            width: 18px;
            color: #28a745;
        }
        .badge-status {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            text-transform: uppercase;
            z-index: 10;
        }
        .owner-info {
            font-size: 0.85rem;
            border-top: 1px solid #eee;
            padding-top: 8px;
            margin-top: 8px;
            color: #555;
        }
        .card-footer {
            background: white;
            border-top: 1px solid #eee;
            padding: 10px 15px;
            font-size: 0.8rem;
            color: #777;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .verified-badge {
            color: #28a745;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        .favorite-btn {
            transition: all 0.2s;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <h2 class="mb-4">Browse Properties</h2>

    <!-- Search Form -->
    <div class="search-bar">
        <form method="GET" action="view_properties.php" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Location</label>
                <select name="location" class="form-select">
                    <option value="">Any</option>
                    <?php
                    $districts = $pdo->query("SELECT districtcode, namedistrict FROM districts WHERE districtcode NOT LIKE '___00' ORDER BY namedistrict")->fetchAll();
                    foreach ($districts as $d) {
                        $selected = ($_GET['location'] ?? '') == $d['districtcode'] ? 'selected' : '';
                        echo '<option value="'.$d['districtcode'].'" '.$selected.'>'.$d['namedistrict'].'</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="house" <?= ($_GET['type'] ?? '') == 'house' ? 'selected' : '' ?>>House</option>
                    <option value="apartment" <?= ($_GET['type'] ?? '') == 'apartment' ? 'selected' : '' ?>>Apartment</option>
                    <option value="land" <?= ($_GET['type'] ?? '') == 'land' ? 'selected' : '' ?>>Land</option>
                    <option value="commercial" <?= ($_GET['type'] ?? '') == 'commercial' ? 'selected' : '' ?>>Commercial</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Min Price</label>
                <input type="number" name="min_price" class="form-control" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>" placeholder="0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Max Price</label>
                <input type="number" name="max_price" class="form-control" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>" placeholder="Any">
            </div>
            <div class="col-md-2">
                <label class="form-label">Min Bedrooms</label>
                <input type="number" name="bedrooms" class="form-control" value="<?= htmlspecialchars($_GET['bedrooms'] ?? '') ?>" placeholder="0">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
        </form>
    </div>

    <!-- Results -->
    <?php if (empty($properties)): ?>
        <div class="alert alert-info">No properties match your criteria.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($properties as $p): 
                // Fetch all images for this property
                $imgStmt = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
                $imgStmt->execute([$p['id']]);
                $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

                // Price display
                $price_display = number_format($p['price']) . ' ' . $p['currency'];
                if ($p['status'] == 'for_rent') $price_display .= '/mo';

                // Location string
                $location_parts = [];
                if (!empty($p['namedistrict'])) $location_parts[] = $p['namedistrict'];
                if (!empty($p['namesector'])) $location_parts[] = $p['namesector'];
                if (!empty($p['nameCell'])) $location_parts[] = $p['nameCell'];
                $location = implode(' > ', $location_parts) ?: 'N/A';

                $badge_class = $p['status'] == 'for_rent' ? 'bg-info' : 'bg-success';
                $is_favorite = in_array($p['id'], $favorites);
            ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="property-card">
                    <div style="position: relative;">
                        <?php if (!empty($images)): ?>
                            <div id="carousel-<?= $p['id'] ?>" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-indicators">
                                    <?php foreach ($images as $index => $img): ?>
                                        <button type="button" data-bs-target="#carousel-<?= $p['id'] ?>" data-bs-slide-to="<?= $index ?>" class="<?= $index == 0 ? 'active' : '' ?>" aria-label="Slide <?= $index+1 ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="carousel-inner">
                                    <?php foreach ($images as $index => $img): ?>
                                        <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
                                            <img src="../uploads/property/<?= htmlspecialchars($img) ?>" class="d-block w-100" alt="Property image">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#carousel-<?= $p['id'] ?>" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#carousel-<?= $p['id'] ?>" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="no-image"><i class="bi bi-image"></i> No Image</div>
                        <?php endif; ?>
                        <span class="badge-status <?= $badge_class ?>"><?= str_replace('_', ' ', $p['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title" title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></h5>
                        <div class="price"><?= $price_display ?></div>
                        <div class="details">
                            <div><i class="bi bi-house-door"></i> <?= ucfirst($p['property_type']) ?></div>
                            <div><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($location) ?></div>
                            <?php if (!empty($p['address'])): ?>
                                <div><i class="bi bi-pin-map"></i> <?= htmlspecialchars($p['address']) ?></div>
                            <?php endif; ?>
                            <?php if ($p['property_type'] != 'land'): ?>
                                <div><i class="bi bi-door-open"></i> <?= $p['bedrooms'] ?> beds | <?= $p['bathrooms'] ?> baths</div>
                            <?php endif; ?>
                            <?php if (!empty($p['size_sqm'])): ?>
                                <div><i class="bi bi-rulers"></i> <?= number_format($p['size_sqm']) ?> sqm</div>
                            <?php endif; ?>
                        </div>
                        <div class="owner-info">
                            <i class="bi bi-person"></i> <?= htmlspecialchars($p['owner_first'] . ' ' . $p['owner_last']) ?>
                            <span class="verified-badge" title="Verified Owner"><i class="bi bi-patch-check-fill"></i></span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small><i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($p['created_at'])) ?></small>
                        <div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="btn btn-sm favorite-btn <?= $is_favorite ? 'btn-danger' : 'btn-outline-danger' ?>" data-property-id="<?= $p['id'] ?>">
                                    <i class="bi bi-heart<?= $is_favorite ? '-fill' : '' ?>"></i>
                                </button>
                            <?php else: ?>
                                <a href="../auth/login.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-heart"></i></a>
                            <?php endif; ?>
                            <a href="property_details.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success">View</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const favoriteButtons = document.querySelectorAll('.favorite-btn');
    favoriteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const propertyId = this.dataset.propertyId;
            const isFav = this.classList.contains('btn-danger');
            const url = window.location.href; // Send to the same page
            const formData = new FormData();
            formData.append('property_id', propertyId);
            formData.append('action', isFav ? 'remove' : 'add');

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.error || 'Server error'); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (data.action === 'added') {
                        btn.classList.remove('btn-outline-danger');
                        btn.classList.add('btn-danger');
                        btn.querySelector('i').classList.remove('bi-heart');
                        btn.querySelector('i').classList.add('bi-heart-fill');
                    } else {
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-outline-danger');
                        btn.querySelector('i').classList.remove('bi-heart-fill');
                        btn.querySelector('i').classList.add('bi-heart');
                    }
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update favorite: ' + error.message);
            });
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>