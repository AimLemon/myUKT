<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; // Gunakan autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($nim, $email, $conn) {
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));

    $stmt = $conn->prepare("INSERT INTO email_verifications (nim, email, token, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nim, $email, $token, $expires_at);
    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Gagal menyimpan token verifikasi.'];
    }
    $stmt->close();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Verifikasi Email - ' . APP_NAME;
        $verification_link = APP_URL . '/verify_email.php?nim=' . urlencode($nim) . '&token=' . urlencode($token);
        $mail->Body = <<<EOD
        <!DOCTYPE html>
        <html>
        <body>
            <h2>Verifikasi Email Anda</h2>
            <p>Terima kasih telah memperbarui email Anda di {MyUKT}.</p>
            <p>Silakan klik link berikut untuk memverifikasi email Anda:</p>
            <a href="{$verification_link}" style="background:#2bb6a8;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;">Verifikasi Email</a>
            <p>Link ini akan kedaluwarsa dalam 24 jam.</p>
            <p>Jika Anda tidak meminta perubahan ini, abaikan email ini.</p>
        </body>
        </html>
        EOD;

        $mail->send();
        return ['success' => true, 'message' => 'Link verifikasi telah dikirim ke email Anda.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Gagal mengirim email verifikasi: ' . $mail->ErrorInfo];
    }
}

function verifyEmailToken($nim, $token, $conn) {
    $stmt = $conn->prepare("SELECT email, expires_at FROM email_verifications WHERE nim=? AND token=?");
    $stmt->bind_param("ss", $nim, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (strtotime($row['expires_at']) < time()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Token verifikasi telah kedaluwarsa.'];
        }
        // Verifikasi berhasil, tandai email sebagai terverifikasi
        $stmt2 = $conn->prepare("UPDATE Mahasiswa SET email_verified_at=NOW() WHERE nim=?");
        $stmt2->bind_param("s", $nim);
        $success = $stmt2->execute();
        $stmt2->close();

        // Hapus token setelah digunakan
        $stmt3 = $conn->prepare("DELETE FROM email_verifications WHERE nim=? AND token=?");
        $stmt3->bind_param("ss", $nim, $token);
        $stmt3->execute();
        $stmt3->close();

        $stmt->close();
        return ['success' => true, 'message' => 'Email berhasil diverifikasi.'];
    }
    $stmt->close();
    return ['success' => false, 'message' => 'Token verifikasi tidak valid.'];
}

function sendPaymentNotification($email, $jumlah, $metode, $tanggal, $catatan = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Pengajuan Pembayaran - ' . APP_NAME;
        $mail->Body = <<<EOD
        <!DOCTYPE html>
        <html>
        <body>
            <h2>Pengajuan Pembayaran Berhasil</h2>
            <p>Pengajuan pembayaran Anda telah diterima oleh MyUKT.</p>
            <p><strong>Detail Pembayaran:</strong></p>
            <ul>
                <li>Jumlah: Rp {number_format($jumlah, 0, ',', '.')}</li>
                <li>Metode Pembayaran: {htmlspecialchars($metode)}</li>
                <li>Tanggal Pengajuan: {date('d/m/y', strtotime($tanggal))}</li>
                <li>Catatan: {htmlspecialchars($catatan ?: '-')}</li>
            </ul>
            <p>Pembayaran Anda sedang menunggu verifikasi oleh admin. Anda akan menerima notifikasi lebih lanjut.</p>
            <p>Terima kasih telah menggunakan MyUKT.</p>
        </body>
        </html>
        EOD;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function sendGeneralNotification($email, $subject, $message, $id_mahasiswa, $id_tagihan, $notification_type, $conn) {
    // Cek apakah notifikasi sudah dikirim sebelumnya dalam 24 jam terakhir
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifikasi WHERE id_mahasiswa=? AND id_tagihan=? AND tipe_notifikasi=? AND isi=? AND waktu_kirim > NOW() - INTERVAL 1 DAY");
    $stmt->bind_param("iiss", $id_mahasiswa, $id_tagihan, $notification_type, $message);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        return ['success' => false, 'message' => 'Notifikasi sudah dikirim dalam 24 jam terakhir.'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = $subject . ' - ' . APP_NAME;
        $mail->Body = <<<EOD
        <!DOCTYPE html>
        <html>
        <body>
            <h2>Notifikasi dari MyUKT}</h2>
            <p>$message</p>
            <p>Silakan login ke <a href="{APP_URL}/dashboardmahasiswa.php">MyUKT</a> untuk detail lebih lanjut.</p>
            <p>Terima kasih telah menggunakan MyUKT.</p>
        </body>
        </html>
        EOD;

        $mail->send();

        // Simpan notifikasi ke tabel notifikasi
        $stmt = $conn->prepare("INSERT INTO notifikasi (id_mahasiswa, id_tagihan, judul, isi, tipe_notifikasi, waktu_kirim, status_baca) VALUES (?, ?, ?, ?, ?, NOW(), 'belum')");
        $stmt->bind_param("iisss", $id_mahasiswa, $id_tagihan, $subject, $message, $notification_type);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Notifikasi berhasil dikirim ke email.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Gagal mengirim notifikasi: ' . $mail->ErrorInfo];
    }
}
?>
