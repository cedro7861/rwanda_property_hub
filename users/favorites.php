    <?php
    session_start();
    require '../config/database.php';

    // Restrict access to logged-in users
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Handle removal from favorites
    if (isset($_GET['remove'])) {
        $property_id = intval($_GET['remove']);
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND property_id = ?");
        $stmt->execute([$user_id, $property_id]);
        header("Location: favorites.php");
        exit();
    }

    // Fetch user's favorites with property details
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            p.id as property_id,
            p.title,
            p.price,
            p.currency,
            p.status,
            p.property_type,
            p.bedrooms,
            p.bathrooms,
            p.size_sqm,
            p.address,
            d.namedistrict,
            s.namesector,
            c.nameCell,
            (SELECT image_url FROM property_images 
            WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM favorites f
        JOIN properties p ON f.property_id = p.id
        LEFT JOIN districts d ON p.district = d.districtcode
        LEFT JOIN sectors s ON p.sector = s.sectorcode
        LEFT JOIN cells c ON p.cells = c.codecell
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Favorites - RwandaPropertyHub</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            body { background-color: #f4f6f9; }
            .container { margin-top: 30px; margin-bottom: 50px; }
            h2 { margin-bottom: 30px; }
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
            .card-img-top {
                width: 100%;
                height: 180px;
                object-fit: cover;
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
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 0.75rem;
                text-transform: uppercase;
                background: #28a745;
                color: white;
            }
            .btn-remove {
                background: #dc3545;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 5px;
                width: 100%;
                margin-top: 10px;
                cursor: pointer;
                transition: background 0.2s;
            }
            .btn-remove:hover {
                background: #c82333;
            }
            .btn-view {
                background: #28a745;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 5px;
                width: 100%;
                margin-top: 10px;
                text-decoration: none;
                display: inline-block;
                text-align: center;
            }
            .btn-view:hover {
                background: #218838;
            }
            @media (max-width: 768px) {
                .container { padding: 0 15px; }
            }
        </style>
    </head>
    <body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2>My Favorite Properties</h2>

        <?php if (empty($favorites)): ?>
            <div class="alert alert-info">You haven't saved any properties yet. <a href="../property/view_properties.php">Browse properties</a> to add some.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($favorites as $fav): 
                    $price_display = $fav['status'] == 'for_rent' 
                        ? number_format($fav['price']) . ' ' . $fav['currency'] . '/month'
                        : number_format($fav['price']) . ' ' . $fav['currency'];

                    // Build location string
                    $location_parts = [];
                    if (!empty($fav['namedistrict'])) $location_parts[] = $fav['namedistrict'];
                    if (!empty($fav['namesector'])) $location_parts[] = $fav['namesector'];
                    if (!empty($fav['nameCell'])) $location_parts[] = $fav['nameCell'];
                    $location = implode(' > ', $location_parts) ?: 'N/A';
                ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="property-card">
                        <?php if (!empty($fav['image']) && file_exists('../uploads/property/' . $fav['image'])): ?>
                            <img src="../uploads/property/<?= htmlspecialchars($fav['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($fav['title']) ?>">
                        <?php else: ?>
                            <div class="no-image"><i class="bi bi-image"></i> No Image</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title" title="<?= htmlspecialchars($fav['title']) ?>"><?= htmlspecialchars($fav['title']) ?></h5>
                            <div class="price"><?= $price_display ?></div>
                            <div class="details">
                                <div><i class="bi bi-house-door"></i> <?= ucfirst($fav['property_type']) ?></div>
                                <div><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($location) ?></div>
                                <?php if (!empty($fav['address'])): ?>
                                    <div><i class="bi bi-pin-map"></i> <?= htmlspecialchars($fav['address']) ?></div>
                                <?php endif; ?>
                                <?php if ($fav['property_type'] != 'land'): ?>
                                    <div><i class="bi bi-door-open"></i> <?= $fav['bedrooms'] ?> beds | <?= $fav['bathrooms'] ?> baths</div>
                                <?php endif; ?>
                                <?php if (!empty($fav['size_sqm'])): ?>
                                    <div><i class="bi bi-rulers"></i> <?= number_format($fav['size_sqm']) ?> sqm</div>
                                <?php endif; ?>
                                <div><span class="badge-status"><?= str_replace('_', ' ', $fav['status']) ?></span></div>
                            </div>
                            <a class="btn btn-success" href="../property/property_details.php?id=<?= $fav['property_id'] ?>">View Property</a>
                            <a class="btn btn-danger" href="?remove=<?= $fav['property_id'] ?>"  onclick="return confirm('Remove from favorites?')">Remove from Favorites</a>
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