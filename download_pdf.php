<?php
// download_pdf.php
// Simple PDF generator for riwayat pembayaran
$autoload_path = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload_path)) {
    die('Autoload Composer tidak ditemukan di: ' . $autoload_path);
}
require_once $autoload_path;
if (!class_exists('Mpdf\\Mpdf')) {
    // Fallback: coba require langsung file mPDF versi lama
    $mpdf_manual = __DIR__ . '/vendor/mpdf/mpdf/mpdf.php';
    if (file_exists($mpdf_manual)) {
        require_once $mpdf_manual;
    }
}
if (!class_exists('Mpdf\\Mpdf')) {
    die('Class Mpdf\\Mpdf tidak ditemukan setelah autoload dan manual require. Cek instalasi mPDF.');
}
include 'koneksi.php';

if (!isset($_GET['tanggal']) || !isset($_GET['jumlah'])) {
    die('Parameter tidak lengkap.');
}
$tanggal = $_GET['tanggal'];
$jumlah = $_GET['jumlah'];

// Query detail pembayaran
$q = $conn->prepare("SELECT p.*, t.semester, t.tahun_ajaran, m.nama, m.nim FROM Pembayaran p JOIN Tagihan t ON p.id_tagihan = t.id_tagihan JOIN Mahasiswa m ON t.id_mahasiswa = m.id_mahasiswa WHERE p.tanggal_bayar=? AND p.jumlah_dibayar=? LIMIT 1");
$q->bind_param("si", $tanggal, $jumlah);
$q->execute();
$result = $q->get_result();
if (!$row = $result->fetch_assoc()) {
    die('Data tidak ditemukan.');
}
$q->close();

$html = '<h2 style="text-align:center;">Bukti Pembayaran UKT</h2>';
$html .= '<table style="width:100%; font-size:1.1em;">';
$html .= '<tr><td><b>Nama</b></td><td>' . htmlspecialchars($row['nama']) . '</td></tr>';
$html .= '<tr><td><b>NIM</b></td><td>' . htmlspecialchars($row['nim']) . '</td></tr>';
$html .= '<tr><td><b>Semester</b></td><td>' . htmlspecialchars($row['semester']) . '</td></tr>';
$html .= '<tr><td><b>Tahun Ajaran</b></td><td>' . htmlspecialchars($row['tahun_ajaran']) . '</td></tr>';
$html .= '<tr><td><b>Tanggal Bayar</b></td><td>' . htmlspecialchars(date('d/m/Y', strtotime($row['tanggal_bayar']))) . '</td></tr>';
$html .= '<tr><td><b>Jumlah Dibayar</b></td><td>Rp ' . number_format($row['jumlah_dibayar'],0,',','.') . '</td></tr>';
$html .= '<tr><td><b>Status</b></td><td>' . htmlspecialchars($row['status_verifikasi']) . '</td></tr>';
$html .= '</table>';
$html .= '<br><br><i>Dicetak otomatis oleh sistem MyUKT</i>';

// Inisialisasi mPDF sesuai versi
if (class_exists('Mpdf\\Mpdf')) {
    $mpdf = new \Mpdf\Mpdf();
} elseif (class_exists('mPDF')) {
    $mpdf = new mPDF();
} else {
    die('Class mPDF tidak ditemukan.');
}
$mpdf->WriteHTML($html);
$mpdf->Output('Bukti_Pembayaran_UKT.pdf', 'I');
