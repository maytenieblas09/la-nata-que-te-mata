<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

require "conexion.php";

$datos = json_decode(file_get_contents("php://input"), true);

$correo = filter_var(
    $datos["correo"] ?? "",
    FILTER_VALIDATE_EMAIL
);
$password = $datos["password"] ?? "";

if (!$correo || $password === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "mensaje" => "Revisa tus datos."]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, password_hash FROM usuarios WHERE correo = ?"
);
$stmt->execute([$correo]);
$usuario = $stmt->fetch();

if (!$usuario) {
    // Primer acceso: crear la cuenta.
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (correo, password_hash) VALUES (?, ?)"
    );
    $stmt->execute([$correo, $hash]);

    $id = $pdo->lastInsertId();
} else {
    // Accesos posteriores: verificar la contraseña.
    if (!password_verify($password, $usuario["password_hash"])) {
        http_response_code(401);
        echo json_encode([
            "ok" => false,
            "mensaje" => "Correo o contraseña incorrectos."
        ]);
        exit;
    }

    $id = $usuario["id"];
}

session_regenerate_id(true);
$_SESSION["usuario_id"] = $id;

echo json_encode(["ok" => true]);