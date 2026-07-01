<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/db_connect.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    jsonResponse(["status" => "error", "message" => "Không tìm thấy token."], 401);
}

try {
    $jwt = $matches[1];
    $secret_key = $_ENV['JWT_SECRET_KEY'] ?? '';
    if (empty($secret_key)) throw new Exception("JWT Secret is not configured.");

    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user_id = (int) $decoded->user_id;

    $rawInput = file_get_contents('php://input');
    $input = [];
    if (!empty($rawInput)) {
        $decodedInput = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $input = $decodedInput;
        }
    }

    $old_password = trim($input['oldPassword'] ?? '');
    $new_password = trim($input['newPassword'] ?? '');

    if ($old_password === '' || $new_password === '') {
        jsonResponse(["status" => "error", "message" => "Vui lòng nhập đầy đủ thông tin!"], 400);
    }

    if (strlen($new_password) < 6) {
        jsonResponse(["status" => "error", "message" => "Mật khẩu mới phải từ 6 ký tự."], 400);
    }

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    if (!$stmt) jsonResponse(["status" => "error", "message" => "Lỗi CSDL."], 500);

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        jsonResponse(["status" => "error", "message" => "Tài khoản không tồn tại!"], 404);
    }

    if (
        !password_verify($old_password, $user['password_hash'])
    ) {
        jsonResponse(["status" => "error", "message" => "Mật khẩu cũ không chính xác!"], 400);
    }

    $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    if (!$update_stmt) jsonResponse(["status" => "error", "message" => "Lỗi CSDL."], 500);

    $update_stmt->bind_param("si", $new_hashed, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    $conn->close();

    jsonResponse(["status" => "success", "message" => "Thay đổi mật khẩu thành công!"], 200);

} catch (Exception $e) {
    jsonResponse(["status" => "error", "message" => "Token không hợp lệ hoặc đã hết hạn."], 401);
}