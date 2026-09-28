<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$order_id = $_GET['order_id'] ?? '';
if (!$order_id) {
    header('Location: pesanan.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order = null;

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
if ($stmt) {
    $stmt->bind_param('si', $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
}

if (!$order) {
    http_response_code(404);
    header('Location: pesanan.php');
    exit();
}

$tracking_data = !empty($order['tracking_data']) ? json_decode($order['tracking_data'], true) : null;
$address = !empty($order['address']) ? json_decode($order['address'], true) : [];

// Fetch delivery personnel data if order has been assigned
$deliveryPersonnel = null;
if (!empty($order['delivery_personnel_id'])) {
    $stmt = $conn->prepare("SELECT * FROM delivery_personnel WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $order['delivery_personnel_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $deliveryPersonnel = $result->fetch_assoc();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="current-user-id" content="<?php echo htmlspecialchars($user_id); ?>">
    <title>Lacak Pesanan - <?php echo htmlspecialchars($order_id); ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="../css/style.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .map-marker.store { background: #2563eb; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="custom-navbar navbar navbar-expand-md navbar-dark bg-dark" aria-label="navigation bar">
        <div class="container">
            <a class="navbar-brand" href="user_page.php">Offo Living</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbars">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbars">
                <ul class="custom-navbar-nav navbar-nav me-auto mb-2 mb-md-0">
                    <li class="nav-item"><a class="nav-link" href="user_page.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="pesanan.php">Pesanan</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="tracking-container">
        <a href="pesanan.php" class="back-btn"><i class="bx bx-arrow-back"></i> Kembali ke Pesanan</a>

        <div class="tracking-header">
            <h1 class="tracking-title">Lacak Pesanan</h1>
            <p class="tracking-subtitle">Nomor Pesanan: <?php echo htmlspecialchars($order_id); ?></p>
        </div>

        <div class="tracking-card">
            <div class="card-header">
                <div class="card-title">Lokasi Pengiriman Real-time</div>
                <div class="card-meta">Posisi pengantar diperbarui setiap 5 detik</div>
            </div>
            <div class="card-body">
                <div class="map-container">
                    <canvas id="mapCanvas" class="map-canvas"></canvas>
                    <div class="map-marker store" id="storeMarker" style="display:none;">
                        <i class="bx bxs-store"></i>
                    </div>
                    <div class="map-marker delivery" id="deliveryMarker" style="display:none;">
                        <i class="bx bxs-truck"></i>
                    </div>
                    <div class="map-marker destination" id="destinationMarker" style="display:none;">
                        <i class="bx bxs-home"></i>
                    </div>
                </div>

                <div class="tracking-info">
                    <div class="info-card">
                        <div class="info-label">Status</div>
                        <div class="info-value" id="statusText">Dalam Perjalanan</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Jarak Tersisa</div>
                        <div class="info-value" id="distanceText">15 km</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Estimasi Tiba</div>
                        <div class="info-value" id="etaText">Dalam 25 menit</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Kecepatan</div>
                        <div class="info-value" id="speedText">32 km/h</div>
                    </div>
                </div>

                <div class="address-section">
                    <div class="address-title">📍 Alamat Pengiriman</div>
                    <div class="address-text">
                        <?php 
                        $addr = $address ? sprintf("%s, %s, %s %s", 
                            $address['jalan'] ?? '-',
                            $address['kota'] ?? '-',
                            $address['provinsi'] ?? '-',
                            $address['kodepos'] ?? '-'
                        ) : 'Alamat tidak tersedia';
                        echo htmlspecialchars($addr);
                        ?>
                    </div>
                </div>

                <?php if ($deliveryPersonnel && in_array($order['status'] ?? '', ['delivered', 'on_delivery'])): ?>
                <div class="address-section" style="border-left: 4px solid #f9bf29; background: #fffbeb;">
                    <div class="address-title" style="color: #b45309;">👤 Data Pengirim</div>
                    <div style="margin-top: 12px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Nama Supir</div>
                                <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($deliveryPersonnel['driver_name']); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Jenis Kendaraan</div>
                                <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($deliveryPersonnel['vehicle_type']); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Nomor Kendaraan</div>
                                <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($deliveryPersonnel['vehicle_number']); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Nomor HP</div>
                                <div style="font-weight: 600; color: #1f2937;">
                                    <a href="tel:<?php echo htmlspecialchars($deliveryPersonnel['phone_number']); ?>" style="color: #0ea5e9; text-decoration: none;">
                                        <?php echo htmlspecialchars($deliveryPersonnel['phone_number']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tracking-card">
            <div class="card-header">
                <div class="card-title">Timeline Pengiriman</div>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker completed">
                            <i class="bx bx-check"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-time">Tadi</div>
                            <div class="timeline-text">Pesanan Dikemas</div>
                            <div class="timeline-desc">Pesanan sedang disiapkan untuk dikirim</div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker completed">
                            <i class="bx bx-check"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-time">Baru saja</div>
                            <div class="timeline-text">Pesanan Diambil Pengantar</div>
                            <div class="timeline-desc">Paket sudah diserahkan ke kurir pengiriman</div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker active">
                            <i class="bx bx-loader-circle"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-time">Sekarang</div>
                            <div class="timeline-text">Pesanan Dalam Perjalanan</div>
                            <div class="timeline-desc">Kurir sedang dalam perjalanan menuju alamat Anda</div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker">
                            <i class="bx bxs-home"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-time">Nanti</div>
                            <div class="timeline-text">Pesanan Tiba</div>
                            <div class="timeline-desc">Paket akan tiba di tangan Anda</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/custom.js"></script>
    <script>
        const orderId = '<?php echo htmlspecialchars($order_id, ENT_QUOTES); ?>';
        const trackingApiUrl = '../database/tracking_api.php?order_id=' + encodeURIComponent(orderId);

        const canvas = document.getElementById('mapCanvas');
        const ctx = canvas.getContext('2d');
        const storeMarker = document.getElementById('storeMarker');
        const deliveryMarker = document.getElementById('deliveryMarker');
        const destinationMarker = document.getElementById('destinationMarker');

        const trackingData = {
            start: { lat: -6.2088, lng: 106.8456 },
            end: { lat: -6.1944, lng: 106.8296 },
            current: { lat: -6.2088, lng: 106.8456 },
            progress: 0,
            stage: 'Menunggu Pengiriman',
            distance_km: 0,
            eta_seconds: 0,
            speed: 32,
            bounds: null
        };

        function updateMapBounds() {
            const points = [trackingData.start, trackingData.end, trackingData.current].filter(Boolean);
            if (!points.length) {
                trackingData.bounds = {
                    minLat: -6.21,
                    maxLat: -6.19,
                    minLng: 106.82,
                    maxLng: 106.86
                };
                return;
            }

            let minLat = points[0].lat;
            let maxLat = points[0].lat;
            let minLng = points[0].lng;
            let maxLng = points[0].lng;

            points.forEach(point => {
                if (point.lat < minLat) minLat = point.lat;
                if (point.lat > maxLat) maxLat = point.lat;
                if (point.lng < minLng) minLng = point.lng;
                if (point.lng > maxLng) maxLng = point.lng;
            });

            const latPadding = Math.max(0.005, (maxLat - minLat) * 0.25);
            const lngPadding = Math.max(0.005, (maxLng - minLng) * 0.25);

            trackingData.bounds = {
                minLat: minLat - latPadding,
                maxLat: maxLat + latPadding,
                minLng: minLng - lngPadding,
                maxLng: maxLng + lngPadding
            };
        }

        function resizeCanvas() {
            canvas.width = canvas.parentElement.clientWidth;
            canvas.height = canvas.parentElement.clientHeight;
            updateMapBounds();
            drawMap();
            updateMarkers();
        }

        function drawMap() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#dbeafe';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.strokeStyle = '#cbd5e0';
            ctx.lineWidth = 0.5;
            for (let i = 0; i < canvas.width; i += 40) {
                ctx.beginPath();
                ctx.moveTo(i, 0);
                ctx.lineTo(i, canvas.height);
                ctx.stroke();
            }
            for (let i = 0; i < canvas.height; i += 40) {
                ctx.beginPath();
                ctx.moveTo(0, i);
                ctx.lineTo(canvas.width, i);
                ctx.stroke();
            }

            updateMapBounds();
            const startPos = latLngToPixel(trackingData.start.lat, trackingData.start.lng);
            const endPos = latLngToPixel(trackingData.end.lat, trackingData.end.lng);

            ctx.strokeStyle = '#60a5fa';
            ctx.lineWidth = 4;
            ctx.setLineDash([8, 6]);
            ctx.beginPath();
            ctx.moveTo(startPos.x, startPos.y);
            ctx.lineTo(endPos.x, endPos.y);
            ctx.stroke();
            ctx.setLineDash([]);
        }

        function latLngToPixel(lat, lng) {
            const bounds = trackingData.bounds || {
                minLat: lat - 0.01,
                maxLat: lat + 0.01,
                minLng: lng - 0.01,
                maxLng: lng + 0.01
            };
            const width = canvas.width;
            const height = canvas.height;
            const x = ((lng - bounds.minLng) / (bounds.maxLng - bounds.minLng)) * width;
            const y = (1 - (lat - bounds.minLat) / (bounds.maxLat - bounds.minLat)) * height;
            return { x, y };
        }

        function updateMarkers() {
            const storePos = latLngToPixel(trackingData.start.lat, trackingData.start.lng);
            storeMarker.style.left = storePos.x + 'px';
            storeMarker.style.top = storePos.y + 'px';
            storeMarker.style.display = 'flex';

            const destPos = latLngToPixel(trackingData.end.lat, trackingData.end.lng);
            destinationMarker.style.left = destPos.x + 'px';
            destinationMarker.style.top = destPos.y + 'px';
            destinationMarker.style.display = 'flex';

            const currentPos = latLngToPixel(trackingData.current.lat, trackingData.current.lng);
            deliveryMarker.style.left = currentPos.x + 'px';
            deliveryMarker.style.top = currentPos.y + 'px';
            deliveryMarker.style.display = 'flex';
        }

        function updateTrackingInfo() {
            const remainingDistance = Math.max(0, trackingData.distance_km * (1 - trackingData.progress));
            const remainingMinutes = Math.max(0, Math.ceil((trackingData.eta_seconds || 0) / 60));

            document.getElementById('statusText').textContent = trackingData.stage || 'Dalam Perjalanan';
            document.getElementById('distanceText').textContent = remainingDistance.toFixed(1) + ' km';
            document.getElementById('etaText').textContent = trackingData.progress >= 1 ? 'Pesanan tiba!' : 'Dalam ' + remainingMinutes + ' menit';
            document.getElementById('speedText').textContent = trackingData.speed + ' km/h';

            if (trackingData.progress >= 1) {
                document.getElementById('statusText').textContent = 'Tiba Tujuan';
                document.getElementById('distanceText').textContent = '0 km';
                document.getElementById('etaText').textContent = 'Pesanan tiba!';
            }
        }

        function normalizeTrackingResponse(data) {
            if (!data || !data.ok) {
                console.warn('Tracking response invalid or failed', data);
                return;
            }

            const tracking = data.tracking;
            if (tracking) {
                trackingData.start = tracking.start || data.store || trackingData.start;
                trackingData.end = tracking.destination || data.destination || trackingData.end;
                trackingData.current = tracking.current_location || trackingData.start;
                trackingData.progress = typeof tracking.progress === 'number' ? tracking.progress : 0;
                trackingData.stage = tracking.stage || tracking.status || 'Dalam Perjalanan';
                trackingData.distance_km = parseFloat(tracking.distance_km ?? trackingData.distance_km) || trackingData.distance_km;
                trackingData.eta_seconds = parseInt(tracking.eta_seconds ?? 0, 10) || trackingData.eta_seconds;
                if (tracking.duration_seconds && tracking.distance_km) {
                    trackingData.speed = Math.max(1, Math.round(tracking.distance_km / (tracking.duration_seconds / 3600)));
                }
            } else {
                trackingData.start = data.store || trackingData.start;
                trackingData.end = data.destination || trackingData.end;
                trackingData.current = trackingData.start;
                trackingData.progress = 0;
                trackingData.stage = data.status === 'on_delivery' ? 'Menunggu data pengiriman' : 'Pengiriman belum dimulai';
                trackingData.distance_km = trackingData.distance_km || 0;
                trackingData.eta_seconds = 0;
                trackingData.speed = 0;
            }

            drawMap();
            updateMarkers();
            updateTrackingInfo();
        }

        function fetchTracking() {
            fetch(trackingApiUrl)
                .then(response => response.json())
                .then(data => {
                    normalizeTrackingResponse(data);
                    if (data.ok) {
                        setTimeout(fetchTracking, 5000);
                    }
                })
                .catch(error => {
                    console.error('Tracking API fetch error:', error);
                    setTimeout(fetchTracking, 10000);
                });
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
        fetchTracking();
    </script>
</body>
</html>
