
<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

if ($action === 'status') {
    responder([
        'ok' => true,
        'autenticado' => !empty($_SESSION['usuario_id']),
        'correo' => $_SESSION['correo'] ?? null
    ]);
}

if ($action === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    session_destroy();
    header('Location: index.html');
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !in_array($action, ['registro', 'login'], true)
) {
    responder(['ok' => false, 'mensaje' => 'Solicitud no válida.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$correo = strtolower(trim((string)($data['correo'] ?? '')));
$contrasena = (string)($data['contrasena'] ?? '');

if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $contrasena === '') {
    responder([
        'ok' => false,
        'mensaje' => 'Escribe un correo válido y una contraseña.'
    ], 400);
}

try {
    $pdo = db();

    if ($action === 'registro') {
        $q = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ?');
        $q->execute([$correo]);

        if ($q->fetch()) {
            responder([
                'ok' => false,
                'mensaje' => 'Ese correo ya está registrado. Inicia sesión.'
            ], 409);
        }

        $hash = password_hash($contrasena, PASSWORD_DEFAULT);

        $q = $pdo->prepare(
            'INSERT INTO usuarios (correo, password_hash) VALUES (?, ?)'
        );
        $q->execute([$correo, $hash]);

        $usuarioId = (int)$pdo->lastInsertId();

    } else {
        $q = $pdo->prepare(
            'SELECT id, password_hash FROM usuarios WHERE correo = ?'
        );
        $q->execute([$correo]);
        $usuario = $q->fetch();

        if (!$usuario) {
            responder([
                'ok' => false,
                'mensaje' => 'Ese correo no está registrado. Crea una cuenta primero.'
            ], 404);
        }

        if (!password_verify($contrasena, $usuario['password_hash'])) {
            responder([
                'ok' => false,
                'mensaje' => 'La contraseña no coincide con esa cuenta.'
            ], 401);
        }

        $usuarioId = (int)$usuario['id'];
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['correo'] = $correo;

    $q = $pdo->prepare(
        'INSERT INTO sesiones_registradas
        (usuario_id, correo, ip, navegador)
        VALUES (?, ?, ?, ?)'
    );

    $q->execute([
        $usuarioId,
        $correo,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
    ]);

    responder([
        'ok' => true,
        'autenticado' => true,
        'correo' => $correo
    ]);

} catch (Throwable $e) {
    error_log('Error de acceso La Nata: ' . $e->getMessage());

    responder([
        'ok' => false,
        'mensaje' => 'No se pudo conectar con MySQL. Revisa config.php y las tablas.'
    ], 500);
}
