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
    $stmt = $pdo->prepare("SELECT codecell, nameCell FROM cells WHERE sectorcode = ?");
    $stmt->execute([$_POST['sector_id']]);
    echo '<option value="">Select Cell</option>';
    while($row = $stmt->fetch()){
        echo '<option value="'.$row['codecell'].'">'.$row['nameCell'].'</option>';
    }
    exit();
}

/* ================= AJAX GET PROPERTY DATA (for edit modal) ================= */
if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_property' && isset($_GET['id'])){
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    $landlord_id = $_SESSION['user_id'];
    $property_id = intval($_GET['id']);

    if ($property_id <= 0) {
        echo json_encode(['error' => 'Invalid property ID']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ? AND owner_id = ?");
        $stmt->execute([$property_id, $landlord_id]);
        $property = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$property) {
            echo json_encode(['error' => 'Property not found or access denied']);
            exit();
        }

        // Fetch images
        $imgStmt = $pdo->prepare("SELECT image_url, is_primary FROM property_images WHERE property_id = ?");
        $imgStmt->execute([$property_id]);
        $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
        $property['images'] = $images;

        echo json_encode($property);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// ========== NORMAL PAGE LOAD (include header) ==========
include '../includes/header.php';

// Restrict access to landlords only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['landlord', 'agent'])) {
    header("Location: ../auth/login.php");
    exit();
}

$landlord_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Ensure upload directory exists
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
            $bedrooms, $bathrooms, $size, $landlord_id
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

/* ================= UPDATE PROPERTY ================= */
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
    $check->execute([$id, $landlord_id]);
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
            $bedrooms, $bathrooms, $size, $id, $landlord_id
        ]);

        // Upload new images (if any)
        if (!empty($_FILES['new_images']['name'][0])) {
            foreach ($_FILES['new_images']['tmp_name'] as $key => $tmp_name) {
                $imageName = time() . '_' . $_FILES['new_images']['name'][$key];
                move_uploaded_file($tmp_name, $uploadDir . $imageName);
                $imgStmt = $pdo->prepare("INSERT INTO property_images(property_id, image_url, is_primary) VALUES(?,?,?)");
                $imgStmt->execute([$id, $imageName, 0]); // new images are not primary
            }
        }
        $success = "Property updated successfully!";
    }
}

/* ================= DELETE PROPERTY ================= */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM properties WHERE id=? AND owner_id=?");
    $check->execute([$id, $landlord_id]);
    if ($check->fetch()) {
        // Delete images from folder and DB
        $images = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id=?");
        $images->execute([$id]);
        foreach($images as $img){
            $file = $uploadDir . $img['image_url'];
            if(file_exists($file)) unlink($file);
        }
        $pdo->prepare("DELETE FROM property_images WHERE property_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM properties WHERE id=? AND owner_id=?")->execute([$id, $landlord_id]);
    }
    header("Location: manage_property.php");
    exit();
}

/* ================= FETCH PROVINCES ================= */
$provinces = $pdo->query("SELECT provincecode, provincename FROM provinces")->fetchAll(PDO::FETCH_ASSOC);

/* ================= FETCH PROPERTIES ================= */
$stmt = $pdo->prepare("
    SELECT p.*,
        (SELECT image_url FROM property_images 
         WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM properties p
    WHERE owner_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between mb-3 align-items-center">
        <h3>My Properties</h3>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
            + Add Property
        </button>
    </div>

    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Status</th>
                <th>Price</th>
                <th>Bedrooms</th>
                <th>Bathrooms</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($properties as $p): ?>
            <tr>
                <td>
                <?php
                if(!empty($p['image']) && file_exists($uploadDir . $p['image'])){
                ?>
                    <img src="../uploads/property/<?= htmlspecialchars($p['image']) ?>"
                         width="80" height="60" class="rounded">
                <?php
                }else{
                    echo "No Image";
                }
                ?>
                </td>
                <td><?= htmlspecialchars($p['title']); ?></td>
                <td><?= $p['status']; ?></td>
                <td><?= number_format($p['price']); ?> <?= $p['currency']; ?></td>
                <td><?= $p['bedrooms']; ?></td>
                <td><?= $p['bathrooms']; ?></td>
                <td>
                    <button class="btn btn-warning btn-sm edit-btn" 
                            data-id="<?= $p['id']; ?>"
                            data-bs-toggle="modal" 
                            data-bs-target="#editPropertyModal">
                        Edit
                    </button>
                    <a href="?delete=<?= $p['id']; ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this property?')">
                        Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ================= ADD PROPERTY MODAL ================= -->
<div class="modal fade" id="addPropertyModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5>Add Property</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
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
                        <input class="form-control mb-2" name="currency" value="RWF" placeholder="Currency">

                        <select class="form-control mb-2" id="province" name="province" required>
                            <option value="">Select Province</option>
                            <?php foreach($provinces as $p): ?>
                                <option value="<?= $p['provincecode']; ?>"><?= $p['provincename']; ?></option>
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

<!-- ================= EDIT PROPERTY MODAL ================= -->
<div class="modal fade" id="editPropertyModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5>Edit Property</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
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
                        <input class="form-control mb-2" name="currency" id="edit_currency" value="RWF" placeholder="Currency">

                        <select class="form-control mb-2" name="province" id="edit_province" required>
                            <option value="">Select Province</option>
                            <?php foreach($provinces as $p): ?>
                                <option value="<?= $p['provincecode']; ?>"><?= $p['provincename']; ?></option>
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

                        <label>Current Images</label>
                        <div id="current_images" class="mb-2"></div>

                        <label>Add New Images</label>
                        <input type="file" name="new_images[]" class="form-control mb-2" multiple>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" name="edit_property">Update Property</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function(){
    // Add modal location cascades
    $("#province").change(function(){
        var province_id = $(this).val();
        $.post("manage_property.php", {province_id: province_id}, function(data){
            $("#district").html(data);
            $("#sector").html('<option value="">Select Sector</option>');
            $("#cell").html('<option value="">Select Cell</option>');
        });
    });

    $("#district").change(function(){
        var district_id = $(this).val();
        $.post("manage_property.php", {district_id: district_id}, function(data){
            $("#sector").html(data);
            $("#cell").html('<option value="">Select Cell</option>');
        });
    });

    $("#sector").change(function(){
        var sector_id = $(this).val();
        $.post("manage_property.php", {sector_id: sector_id}, function(data){
            $("#cell").html(data);
        });
    });

    // Edit modal - fetch property data via AJAX
    $(".edit-btn").click(function(){
        var property_id = $(this).data('id');
        $("#edit_property_id").val(property_id);

        // Clear previous data and show loading
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
        $('#current_images').html('<p>Loading...</p>');

        $.ajax({
            url: "manage_property.php",
            type: "GET",
            data: {ajax: 'get_property', id: property_id},
            dataType: "json",
            success: function(data){
                console.log("Received data:", data); // DEBUG: check console
                if(data.error){
                    alert("Error: " + data.error);
                    $('#editPropertyModal').modal('hide');
                    return;
                }
                // Populate basic fields
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

                // Set province and then trigger cascades with saved district, sector, cell
                if(data.province){
                    $('#edit_province').val(data.province).trigger('change', [
                        data.district || '', 
                        data.sector || '', 
                        data.cells || ''
                    ]);
                }

                // Show current images
                var imagesHtml = '';
                if(data.images && data.images.length > 0){
                    data.images.forEach(function(img){
                        imagesHtml += '<div><img src="../uploads/property/' + img.image_url + '" width="60" height="40" style="object-fit:cover;"> ' + (img.is_primary ? '(Primary)' : '') + '</div>';
                    });
                } else {
                    imagesHtml = '<p>No images</p>';
                }
                $('#current_images').html(imagesHtml);
            },
            error: function(jqXHR, textStatus, errorThrown){
                console.error("AJAX error:", textStatus, errorThrown);
                console.log("Response text:", jqXHR.responseText); // Show raw response
                alert("Failed to load property data. Check console for details.");
                $('#editPropertyModal').modal('hide');
            }
        });
    });

    // For edit modal cascades
    $('#edit_province').change(function(e, districtCode, sectorCode, cellCode){
        var province_id = $(this).val();
        if(!province_id) return;
        $.post("manage_property.php", {province_id: province_id}, function(data){
            $('#edit_district').html(data);
            if(districtCode){
                $('#edit_district').val(districtCode).trigger('change', [sectorCode, cellCode]);
            }
        });
    });

    $('#edit_district').change(function(e, sectorCode, cellCode){
        var district_id = $(this).val();
        if(!district_id) return;
        $.post("manage_property.php", {district_id: district_id}, function(data){
            $('#edit_sector').html(data);
            if(sectorCode){
                $('#edit_sector').val(sectorCode).trigger('change', [cellCode]);
            }
        });
    });

    $('#edit_sector').change(function(e, cellCode){
        var sector_id = $(this).val();
        if(!sector_id) return;
        $.post("manage_property.php", {sector_id: sector_id}, function(data){
            $('#edit_cells').html(data);
            if(cellCode){
                $('#edit_cells').val(cellCode);
            }
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>