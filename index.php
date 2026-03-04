<?php
session_start();
require 'config/database.php'; // adjust path as needed

// Fetch approved ads within date range for slider
$ads = [];
$stmt = $pdo->prepare("
    SELECT * FROM ads 
    WHERE status = 'approved' 
    AND start_date <= CURDATE() 
    AND end_date >= CURDATE()
    ORDER BY created_at DESC
");
$stmt->execute();
$ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build base query for properties
$sql = "SELECT p.*, d.namedistrict,
               (SELECT image_url FROM property_images 
                WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM properties p
        LEFT JOIN districts d ON p.district = d.districtcode
        WHERE p.status IN ('for_sale', 'for_rent')";
$params = [];

// Check if search filters are applied
$search_active = false;
if (!empty($_GET['location']) || !empty($_GET['type']) || !empty($_GET['min_price']) || !empty($_GET['max_price'])) {
    $search_active = true;

    if (!empty($_GET['location'])) {
        $sql .= " AND p.district = ?";
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
    $sql .= " ORDER BY p.created_at DESC";
} else {
    // No search: show latest 4 featured properties
    $sql .= " ORDER BY p.created_at DESC LIMIT 4";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RwandaPropertyHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* ================= HERO ================= */
        .hero {
            height: 90vh;
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
                        url('images/hero.jpg') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            padding: 0 80px;
            color: white;
        }

        .hero h1 {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
        }

        .search-bar {
            background: white;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            width: 100%;
            max-width: 1000px;
        }

        .search-bar select,
        .search-bar input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 150px;
            flex: 1 1 auto;
        }

        .search-bar button {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            flex: 0 0 auto;
        }

        .search-bar button:hover {
            background: #218838;
        }

        /* ================= AD SLIDER (two cards per slide) ================= */
        .ad-carousel {
    margin: 30px 0;
}
.ad-slide-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    height: 100%;
    display: flex;
    flex-direction: column;
}
.ad-slide-card img,
.ad-slide-card video {
    width: 100%;
    height: 200px;
    object-fit: cover;
}
.ad-slide-card .card-body {
    padding: 15px;
    height: 200px;
    overflow-y: auto;
}
.ad-slide-card .card-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 8px;
}
.ad-slide-card .card-text {
    color: #555;
    margin-bottom: 15px;
}
.ad-slide-card .btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
}
.ad-slide-card .btn:hover {
    background: #218838;
}
.carousel-control-prev,
.carousel-control-next {
    width: 5%;
}
.carousel-indicators {
    margin-bottom: -30px;
}

        /* ================= SECTION TITLE ================= */
        .section {
            padding: 70px 80px;
        }

        .section h2 {
            margin-bottom: 40px;
            font-size: 28px;
        }

        /* ================= PROPERTY GRID ================= */
        .properties {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .card-body {
            padding: 15px;
        }

        .price {
            color: #28a745;
            font-weight: bold;
            margin: 10px 0;
            font-size: 1.1rem;
        }

        .view-btn {
            margin-top: 30px;
            text-align: center;
        }

        .view-btn a {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .view-btn a:hover {
            background: #218838;
        }

        /* ================= HOW IT WORKS ================= */
        .how-it-works {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            text-align: center;
        }

        .step {
            flex: 1;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* ================= CTA ================= */
        .cta {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),
                        url('images/city.jpg') center/cover no-repeat;
            color: white;
            padding: 70px 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .cta button {
            background: #28a745;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .cta button:hover {
            background: #218838;
        }

        /* ================= FOOTER ================= */
        footer {
            background: #1c1f26;
            color: #bbb;
            padding: 30px 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        footer a {
            color: #bbb;
            text-decoration: none;
            margin-right: 20px;
        }

        footer a:hover {
            color: white;
        }

        .no-image {
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 180px;
            color: #999;
            font-size: 14px;
        }

        @media(max-width: 900px){
            .how-it-works {
                flex-direction: column;
            }

            header {
                flex-direction: column;
                gap: 10px;
            }

            .hero {
                padding: 0 30px;
            }

            .section {
                padding: 40px 30px;
            }

            .search-bar {
                flex-direction: column;
            }

            .ad-carousel .row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="logo">RwandaPropertyHub</div>
    <nav>
        <a href="#">Buy</a>
        <a href="#">Rent</a>
        <a href="#">Sell</a>
        <a href="#">Market Trends</a>
        <div class="auth-buttons">
            <a href="auth/login.php" class="login">Login</a>
            <a href="auth/login.php" class="signup">Sign Up</a>
        </div>
    </nav>
</header>

<section class="hero">
    <h1>Find Your Dream Home in Rwanda</h1>
    <p>Buy, Rent, or Invest with Ease</p>

    <!-- Form submits to the same page (auto‑search) -->
    <form class="search-bar" method="GET" action="">
        <select name="location">
            <option value="">Location</option>
            <?php
            $districts = $pdo->query("SELECT districtcode, namedistrict FROM districts WHERE districtcode NOT LIKE '___00' ORDER BY namedistrict")->fetchAll();
            $selected_loc = $_GET['location'] ?? '';
            foreach($districts as $d){
                $sel = ($d['districtcode'] == $selected_loc) ? 'selected' : '';
                echo '<option value="'.$d['districtcode'].'" '.$sel.'>'.htmlspecialchars($d['namedistrict']).'</option>';
            }
            ?>
        </select>

        <select name="type">
            <option value="">Property Type</option>
            <?php
            $types = ['house'=>'House', 'apartment'=>'Apartment', 'land'=>'Land', 'commercial'=>'Commercial'];
            $selected_type = $_GET['type'] ?? '';
            foreach($types as $val => $label){
                $sel = ($val == $selected_type) ? 'selected' : '';
                echo '<option value="'.$val.'" '.$sel.'>'.$label.'</option>';
            }
            ?>
        </select>

        <input type="number" name="min_price" placeholder="Min Price" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>">
        <input type="number" name="max_price" placeholder="Max Price" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">

        <button type="submit">Search</button>
    </form>
</section>

<!-- Ad Carousel Section (only if ads exist) -->
<?php if (!empty($ads)): ?>
<div class="container ad-carousel">
    <h2 class="mb-3">Sponsored Ads</h2>
    <div id="adCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach ($ads as $index => $ad): ?>
                <button type="button" data-bs-target="#adCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index == 0 ? 'active' : '' ?>" aria-label="Slide <?= $index+1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($ads as $index => $ad): ?>
                <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
                    <div class="row g-3">
                        <!-- Left card: Media -->
                        <div class="col-md-6">
                            <div class="ad-slide-card">
                                <?php if ($ad['media_type'] == 'video'): ?>
                                    <video src="uploads/ads/<?= htmlspecialchars($ad['file_path']) ?>" autoplay muted loop playsinline></video>
                                <?php else: ?>
                                    <img src="uploads/ads/<?= htmlspecialchars($ad['file_path']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Right card: Description -->
                        <div class="col-md-6">
                            <div class="ad-slide-card">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($ad['title']) ?></h5>
                                    <?php if (!empty($ad['description'])): ?>
                                        <p class="card-text"><?= nl2br(htmlspecialchars($ad['description'])) ?></p>
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($ad['target_url']) ?>" class="btn" target="_blank" rel="sponsored">Learn More</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#adCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#adCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</div>
<?php endif; ?>

<section class="section">
    <h2><?= $search_active ? 'Search Results' : 'Featured Properties' ?></h2>

    <?php if (empty($properties)): ?>
        <p class="text-center">No properties found matching your criteria.</p>
    <?php else: ?>
    <div class="properties">
        <?php foreach($properties as $prop): 
            // Determine price display
            if($prop['status'] == 'for_rent') {
                $price_display = number_format($prop['price']) . ' ' . $prop['currency'] . ' / month';
            } else {
                $price_display = number_format($prop['price']) . ' ' . $prop['currency'];
            }

            // Build location string (district name)
            $location = $prop['namedistrict'] ?? 'Unknown';

            // Build features string
            $features = [];
            if($prop['property_type'] == 'land') {
                if($prop['size_sqm']) $features[] = number_format($prop['size_sqm']) . ' sqm';
            } else {
                if($prop['bedrooms']) $features[] = $prop['bedrooms'] . ' Beds';
                if($prop['bathrooms']) $features[] = $prop['bathrooms'] . ' Baths';
            }
            $features[] = $location;
            $feature_text = implode(' | ', $features);
        ?>
        <a href="property/property_details.php?id=<?= $prop['id'] ?>" class="card">
            <?php if(!empty($prop['primary_image']) && file_exists('uploads/property/'.$prop['primary_image'])): ?>
                <img src="uploads/property/<?= htmlspecialchars($prop['primary_image']) ?>" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div class="no-image">No Image</div>
            <?php endif; ?>
            <div class="card-body">
                <h3><?= htmlspecialchars($prop['title']) ?></h3>
                <div class="price"><?= $price_display ?></div>
                <p><?= htmlspecialchars($feature_text) ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$search_active): ?>
    <div class="view-btn">
        <a href="property/properties.php">View All Properties</a>
    </div>
    <?php endif; ?>
</section>

<section class="section">
    <h2>How It Works</h2>

    <div class="how-it-works">
        <div class="step">
            <h3>1. Search</h3>
            <p>Find properties easily</p>
        </div>
        <div class="step">
            <h3>2. Connect</h3>
            <p>Chat with agents directly</p>
        </div>
        <div class="step">
            <h3>3. Move In</h3>
            <p>Schedule a visit and close the deal</p>
        </div>
    </div>
</section>

<section class="cta">
    <div>
        <h2>List Your Property With Us</h2>
        <p>Reach thousands of buyers & renters in Rwanda.</p>
    </div>
    <a href="auth/login.php?signup=landlord"><button>Get Started</button></a>
</section>

<footer>
    <div>
        <a href="#">About Us</a>
        <a href="#">Contact Us</a>
        <a href="#">FAQs</a>
        <a href="#">Blog</a>
    </div>
    <div>
        © 2023 RwandaPropertyHub. All Rights Reserved.
    </div>
</footer>

<!-- Bootstrap JS (required for carousel) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>