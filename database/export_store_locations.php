<?php
require_once __DIR__ . '/config.php';
// simple export of all store locations
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="store_locations.csv"');
$out = fopen('php://output', 'w');
// header
fputcsv($out, ['name','address','latitude','longitude']);
$res = $conn->query('SELECT name,address,latitude,longitude FROM store_locations ORDER BY id DESC');
if ($res) {
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [$r['name'], $r['address'], $r['latitude'], $r['longitude']]);
    }
}
fclose($out);
exit;
