<?php
declare(strict_types=1);

// No tocar el php.ini compartido del WAMP (lo usan otros proyectos como
// sgo/dptuc/imperio) - la zona horaria se fija acá, a nivel de aplicación.
date_default_timezone_set('America/Argentina/Buenos_Aires');

// cookie_secure se autodetecta (true solo si la request ya llega por HTTPS):
// así el mismo index.php sirve para el WAMP local en HTTP y para producción
// en HTTPS, sin necesitar una versión aparte de este archivo.
ini_set('session.use_strict_mode', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Icons.php';
require_once __DIR__ . '/../src/Csrf.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/SasApiClient.php';
require_once __DIR__ . '/../src/Prioridad.php';
require_once __DIR__ . '/../src/Tickets.php';
require_once __DIR__ . '/../src/Agendas.php';
require_once __DIR__ . '/../src/Refinanciaciones.php';
require_once __DIR__ . '/../src/Gestiones.php';
require_once __DIR__ . '/../src/WhatsApp.php';

// Whitelist de páginas válidas para ?p=. El router nunca hace include()
// directo del parámetro recibido - todo pasa por esta lista + el switch.
const PAGINAS_PUBLICAS = ['login'];
const PAGINAS_VALIDAS  = [
    'login', 'logout', 'lista_tickets', 'ficha_ticket', 'registrar_gestion',
    'cerrar_ticket', 'administrar_usuarios', 'whatsapp_preview', 'whatsapp_enviar',
    'dashboard', 'agendas_accion', 'refinanciaciones_accion',
];

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
        if (Auth::check()) {
            redirect('index.php?p=lista_tickets');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::requerirValido();
            $resultado = Auth::login((string) ($_POST['usuario'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($resultado['ok']) {
                redirect('index.php?p=lista_tickets');
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

    case 'lista_tickets':
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/lista_tickets.php';
        require __DIR__ . '/../views/partials/footer.php';
        break;

    case 'ficha_ticket':
        $ticket = Tickets::porId((int) ($_GET['id'] ?? 0));
        if ($ticket === null) {
            http_response_code(404);
            echo 'Ticket no encontrado';
            exit;
        }
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/ficha_ticket.php';
        require __DIR__ . '/../views/partials/footer.php';
        break;

    case 'registrar_gestion':
        $ticketId = (int) ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
        $ticket   = Tickets::porId($ticketId);
        if ($ticket === null || $ticket['estado'] !== 'abierto') {
            http_response_code(404);
            echo 'Ticket no encontrado o ya cerrado';
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::requerirValido();

            $resultadoPost = (string) ($_POST['resultado'] ?? '');
            $canalPost     = (string) ($_POST['canal'] ?? 'llamada');
            $conAgenda     = !empty($_POST['agenda_cobro']);
            $tipoAgenda    = (string) ($_POST['tipo_agenda'] ?? '');

            $resultadosValidos  = ['atendio', 'no_atendio', 'ocupado', 'buzon', 'fuera_servicio', 'numero_erroneo'];
            $canalesValidos     = ['llamada', 'visita', 'sms'];
            $tiposAgendaValidos = ['cliente_viene', 'se_visita', 'transferencia'];

            if (!in_array($resultadoPost, $resultadosValidos, true)) {
                redirect('index.php?p=registrar_gestion&ticket_id=' . $ticketId . '&error=' . urlencode('Falta indicar el resultado de la gestión'));
            }

            if ($conAgenda && (empty($_POST['fecha_agendada']) || empty($_POST['monto_esperado']) || !in_array($tipoAgenda, $tiposAgendaValidos, true))) {
                redirect('index.php?p=registrar_gestion&ticket_id=' . $ticketId . '&error=' . urlencode('Faltan datos de la agenda de cobro'));
            }

            $usuario = current_user();
            Gestiones::registrar([
                'ticket_id'            => $ticketId,
                'usuario_id'           => $usuario['id'],
                'canal'                => in_array($canalPost, $canalesValidos, true) ? $canalPost : 'llamada',
                'resultado'            => $resultadoPost,
                'observacion'          => trim((string) ($_POST['observacion'] ?? '')) ?: null,
                'agenda_cobro'         => $conAgenda,
                'fecha_agendada'       => $conAgenda ? $_POST['fecha_agendada'] : null,
                'monto_esperado'       => $conAgenda ? (float) $_POST['monto_esperado'] : null,
                'tipo_agenda'          => $conAgenda ? $tipoAgenda : null,
                'solicita_refinanciar' => !empty($_POST['solicita_refinanciar']),
            ]);

            redirect('index.php?p=ficha_ticket&id=' . $ticketId . '&msg=gestion_ok');
        }

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/registrar_gestion.php';
        require __DIR__ . '/../views/partials/footer.php';
        break;

    case 'cerrar_ticket':
        Csrf::requerirValido();
        $ticketId    = (int) ($_POST['ticket_id'] ?? 0);
        $motivo      = (string) ($_POST['motivo'] ?? '');
        $observacion = trim((string) ($_POST['observacion_cierre'] ?? '')) ?: null;
        $monto       = isset($_POST['monto_cierre']) && $_POST['monto_cierre'] !== '' ? (float) $_POST['monto_cierre'] : null;

        if (!in_array($motivo, ['abono', 'retiro_producto', 'refinanciacion'], true)) {
            redirect('index.php?p=ficha_ticket&id=' . $ticketId . '&error=' . urlencode('Motivo de cierre inválido'));
        }

        $usuario   = current_user();
        $resultado = Tickets::cerrar($ticketId, $motivo, $observacion, $monto, $usuario['id']);

        if ($resultado['ok']) {
            redirect('index.php?p=lista_tickets&msg=ticket_cerrado');
        }

        redirect('index.php?p=ficha_ticket&id=' . $ticketId . '&error=' . urlencode($resultado['error']));
        break;

    case 'administrar_usuarios':
        Auth::requiereAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::requerirValido();
            $accionUsuario = (string) ($_POST['accion'] ?? '');

            if ($accionUsuario === 'crear') {
                $nombreNuevo   = trim((string) ($_POST['nombre'] ?? ''));
                $usuarioNuevo  = trim((string) ($_POST['usuario'] ?? ''));
                $passwordNueva = (string) ($_POST['password'] ?? '');
                $rolNuevo      = (string) ($_POST['rol'] ?? '');

                if ($nombreNuevo === '' || $usuarioNuevo === '' || strlen($passwordNueva) < 6 || !in_array($rolNuevo, ['administrador', 'supervision'], true)) {
                    redirect('index.php?p=administrar_usuarios&error=' . urlencode('Completá todos los campos (password de al menos 6 caracteres)'));
                }

                try {
                    Database::pdo()->prepare('
                        INSERT INTO usuarios_acceso (nombre, usuario, password_hash, rol) VALUES (?, ?, ?, ?)
                    ')->execute([$nombreNuevo, $usuarioNuevo, password_hash($passwordNueva, PASSWORD_DEFAULT), $rolNuevo]);
                    redirect('index.php?p=administrar_usuarios&msg=' . urlencode('Usuario creado correctamente'));
                } catch (PDOException $e) {
                    redirect('index.php?p=administrar_usuarios&error=' . urlencode('Ese nombre de usuario ya existe'));
                }
            }

            if ($accionUsuario === 'toggle_activo') {
                $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
                Database::pdo()->prepare('UPDATE usuarios_acceso SET activo = NOT activo WHERE id = ?')->execute([$usuarioId]);
                redirect('index.php?p=administrar_usuarios&msg=' . urlencode('Usuario actualizado'));
            }
        }

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/administrar_usuarios.php';
        require __DIR__ . '/../views/partials/footer.php';
        break;

    case 'whatsapp_preview':
        header('Content-Type: application/json; charset=utf-8');
        $ticket = Tickets::porId((int) ($_GET['ticket_id'] ?? 0));
        if ($ticket === null) {
            echo json_encode(['ok' => false, 'error' => 'Ticket no encontrado']);
            exit;
        }

        $deuda          = SasApiClient::deuda($ticket['dni']);
        $cuotasVencidas = $deuda['ok'] ? (int) ($deuda['data']['cuotas_vencidas'] ?? 0) : 0;
        $agendasTicket  = Agendas::paraTicket($ticket['ticket_id']);

        echo json_encode([
            'ok'         => true,
            'telefono'   => $ticket['telefono_principal'],
            'plantillas' => WhatsApp::plantillasDisponibles($ticket['nombre_completo'], $cuotasVencidas, $agendasTicket),
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'whatsapp_enviar':
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
            exit;
        }

        $ticket = Tickets::porId((int) ($_POST['ticket_id'] ?? 0));
        if ($ticket === null || empty($ticket['telefono_principal'])) {
            echo json_encode(['ok' => false, 'error' => 'Ticket o teléfono no disponible']);
            exit;
        }

        $plantillaId    = (int) ($_POST['plantilla_id'] ?? 0);
        $deuda          = SasApiClient::deuda($ticket['dni']);
        $cuotasVencidas = $deuda['ok'] ? (int) ($deuda['data']['cuotas_vencidas'] ?? 0) : 0;
        $agendasTicket  = Agendas::paraTicket($ticket['ticket_id']);
        $disponibles    = WhatsApp::plantillasDisponibles($ticket['nombre_completo'], $cuotasVencidas, $agendasTicket);

        $elegida = null;
        foreach ($disponibles as $plantilla) {
            if ($plantilla['id'] === $plantillaId) {
                $elegida = $plantilla;
                break;
            }
        }

        if ($elegida === null) {
            echo json_encode(['ok' => false, 'error' => 'Esa plantilla no está disponible para este ticket']);
            exit;
        }

        $usuario = current_user();
        Gestiones::registrar([
            'ticket_id'   => $ticket['ticket_id'],
            'usuario_id'  => $usuario['id'],
            'canal'       => 'whatsapp',
            'resultado'   => 'enviado',
            'observacion' => $elegida['texto'],
        ]);

        echo json_encode([
            'ok'   => true,
            'link' => WhatsApp::construirLink($ticket['telefono_principal'], $elegida['texto']),
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'dashboard':
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/dashboard.php';
        require __DIR__ . '/../views/partials/footer.php';
        break;

    case 'agendas_accion':
        Csrf::requerirValido();
        $agendaId     = (int) ($_POST['agenda_id'] ?? 0);
        $accionAgenda = (string) ($_POST['accion'] ?? '');
        $usuario      = current_user();

        if ($accionAgenda === 'cumplida') {
            $montoReal = isset($_POST['monto_real']) && $_POST['monto_real'] !== '' ? (float) $_POST['monto_real'] : null;
            $resultado = Agendas::resolver($agendaId, 'cumplida', $montoReal, null);
            if ($resultado['ok'] && !empty($_POST['saldo_deuda'])) {
                Tickets::cerrar(
                    $resultado['ticket_id'],
                    'abono',
                    'Cerrado automáticamente al cumplir la agenda de cobro',
                    $montoReal,
                    $usuario['id']
                );
            }
        } elseif ($accionAgenda === 'incumplida') {
            Agendas::resolver($agendaId, 'incumplida', null, null);
        } elseif ($accionAgenda === 'reprogramar') {
            $nuevaFecha = (string) ($_POST['nueva_fecha'] ?? '');
            if ($nuevaFecha !== '') {
                Agendas::reprogramar($agendaId, $nuevaFecha, $usuario['id']);
            }
        }

        redirect('index.php?p=dashboard&tab=agendas');
        break;

    case 'refinanciaciones_accion':
        Csrf::requerirValido();
        $refiId     = (int) ($_POST['refinanciacion_id'] ?? 0);
        $accionRefi = (string) ($_POST['accion'] ?? '');
        $usuario    = current_user();

        if ($accionRefi === 'procesada_en_sas') {
            Auth::requiereAdmin();
            Refinanciaciones::marcarProcesadaEnSas($refiId, $usuario['id']);
        } elseif ($accionRefi === 'ofrecida') {
            Refinanciaciones::marcarOfrecida($refiId);
        } elseif ($accionRefi === 'aceptada') {
            Refinanciaciones::marcarAceptada($refiId);
        } elseif ($accionRefi === 'rechazada') {
            Refinanciaciones::marcarRechazada($refiId);
        }

        redirect('index.php?p=dashboard&tab=refinanciaciones');
        break;
}
