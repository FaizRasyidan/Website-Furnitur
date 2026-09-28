<?php
// Footer include file for all user pages
// This file includes categories and store locations data for the footer

// Ensure database connection exists
if (!isset($conn)) {
    require_once __DIR__ . '/../database/config.php';
}

// Ensure userId is set
if (!isset($userId)) {
    $userId = $_SESSION['user_id'] ?? 0;
}

// Fetch categories from database if not already set
if (!isset($categories)) {
    $categories = [];
    $catResult = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($catResult) {
        while ($row = $catResult->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

// Fetch store locations from database if not already set
if (!isset($storeLocations)) {
    $storeLocations = [];
    // Check if table exists first
    $tableCheck = $conn->query("SHOW TABLES LIKE 'store_locations'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $locResult = $conn->query("SELECT id, name, latitude, longitude, address FROM store_locations WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY id ASC");
        if ($locResult && $locResult->num_rows > 0) {
            while ($row = $locResult->fetch_assoc()) {
                $storeLocations[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)($row['name'] ?? 'Toko'),
                    'latitude' => (float)($row['latitude'] ?? 0),
                    'longitude' => (float)($row['longitude'] ?? 0),
                    'address' => (string)($row['address'] ?? '')
                ];
            }
        }
    }
}

// Prepare store locations JSON for JavaScript if not already set
if (!isset($storeLocationsJson)) {
    $storeLocationsForJson = [];
    foreach ($storeLocations as $loc) {
        $storeLocationsForJson[] = [
            'id' => (int)($loc['id'] ?? 0),
            'name' => (string)($loc['name'] ?? 'Toko'),
            'latitude' => (float)($loc['latitude'] ?? 0),
            'longitude' => (float)($loc['longitude'] ?? 0),
            'address' => (string)($loc['address'] ?? '')
        ];
    }
    $storeLocationsJson = json_encode($storeLocationsForJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (!$storeLocationsJson || $storeLocationsJson === 'null' || $storeLocationsJson === '[]') {
        $storeLocationsJson = '[]';
    }
}
?>

<!-- Start Footer Section -->
<footer class="footer-section">
    <div class="container relative">

        <div class="row g-5 mb-5">
            <div class="col-lg-4">
                <div class="mb-4 footer-logo-wrap"><a href="user_page.php" class="footer-logo">Offo Living<span>.</span></a></div>
                <p class="mb-4">Terpercaya untuk segal Furnitur yang berkualitas dan terjamin ke asliannya hingga seluruh Indonesia</p>

                <ul class="list-unstyled custom-social">
                    <li><a href="#"><span class="fa fa-brands fa-facebook-f"></span></a></li>
                    <li><a href="#"><span class="fa fa-brands fa-twitter"></span></a></li>
                    <li><a href="#"><span class="fa fa-brands fa-instagram"></span></a></li>
                    <li><a href="#"><span class="fa fa-brands fa-linkedin"></span></a></li>
                </ul>
            </div>

            <div class="col-lg-8">
                <div class="row links-wrap">
                    <div class="col-6 col-sm-6 col-md-3">
                        <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 15px;">Kategori Produk</h4>
                        <ul class="list-unstyled">
                            <?php foreach ($categories as $cat): ?>
                                <li><a href="shop.php?category=<?php echo urlencode($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                                <li><a href="shop.php">Lihat Semua Produk</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="col-6 col-sm-6 col-md-3">
                        <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 15px;">Cari & Lokasi</h4>
                        <ul class="list-unstyled">
                            <li><a href="shop.php">Cari Produk</a></li>
                            <li><a href="#storeMapModal" data-bs-toggle="modal">Peta Lokasi Toko</a></li>
                            <li><a href="about.php">Tentang Kami</a></li>
                        </ul>
                    </div>

                    <div class="col-6 col-sm-6 col-md-3">
                        <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 15px;">Bantuan</h4>
                        <ul class="list-unstyled">
                            <li><a href="pesanan.php">Pesanan Saya</a></li>
                            <li><a href="chat.php">Chat Bantuan</a></li>
                            <li><a href="contact.php">Kebijakan Privasi</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Lokasi Toko -->
        <?php if (!empty($storeLocations)): ?>
        <div class="row mt-5 pt-5" style="border-top: 1px solid #e0e7ff;">
            <div class="col-12">
                <h4 style="font-size: 16px; font-weight: 700; margin-bottom: 20px;">Lokasi Toko Kami</h4>
            </div>
            <div class="col-12">
                <div style="width: 100%; height: 300px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" id="footerStoreMap"></div>
            </div>
            <div class="col-12 mt-3">
                <div class="row">
                    <?php foreach ($storeLocations as $idx => $store): ?>
                        <?php if ($idx < 4): ?>
                            <div class="col-6 col-md-3 mb-3">
                                <div style="padding: 12px; background: #f7fafc; border-radius: 6px;">
                                    <h6 style="font-size: 13px; font-weight: 700; margin-bottom: 5px;"><?php echo htmlspecialchars($store['name']); ?></h6>
                                    <p style="font-size: 12px; color: #666; margin: 0;">
                                        <?php echo htmlspecialchars(substr($store['address'] ?? 'Alamat', 0, 40)); ?>...
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</footer>
<!-- End Footer Section -->

<!-- Store Locations Modal -->
<?php if (!empty($storeLocations)): ?>
<div class="modal fade" id="storeMapModal" tabindex="-1" aria-labelledby="storeMapLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="storeMapLabel">Peta Lokasi Toko Kami</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div style="width: 100%; height: 500px;" id="fullStoreMap"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Leaflet Maps Library (OpenStreetMap - No API Key Required) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
    // Store locations dari PHP dengan debugging
    const storeLocationsRaw = <?php echo $storeLocationsJson; ?>;
    const storeLocations = Array.isArray(storeLocationsRaw) ? storeLocationsRaw : [];
    
    console.log('🗺️ Store Locations Data:', {
        rawLength: Array.isArray(storeLocationsRaw) ? storeLocationsRaw.length : 0,
        processedLength: storeLocations.length,
        data: storeLocations
    });

    let footerMap = null;
    let fullStoreMap = null;
    let fullStoreMapInitialized = false;

    function initFooterMap() {
        const mapElement = document.getElementById('footerStoreMap');
        console.log('📍 initFooterMap - element:', mapElement ? 'FOUND' : 'NOT FOUND', 'locations:', storeLocations.length);
        
        if (!mapElement) {
            console.warn('⚠️ footerStoreMap element not found');
            return;
        }
        
        if (!storeLocations || storeLocations.length === 0) {
            console.warn('⚠️ No store locations to display');
            mapElement.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">Lokasi toko belum ditambahkan</div>';
            return;
        }

        try {
            // Calculate center point
            let centerLat = 0, centerLng = 0;
            storeLocations.forEach(loc => {
                const lat = parseFloat(loc.latitude);
                const lng = parseFloat(loc.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    centerLat += lat;
                    centerLng += lng;
                }
            });
            centerLat /= storeLocations.length;
            centerLng /= storeLocations.length;

            console.log('📌 Map center:', { lat: centerLat, lng: centerLng });

            // Initialize map
            footerMap = L.map(mapElement, { 
                attributionControl: true,
                zoomControl: true 
            }).setView([centerLat, centerLng], 9);

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
                minZoom: 3
            }).addTo(footerMap);

            // Add markers
            let markerCount = 0;
            storeLocations.forEach((loc, idx) => {
                const lat = parseFloat(loc.latitude);
                const lng = parseFloat(loc.longitude);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = L.circleMarker([lat, lng], {
                        radius: 8,
                        fillColor: '#3b5d50',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(footerMap);

                    marker.bindPopup(`
                        <div style="font-size: 13px; min-width: 200px;">
                            <strong style="color: #3b5d50;">${loc.name}</strong>
                            <br/><small>${loc.address || 'Alamat tidak tersedia'}</small>
                        </div>
                    `);

                    markerCount++;
                }
            });

            console.log('✅ Footer map initialized with', markerCount, 'markers');
        } catch (error) {
            console.error('❌ Error initializing footer map:', error);
        }
    }

    function initFullMap() {
        const mapElement = document.getElementById('fullStoreMap');
        console.log('🗺️ initFullMap - element:', mapElement ? 'FOUND' : 'NOT FOUND');
        
        if (!mapElement) {
            console.warn('⚠️ fullStoreMap element not found');
            return;
        }
        
        if (!storeLocations || storeLocations.length === 0) {
            console.warn('⚠️ No store locations to display in full map');
            return;
        }

        try {
            // Calculate center point
            let centerLat = 0, centerLng = 0;
            storeLocations.forEach(loc => {
                const lat = parseFloat(loc.latitude);
                const lng = parseFloat(loc.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    centerLat += lat;
                    centerLng += lng;
                }
            });
            centerLat /= storeLocations.length;
            centerLng /= storeLocations.length;

            // Initialize map
            fullStoreMap = L.map(mapElement, { 
                attributionControl: true,
                zoomControl: true 
            }).setView([centerLat, centerLng], 10);

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
                minZoom: 3
            }).addTo(fullStoreMap);

            // Add markers
            let markerCount = 0;
            storeLocations.forEach((loc, idx) => {
                const lat = parseFloat(loc.latitude);
                const lng = parseFloat(loc.longitude);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = L.circleMarker([lat, lng], {
                        radius: 10,
                        fillColor: '#3b5d50',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(fullStoreMap);

                    marker.bindPopup(`
                        <div style="font-size: 14px; min-width: 250px;">
                            <strong style="color: #3b5d50; display: block; margin-bottom: 8px;">${loc.name}</strong>
                            <small style="color: #666;">${loc.address || 'Alamat tidak tersedia'}</small>
                        </div>
                    `);

                    markerCount++;
                }
            });

            fullStoreMapInitialized = true;
            console.log('✅ Full map initialized with', markerCount, 'markers');
        } catch (error) {
            console.error('❌ Error initializing full map:', error);
        }
    }

    // Initialize footer map on page load
    document.addEventListener('DOMContentLoaded', () => {
        console.log('✓ DOMContentLoaded - initializing footer map');
        setTimeout(initFooterMap, 100);
    });

    // Handle modal opening for full map
    const storeMapModal = document.getElementById('storeMapModal');
    if (storeMapModal) {
        storeMapModal.addEventListener('shown.bs.modal', () => {
            console.log('📱 Modal shown - fullStoreMapInitialized:', fullStoreMapInitialized);
            if (!fullStoreMapInitialized) {
                setTimeout(() => {
                    initFullMap();
                    if (fullStoreMap) {
                        fullStoreMap.invalidateSize();
                    }
                }, 100);
            } else if (fullStoreMap) {
                setTimeout(() => {
                    fullStoreMap.invalidateSize();
                }, 50);
            }
        });
    }
</script>
