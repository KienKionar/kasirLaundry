<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

header('Content-Type: application/json');

$q    = clean($_GET['q'] ?? '');
$mode = clean($_GET['mode'] ?? 'search'); // search | recent

$conn = getConnection();

if ($mode === 'recent') {
    // Ambil 6 pelanggan yang terakhir bertransaksi
    $stmt = $conn->prepare("
        SELECT p.id, p.nama, p.no_hp, p.alamat,
               MAX(t.tanggal_masuk) AS last_visit,
               COUNT(t.id) AS total_transaksi
        FROM pelanggan p
        JOIN transaksi t ON t.pelanggan_id = p.id
        GROUP BY p.id
        ORDER BY last_visit DESC
        LIMIT 6
    ");
    $stmt->execute();
} else {
    if (strlen($q) < 1) { echo json_encode([]); exit(); }
    $like = "%$q%";
    $stmt = $conn->prepare("
        SELECT p.id, p.nama, p.no_hp, p.alamat,
               COUNT(t.id) AS total_transaksi
        FROM pelanggan p
        LEFT JOIN transaksi t ON t.pelanggan_id = p.id
        WHERE p.nama LIKE ? OR p.no_hp LIKE ?
        GROUP BY p.id
        ORDER BY p.nama
        LIMIT 8
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
}

$result = $stmt->get_result();
$data   = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'               => $row['id'],
        'nama'             => $row['nama'],
        'no_hp'            => $row['no_hp'],
        'alamat'           => $row['alamat'] ?? '',
        'total_transaksi'  => $row['total_transaksi'] ?? 0,
    ];
}
$stmt->close();
$conn->close();

echo json_encode($data);
