<?php
session_start();
require '../config/database.php';

/* ================= AJAX LOCATION (cascading dropdowns) ================= */
if(isset($_POST['province_id'])){
    $stmt = $pdo->prepare("SELECT districtcode, namedistrict FROM districts WHERE provincecode = ?");
    $stmt->execute([$_POST['province_id']]);
    echo '<option value="">Select District</option>';
    while($row = $stmt->fetch()){
        echo '<option value="'.$row['districtcode'].'">'.$row['namedistrict'].'</option>';
    }
    exit();
}

if(isset($_POST['district_id'])){
    $stmt = $pdo->prepare("SELECT sectorcode, namesector FROM sectors WHERE districtcode = ?");
    $stmt->execute([$_POST['district_id']]);
    echo '<option value="">Select Sector</option>';
    while($row = $stmt->fetch()){
        echo '<option value="'.$row['sectorcode'].'">'.$row['namesector'].'</option>';
    }
    exit();
}

if(isset($_POST['sector_id'])){
    // Corrected column names: codecell, namecell
    $stmt = $pdo->prepare("SELECT codecell, namecell FROM cells WHERE sectorcode = ?");
    $stmt->execute([$_POST['sector_id']]);
    echo '<option value="">Select Cell</option>';
    while($row = $stmt->fetch()){
        echo '<option value="'.$row['codecell'].'">'.$row['namecell'].'</option>';
    }
    exit();
}

/* ================= AJAX GET PROPERTY DATA (for edit modal) ================= */
if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_property' && isset($_GET['id'])){
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['landlord','agent'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized - please log in as landlord/agent']);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $property_id = intval($_GET['id']);

    if ($property_id <= 0) {
        echo json_encode(['error' => 'Invalid property ID']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ? AND owner_id = ?");
        $stmt->execute([$property_id, $user_id]);
        $property = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$property) {
            echo json_encode(['error' => 'Property not found or access denied']);
            exit();
        }

        echo json_encode($property);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

/* ================= AJAX GET IMAGES (for image modal) ================= */
if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_images' && isset($_GET['id'])){
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['landlord','agent'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $property_id = intval($_GET['id']);

    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM properties WHERE id = ? AND owner_id = ?");
    $check->execute([$property_id, $user_id]);
    if (!$check->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }

    $imgStmt = $pdo->prepare("SELECT id, image_url, is_primary FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
    $imgStmt->execute([$property_id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['images' => $images]);
    exit();
}

// ========== NORMAL PAGE LOAD ==========
// Restrict access to landlords and agents
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['landlord','agent'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

$uploadDir = "../uploads/property/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* ================= ADD PROPERTY ================= */
if (isset($_POST['add_property'])) {
    $title        = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $type         = $_POST['property_type'];
    $status       = $_POST['status'];
    $price        = $_POST['price'];
    $currency     = $_POST['currency'];
    $province     = $_POST['province'];
    $district     = $_POST['district'];
    $sector       = $_POST['sector'];
    $cells        = $_POST['cells'];
    $address      = trim($_POST['address']);
    $latitude     = $_POST['latitude'] ?: null;
    $longitude    = $_POST['longitude'] ?: null;
    $bedrooms     = $_POST['bedrooms'] ?: 0;
    $bathrooms    = $_POST['bathrooms'] ?: 0;
    $size         = $_POST['size_sqm'] ?: null;

    if (empty($title) || empty($price)) {
        $error = "Title and Price are required.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO properties 
            (title, description, property_type, status, price, currency,
             province, district, sector, cells, address, latitude, longitude,
             bedrooms, bathrooms, size_sqm, owner_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title, $description, $type, $status, $price, $currency,
            $province, $district, $sector, $cells, $address, $latitude, $longitude,
            $bedrooms, $bathrooms, $size, $user_id
        ]);

        $property_id = $pdo->lastInsertId();

        // Upload images
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $imageName = time() . '_' . $_FILES['images']['name'][$key];
                move_uploaded_file($tmp_name, $uploadDir . $imageName);
                $is_primary = ($key == 0) ? 1 : 0;
                $imgStmt = $pdo->prepare("INSERT INTO property_images(property_id, image_url, is_primary) VALUES(?,?,?)");
                $imgStmt->execute([$property_id, $imageName, $is_primary]);
            }
        }
        $success = "Property added successfully!";
    }
}

/* ================= UPDATE PROPERTY DETAILS (no image changes) ================= */
if (isset($_POST['edit_property'])) {
    $id           = intval($_POST['property_id']);
    $title        = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $type         = $_POST['property_type'];
    $status       = $_POST['status'];
    $price        = $_POST['price'];
    $currency     = $_POST['currency'];
    $province     = $_POST['province'];
    $district     = $_POST['district'];
    $sector       = $_POST['sector'];
    $cells        = $_POST['cells'];
    $address      = trim($_POST['address']);
    $latitude     = $_POST['latitude'] ?: null;
    $longitude    = $_POST['longitude'] ?: null;
    $bedrooms     = $_POST['bedrooms'] ?: 0;
    $bathrooms    = $_POST['bathrooms'] ?: 0;
    $size         = $_POST['size_sqm'] ?: null;

    // Check ownership
    $check = $pdo->prepare("SELECT id FROM properties WHERE id=? AND owner_id=?");
    $check->execute([$id, $user_id]);
    if (!$check->fetch()) {
        $error = "You do not own this property.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE properties SET
                title=?, description=?, property_type=?, status=?, price=?, currency=?,
                province=?, district=?, sector=?, cells=?, address=?, latitude=?, longitude=?,
                bedrooms=?, bathrooms=?, size_sqm=?
            WHERE id=? AND owner_id=?
        ");
        $stmt->execute([
            $title, $description, $type, $status, $price, $currency,
            $province, $district, $sector, $cells, $address, $latitude, $longitude,
            $bedrooms, $bathrooms, $size, $id, $user_id
        ]);

        $success = "Property details updated successfully!";
    }
}

/* ================= UPDATE IMAGES ONLY ================= */
if (isset($_POST['update_images'])) {
    $id = intval($_POST['property_id']);

    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM properties WHERE id=? AND owner_id=?");
    $check->execute([$id, $user_id]);
    if (!$check->fetch()) {
        $error = "You do not own this property.";
    } else {
        // 1. Handle image deletions
        if (!empty($_POST['delete_images'])) {
            $delete_ids = $_POST['delete_images'];
            $placeholders = implode(',', array_fill(0, count($delete_ids), '?'));
            $imgStmt = $pdo->prepare("SELECT image_url FROM property_images WHERE id IN ($placeholders) AND property_id = ?");
            $params = array_merge($delete_ids, [$id]);
            $imgStmt->execute($params);
            $images_to_delete = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($images_to_delete as $img) {
                $file = $uploadDir . $img;
                if (file_exists($file)) unlink($file);
            }
            $delStmt = $pdo->prepare("DELETE FROM property_images WHERE id IN ($placeholders) AND property_id = ?");
            $delStmt->execute(array_merge($delete_ids, [$id]));
        }

        // 2. Set primary image if specified
        if (!empty($_POST['primary_image'])) {
            $primary_id = intval($_POST['primary_image']);
            $pdo->prepare("UPDATE property_images SET is_primary = 0 WHERE property_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE property_images SET is_primary = 1 WHERE id = ? AND property_id = ?")->execute([$primary_id, $id]);
        }

        // 3. Upload new images
        if (!empty($_FILES['new_images']['name'][0])) {
            // Check if there are any images left after deletions; if none, make first new image primary
            $imgCount = $pdo->prepare("SELECT COUNT(*) FROM property_images WHERE property_id = ?");
            $imgCount->execute([$id]);
            $hasImages = $imgCount->fetchColumn() > 0;

            foreach ($_FILES['new_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['new_images']['error'][$key] == 0) {
                    $imageName = time() . '_' . $_FILES['new_images']['name'][$key];
                    move_uploaded_file($tmp_name, $uploadDir . $imageName);
                    $is_primary = (!$hasImages && $key == 0) ? 1 : 0;
                    $imgStmt = $pdo->prepare("INSERT INTO property_images(property_id, image_url, is_primary) VALUES(?,?,?)");
                    $imgStmt->execute([$id, $imageName, $is_primary]);
                }
            }
        }

        $success = "Images updated successfully!";
    }
}

/* ================= DELETE PROPERTY ================= */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM properties WHERE id=? AND owner_id=?");
    $check->execute([$id, $user_id]);
    if ($check->fetch()) {
        // Delete images from folder and DB
        $images = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id=?");
        $images->execute([$id]);
        foreach($images as $img){
            $file = $uploadDir . $img['image_url'];
            if(file_exists($file)) unlink($file);
        }
        $pdo->prepare("DELETE FROM property_images WHERE property_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM properties WHERE id=? AND owner_id=?")->execute([$id, $user_id]);
    }
    header("Location: properties.php");
    exit();
}

/* ================= FETCH PROVINCES ================= */
$provinces = $pdo->query("SELECT provincecode, provincename FROM provinces")->fetchAll(PDO::FETCH_ASSOC);

/* ================= FETCH PROPERTIES ================= */
$stmt = $pdo->prepare("
    SELECT p.*,
        (SELECT image_url FROM property_images 
         WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM properties p
    WHERE owner_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .container { margin-top: 5rem; margin-bottom: 50px; }
        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .property-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s;
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
        }
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
        .btn-group {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .btn-group .btn {
            flex: 1;
        }
        .modal-body .image-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .image-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            width: 120px;
        }
        .image-item img {
            width: 100px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between mb-4 align-items-center">
        <h3>My Properties</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
            <i class="bi bi-plus-circle"></i> Add Property
        </button>
    </div>

    <div class="property-grid">
        <?php foreach($properties as $p): 
            $price_display = $p['status'] == 'for_rent' 
                ? number_format($p['price']) . ' ' . $p['currency'] . '/month'
                : number_format($p['price']) . ' ' . $p['currency'];
            
            // Get district name for location
            $locStmt = $pdo->prepare("SELECT namedistrict FROM districts WHERE districtcode = ?");
            $locStmt->execute([$p['district']]);
            $districtName = $locStmt->fetchColumn() ?: 'Unknown';
        ?>
        <div class="property-card">
            <?php if(!empty($p['primary_image']) && file_exists($uploadDir . $p['primary_image'])): ?>
                <img src="../uploads/property/<?= htmlspecialchars($p['primary_image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['title']) ?>">
            <?php else: ?>
                <div class="no-image"><i class="bi bi-image"></i> No Image</div>
            <?php endif; ?>
            <div class="card-body">
                <h5 class="card-title" title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></h5>
                <div class="price"><?= $price_display ?></div>
                <div class="details">
                    <div><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($districtName) ?></div>
                    <?php if($p['property_type'] != 'land'): ?>
                        <div><i class="bi bi-door-open"></i> <?= $p['bedrooms'] ?> beds | <?= $p['bathrooms'] ?> baths</div>
                    <?php else: ?>
                        <div><i class="bi bi-rulers"></i> <?= number_format($p['size_sqm']) ?> sqm</div>
                    <?php endif; ?>
                    <div><span class="badge bg-<?= $p['status']=='for_rent'?'info':'success' ?>"><?= str_replace('_', ' ', $p['status']) ?></span></div>
                </div>
                <div class="btn-group">
                    <button class="btn btn-outline-primary btn-sm edit-btn" data-id="<?= $p['id'] ?>" data-bs-toggle="modal" data-bs-target="#editPropertyModal">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button class="btn btn-outline-secondary btn-sm manage-images-btn" data-id="<?= $p['id'] ?>" data-bs-toggle="modal" data-bs-target="#imageModal">
                        <i class="bi bi-images"></i> Images
                    </button>
                </div>
                <div class="mt-2">
                    <a href="?delete=<?= $p['id'] ?>" class="btn btn-outline-danger btn-sm w-100" onclick="return confirm('Delete this property?')">
                        <i class="bi bi-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ================= ADD PROPERTY MODAL ================= -->
<div class="modal fade" id="addPropertyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" action="properties.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row">
                    <div class="col-md-6">
                        <input class="form-control mb-2" name="title" placeholder="Title" required>
                        <textarea class="form-control mb-2" name="description" placeholder="Description"></textarea>
                        <select class="form-control mb-2" name="property_type">
                            <option value="house">House</option>
                            <option value="apartment">Apartment</option>
                            <option value="land">Land</option>
                            <option value="commercial">Commercial</option>
                        </select>
                        <select class="form-control mb-2" name="status">
                            <option value="for_sale">For Sale</option>
                            <option value="for_rent">For Rent</option>
                        </select>
                        <input class="form-control mb-2" name="price" placeholder="Price" required>
                        <input class="form-control mb-2" name="currency" value="RWF">
                        <select class="form-control mb-2" id="province" name="province" required>
                            <option value="">Select Province</option>
                            <?php foreach($provinces as $p): ?>
                                <option value="<?= $p['provincecode'] ?>"><?= $p['provincename'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-control mb-2" id="district" name="district" required>
                            <option value="">Select District</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select class="form-control mb-2" id="sector" name="sector" required>
                            <option value="">Select Sector</option>
                        </select>
                        <select class="form-control mb-2" id="cell" name="cells" required>
                            <option value="">Select Cell</option>
                        </select>
                        <input class="form-control mb-2" name="address" placeholder="Address">
                        <input class="form-control mb-2" name="latitude" placeholder="Latitude">
                        <input class="form-control mb-2" name="longitude" placeholder="Longitude">
                        <input class="form-control mb-2" name="bedrooms" placeholder="Bedrooms">
                        <input class="form-control mb-2" name="bathrooms" placeholder="Bathrooms">
                        <input class="form-control mb-2" name="size_sqm" placeholder="Size (sqm)">
                        <label>Upload Images</label>
                        <input type="file" name="images[]" class="form-control mb-2" multiple>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" name="add_property">Save Property</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= EDIT PROPERTY MODAL (no images) ================= -->
<div class="modal fade" id="editPropertyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="properties.php">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row">
                    <input type="hidden" name="property_id" id="edit_property_id">

                    <div class="col-md-6">
                        <input class="form-control mb-2" name="title" id="edit_title" placeholder="Title" required>
                        <textarea class="form-control mb-2" name="description" id="edit_description" placeholder="Description"></textarea>
                        <select class="form-control mb-2" name="property_type" id="edit_property_type">
                            <option value="house">House</option>
                            <option value="apartment">Apartment</option>
                            <option value="land">Land</option>
                            <option value="commercial">Commercial</option>
                        </select>
                        <select class="form-control mb-2" name="status" id="edit_status">
                            <option value="for_sale">For Sale</option>
                            <option value="for_rent">For Rent</option>
                        </select>
                        <input class="form-control mb-2" name="price" id="edit_price" placeholder="Price" required>
                        <input class="form-control mb-2" name="currency" id="edit_currency" value="RWF">
                        <select class="form-control mb-2" name="province" id="edit_province" required>
                            <option value="">Select Province</option>
                            <?php foreach($provinces as $p): ?>
                                <option value="<?= $p['provincecode'] ?>"><?= $p['provincename'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-control mb-2" name="district" id="edit_district" required>
                            <option value="">Select District</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select class="form-control mb-2" name="sector" id="edit_sector" required>
                            <option value="">Select Sector</option>
                        </select>
                        <select class="form-control mb-2" name="cells" id="edit_cells" required>
                            <option value="">Select Cell</option>
                        </select>
                        <input class="form-control mb-2" name="address" id="edit_address" placeholder="Address">
                        <input class="form-control mb-2" name="latitude" id="edit_latitude" placeholder="Latitude">
                        <input class="form-control mb-2" name="longitude" id="edit_longitude" placeholder="Longitude">
                        <input class="form-control mb-2" name="bedrooms" id="edit_bedrooms" placeholder="Bedrooms">
                        <input class="form-control mb-2" name="bathrooms" id="edit_bathrooms" placeholder="Bathrooms">
                        <input class="form-control mb-2" name="size_sqm" id="edit_size_sqm" placeholder="Size (sqm)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" name="edit_property">Update Property</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MANAGE IMAGES MODAL ================= -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" action="properties.php">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="property_id" id="image_property_id">
                    <div id="image-list" class="image-grid"></div>
                    <div class="mb-3">
                        <label for="new_images" class="form-label">Add New Images</label>
                        <input type="file" name="new_images[]" class="form-control" multiple accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" name="update_images">Save Image Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    // Add modal cascades
    $("#province").change(function(){
        var province_id = $(this).val();
        $.post("properties.php", {province_id: province_id}, function(data){
            $("#district").html(data);
            $("#sector").html('<option value="">Select Sector</option>');
            $("#cell").html('<option value="">Select Cell</option>');
        });
    });
    $("#district").change(function(){
        var district_id = $(this).val();
        $.post("properties.php", {district_id: district_id}, function(data){
            $("#sector").html(data);
            $("#cell").html('<option value="">Select Cell</option>');
        });
    });
    $("#sector").change(function(){
        var sector_id = $(this).val();
        $.post("properties.php", {sector_id: sector_id}, function(data){
            $("#cell").html(data);
        });
    });

    // Edit modal cascades
    $('#edit_province').change(function(e, districtCode, sectorCode, codecell){
        var province_id = $(this).val();
        if(!province_id) return;
        $.post("properties.php", {province_id: province_id}, function(data){
            $('#edit_district').html(data);
            if(districtCode){
                $('#edit_district').val(districtCode).trigger('change', [sectorCode, codecell]);
            }
        });
    });
    $('#edit_district').change(function(e, sectorCode, codecell){
        var district_id = $(this).val();
        if(!district_id) return;
        $.post("properties.php", {district_id: district_id}, function(data){
            $('#edit_sector').html(data);
            if(sectorCode){
                $('#edit_sector').val(sectorCode).trigger('change', [codecell]);
            }
        });
    });
    $('#edit_sector').change(function(e, codecell){
        var sector_id = $(this).val();
        if(!sector_id) return;
        $.post("properties.php", {sector_id: sector_id}, function(data){
            $('#edit_cells').html(data);
            if(codecell){
                $('#edit_cells').val(codecell);
            }
        });
    });

    // Load property data into edit modal
    $(".edit-btn").click(function(){
        var property_id = $(this).data('id');
        $("#edit_property_id").val(property_id);

        // Clear previous
        $('#edit_title').val('');
        $('#edit_description').val('');
        $('#edit_property_type').val('house');
        $('#edit_status').val('for_sale');
        $('#edit_price').val('');
        $('#edit_currency').val('RWF');
        $('#edit_address').val('');
        $('#edit_latitude').val('');
        $('#edit_longitude').val('');
        $('#edit_bedrooms').val('');
        $('#edit_bathrooms').val('');
        $('#edit_size_sqm').val('');
        $('#edit_province').val('');
        $('#edit_district').html('<option value="">Select District</option>');
        $('#edit_sector').html('<option value="">Select Sector</option>');
        $('#edit_cells').html('<option value="">Select Cell</option>');

        $.ajax({
            url: "properties.php",
            type: "GET",
            data: {ajax: 'get_property', id: property_id},
            dataType: "json",
            success: function(data){
                if(data.error){
                    alert("Error: " + data.error);
                    $('#editPropertyModal').modal('hide');
                    return;
                }
                $('#edit_title').val(data.title || '');
                $('#edit_description').val(data.description || '');
                $('#edit_property_type').val(data.property_type || 'house');
                $('#edit_status').val(data.status || 'for_sale');
                $('#edit_price').val(data.price || '');
                $('#edit_currency').val(data.currency || 'RWF');
                $('#edit_address').val(data.address || '');
                $('#edit_latitude').val(data.latitude || '');
                $('#edit_longitude').val(data.longitude || '');
                $('#edit_bedrooms').val(data.bedrooms || '');
                $('#edit_bathrooms').val(data.bathrooms || '');
                $('#edit_size_sqm').val(data.size_sqm || '');

                if(data.province){
                    $('#edit_province').val(data.province).trigger('change', [
                        data.district || '', 
                        data.sector || '', 
                        data.cells || ''
                    ]);
                }
            },
            error: function(jqXHR, textStatus, errorThrown){
                console.error("AJAX error:", textStatus, errorThrown);
                console.log("Response text:", jqXHR.responseText);
                alert("Failed to load property data. Check console for details.");
                $('#editPropertyModal').modal('hide');
            }
        });
    });

    // Load images into image modal
    $(".manage-images-btn").click(function(){
        var property_id = $(this).data('id');
        $("#image_property_id").val(property_id);
        $("#image-list").html('<p>Loading...</p>');

        $.ajax({
            url: "properties.php",
            type: "GET",
            data: {ajax: 'get_images', id: property_id},
            dataType: "json",
            success: function(data){
                if(data.error){
                    alert("Error: " + data.error);
                    $('#imageModal').modal('hide');
                    return;
                }
                var html = '';
                if(data.images.length > 0){
                    data.images.forEach(function(img){
                        html += '<div class="image-item">';
                        html += '<img src="../uploads/property/' + img.image_url + '">';
                        html += '<div>';
                        html += '<input type="checkbox" name="delete_images[]" value="' + img.id + '"> Delete<br>';
                        html += '<input type="radio" name="primary_image" value="' + img.id + '" ' + (img.is_primary == 1 ? 'checked' : '') + '> Primary';
                        html += '</div>';
                        html += '</div>';
                    });
                } else {
                    html = '<p>No images yet.</p>';
                }
                $("#image-list").html(html);
            },
            error: function(jqXHR){
                console.error("AJAX error:", jqXHR.responseText);
                alert("Failed to load images.");
                $('#imageModal').modal('hide');
            }
        });
    });
});
</script>
</body>
</html>