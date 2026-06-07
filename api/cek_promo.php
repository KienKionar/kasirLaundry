<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireKasir();

header('Content-Type: application/json');

$kodePromo  = clean($_GET['kode'] ?? '');
$totalHarga = (float)($_GET['total'] ?? 0);

$conn   = getConnection();
$result = hitungDiskon($conn, $kodePromo, $totalHarga);
$conn->close();

echo json_encode($result);
