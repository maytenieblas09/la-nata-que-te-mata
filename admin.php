
<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['admin_autorizado'])) {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $clave = (string)($_POST['clave_admin'] ?? '');

        if (hash_equals(ADMIN_PASSWORD, $clave)) {
            session_regenerate_id(true);
            $_SESSION['admin_autorizado'] = true;
            header('Location: admin.php');
            exit;
        }

        $error = 'Contraseña incorrecta.';
    }
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Administración | La Nata</title>
    </head>
    <body style="font-family:Arial;background:#f8f3ea;color:#321a11;max-width:500px;margin:60px auto;padding:24px">
        <h1>Panel de administración</h1>
        <p>Ingresa la contraseña configurada en config.php.</p>
        <form method="post">
            <label>Contraseña
                <input type="password" name="clave_admin" required>
            </label>
            <button type="submit">Entrar</button>
        </form>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_GET['salir'])) {
    unset($_SESSION['admin_autorizado']);
    header('Location: admin.php');
    exit;
}

try {
    $pdo = db();

    $usuarios = $pdo->query(
        'SELECT id, correo, fecha_registro
         FROM usuarios ORDER BY fecha_registro DESC'
    )->fetchAll();

    $sesiones = $pdo->query(
        'SELECT id, correo, fecha_inicio, ip, navegador
         FROM sesiones_registradas
         ORDER BY fecha_inicio DESC LIMIT 1000'
    )->fetchAll();

} catch (Throwable $e) {
    http_response_code(500);
    exit('No se pudo leer MySQL. Revisa config.php y schema.sql.');
}

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administración | La Nata Que Te Mata</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f3ea;
            color: #321a11;
            padding: 24px;
        }
        main { max-width: 1200px; margin: auto; }
        table {
            border-collapse: collapse;
            width: 100%;
            background: white;
            margin: 16px 0 35px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            overflow-wrap: anywhere;
        }
        th { background: #542d1c; color: white; }
    </style>
</head>
<body>
<main>
    <p>
        <a href="index.html">Volver a la página</a> |
        <a href="admin.php?salir=1">Cerrar panel</a>
    </p>

    <h1>Panel de administración</h1>

    <p>
        Usuarios registrados: <strong><?= count($usuarios) ?></strong><br>
        Inicios de sesión registrados: <strong><?= count($sesiones) ?></strong>
    </p>

    <h2>Usuarios registrados</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Correo</th>
            <th>Fecha de registro</th>
        </tr>
        <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><?= h($u['id']) ?></td>
            <td><?= h($u['correo']) ?></td>
            <td><?= h($u['fecha_registro']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Inicios de sesión</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Correo</th>
            <th>Fecha y hora</th>
            <th>IP</th>
            <th>Navegador/dispositivo</th>
        </tr>
        <?php foreach ($sesiones as $s): ?>
        <tr>
            <td><?= h($s['id']) ?></td>
            <td><?= h($s['correo']) ?></td>
            <td><?= h($s['fecha_inicio']) ?></td>
            <td><?= h($s['ip']) ?></td>
            <td><?= h($s['navegador']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</main>
</body>
</html>
