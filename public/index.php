<?php
declare(strict_types=1);

// No tocar el php.ini compartido del WAMP (lo usan otros proyectos como
// sgo/dptuc/imperio) - la zona horaria se fija acá, a nivel de aplicación.
date_default_timezone_set('America/Argentina/Buenos_Aires');

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Csrf.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Tickets.php';

// Whitelist de páginas válidas para ?p=. El router nunca hace include()
// directo del parámetro recibido - todo pasa por esta lista + el switch.
const PAGINAS_PUBLICAS = ['login'];
const PAGINAS_VALIDAS  = ['login', 'logout'];

$p = $_GET['p'] ?? 'login';
if (!in_array($p, PAGINAS_VALIDAS, true)) {
    http_response_code(404);
    echo 'Página no encontrada';
    exit;
}

if (!in_array($p, PAGINAS_PUBLICAS, true)) {
    Auth::requiereLogin();
}

$errorLogin = null;

switch ($p) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::check()) {
            Csrf::requerirValido();
            $resultado = Auth::login((string) ($_POST['usuario'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($resultado['ok']) {
                redirect('index.php?p=login');
            }
            $errorLogin = $resultado['error'];
        }
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/login.php';
        require __DIR__ . '/../views/partials/footer.php';
        break;

    case 'logout':
        Auth::logout();
        redirect('index.php?p=login');
        break;
}
