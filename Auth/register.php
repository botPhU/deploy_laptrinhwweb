<?php
require_once dirname(__DIR__) . '/db_connect.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->fullname) || !isset($data->email) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(["message" => "Vui lòng điền đầy đủ thông tin!"]);
    exit();
}

$fullname = $data->fullname;
$email = $data->email;
$password = $data->password;

// Kiểm tra email đã tồn tại chưa
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["message" => "Email này đã được đăng ký!"]);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (fullname, email, password_hash) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $fullname, $email, $hashed_password);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(["message" => "Đăng ký tài khoản thành công!"]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Lỗi máy chủ nội bộ: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>