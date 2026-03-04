<?php
session_start();
require '../config/database.php';

// Restrict access to admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all properties with joined location and owner data
$stmt = $pdo->query("
    SELECT 
        p.*,
        d.namedistrict,
        s.namesector,
        c.nameCell,
        u.first_name AS owner_first,
        u.last_name AS owner_last
    FROM properties p
    LEFT JOIN districts d ON p.district = d.districtcode
    LEFT JOIN sectors s ON p.sector = s.sectorcode
    LEFT JOIN cells c ON p.cells = c.codecell
    LEFT JOIN users u ON p.owner_id = u.id
    ORDER BY p.created_at DESC
");
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Properties - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .container { margin-top: 30px; margin-bottom: 50px; }
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
        /* Carousel styling */
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
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <h2 class="mb-4">All Properties</h2>

    <?php if (empty($properties)): ?>
        <div class="alert alert-info">No properties found.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($properties as $p): 
                // Fetch all images for this property
                $imgStmt = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
                $imgStmt->execute([$p['id']]);
                $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

                // Build price display
                $price_display = number_format($p['price']) . ' ' . $p['currency'];
                if ($p['status'] == 'for_rent') $price_display .= '/mo';

                // Build location string (district, sector, cell)
                $location_parts = [];
                if (!empty($p['namedistrict'])) $location_parts[] = $p['namedistrict'];
                if (!empty($p['namesector'])) $location_parts[] = $p['namesector'];
                if (!empty($p['nameCell'])) $location_parts[] = $p['nameCell'];
                $location = implode(' > ', $location_parts) ?: 'N/A';

                // Determine badge color based on status
                $badge_class = $p['status'] == 'for_rent' ? 'bg-info' : 'bg-success';
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
                        </div>
                    </div>
                    <div class="card-footer">
                        <i class="bi bi-calendar"></i> Listed: <?= date('M j, Y', strtotime($p['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>