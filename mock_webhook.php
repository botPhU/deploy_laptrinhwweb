<?php
require_once __DIR__ . '/db_connect.php';
header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    "success" => true, 
    "message" => "Đã gửi yêu cầu xác nhận! Vui lòng chờ Admin duyệt để cấp quyền vào học."
]);