<?php
// forgot_password.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../db_connect.php';
if (file_exists('../vendor/autoload.php')) require_once '../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Hàm gửi OTP giả lập (hoặc kết nối SMTP)
 */
function sendEmail($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? 'your_email@gmail.com';
        $mail->Password   = $_ENV['SMTP_PASS'] ?? 'your_app_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($_ENV['SMTP_USER'] ?? 'no-reply@coursera.vn', 'Coursera Advanced');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Mã xác nhận khôi phục mật khẩu';
        $mail->Body    = "<h3>Khôi phục mật khẩu</h3><p>Mã OTP của bạn là: <b style='color:#0056D2;font-size:20px;'>{$otp}</b></p><p>Mã này có hiệu lực trong 15 phút.</p><br><p>Trân trọng,<br>Coursera Advanced Team</p>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? ($_POST['email'] ?? ''));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email không hợp lệ!"]);
    exit();
}

/** @var mysqli $conn */
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Email không tồn tại trong hệ thống!"]);
    exit();
}
$stmt->close();

// Tạo mã OTP
$otp = strval(random_int(100000, 999999));
$expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Lưu vào bảng otp_codes (Đảm bảo bảng này đã tồn tại trong DB)
$stmt = $conn->prepare("INSERT INTO otp_codes (email, otp, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $otp, $expires_at);

if ($stmt->execute()) {
    // Gửi email thực tế hoặc log lại để test
    if (sendEmail($email, $otp)) {
        echo json_encode(["status" => "success", "message" => "Mã OTP đã được gửi tới email của bạn."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Không thể gửi email."]);
    }
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống khi tạo OTP."]);
}

$stmt->close();
$conn->close();
?>