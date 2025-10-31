<?php
// panel/panel.php - versión corregida (arreglado error de parse)
require_once __DIR__ . '/../db.php';
session_start();

// requerir login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login/login.html');
    exit;
}
$uid = intval($_SESSION['usuario_id']);

// obtener rol y nombre
$user_role = 'donante';
$user_name = '';
if ($conn) {
    $stmt = $conn->prepare("SELECT rol, nombre FROM usuarios WHERE id_usuario = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            $row = $res->fetch_assoc();
            $user_role = $row['rol'] ?? 'donante';
            $user_name = $row['nombre'] ?? '';
        }
        $stmt->close();
    }
}

// manejo POST admin: separar "save" (editar) y acciones (approve/reject/delete)
// siempre devolver JSON cuando viene from=panel_js
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $user_role === 'admin') {
    $action = $_POST['action'];

    // Acción: cambiar rol de usuario (nuevo)
    if ($action === 'change_role') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $new_role = $_POST['new_role'] ?? '';
        $resp = ['ok' => false, 'msg' => ''];
        if ($user_id > 0 && in_array($new_role, ['donante','beneficiario','admin'])) {
            $s = $conn->prepare("UPDATE usuarios SET rol = ? WHERE id_usuario = ?");
            if ($s) {
                $s->bind_param("si", $new_role, $user_id);
                $ok = $s->execute();
                $s->close();
                $resp['ok'] = (bool)$ok;
                $resp['msg'] = $ok ? 'Rol actualizado.' : 'Error al actualizar rol.';
            } else {
                $resp['msg'] = 'Error en la consulta: ' . $conn->error;
            }
        } else {
            $resp['msg'] = 'Parámetros inválidos.';
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        $conn->close();
        exit;
    }

    // Acción: save (edición inline)
    if ($action === 'save') {
        $type = $_POST['type'] ?? '';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $response = ['ok' => false, 'msg' => ''];
        if ($id > 0 && in_array($type, ['campana','centro'])) {
            if ($type === 'campana') {
                $titulo = trim($_POST['titulo'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $direccion = trim($_POST['direccion'] ?? '');
                $fecha_inicio = $_POST['fecha_inicio'] ?: null;
                $fecha_fin = $_POST['fecha_fin'] ?: null;
                $meta = trim($_POST['meta'] ?? '');
                $categorias = trim($_POST['categorias'] ?? '');
                $stmt = $conn->prepare("UPDATE `campañas` SET titulo = ?, descripcion = ?, direccion = ?, fecha_inicio = ?, fecha_fin = ?, meta = ?, categorias = ? WHERE id_campaña = ?");
                if ($stmt) {
                    $stmt->bind_param("sssssssi", $titulo, $descripcion, $direccion, $fecha_inicio, $fecha_fin, $meta, $categorias, $id);
                    $ok = $stmt->execute();
                    $stmt->close();
                    $response['ok'] = (bool)$ok;
                    $response['msg'] = $ok ? 'Guardado correctamente.' : 'Error al guardar campaña.';
                } else {
                    $response['msg'] = 'Error en la consulta: ' . $conn->error;
                }
            } else {
                $nombre = trim($_POST['nombre'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $direccion = trim($_POST['direccion'] ?? '');
                $categorias = trim($_POST['categorias'] ?? '');
                $stmt = $conn->prepare("UPDATE `centros_donacion` SET nombre = ?, descripcion = ?, direccion = ?, categorias = ? WHERE id_centro = ?");
                if ($stmt) {
                    $stmt->bind_param("ssssi", $nombre, $descripcion, $direccion, $categorias, $id);
                    $ok = $stmt->execute();
                    $stmt->close();
                    $response['ok'] = (bool)$ok;
                    $response['msg'] = $ok ? 'Guardado correctamente.' : 'Error al guardar centro.';
                } else {
                    $response['msg'] = 'Error en la consulta: ' . $conn->error;
                }
            }
        } else {
            $response['msg'] = 'Solicitud inválida.';
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        $conn->close();
        exit;
    }

    // Acciones approve / reject / delete
    $type = $conn->real_escape_string($_POST['type'] ?? '');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $ok = false; $msg = '';
    if ($id > 0 && in_array($type, ['campana','centro'])) {
        if ($action === 'approve') {
            if ($type === 'campana') $stmt = $conn->prepare("UPDATE `campañas` SET estado = 'aprobado' WHERE id_campaña = ?");
            else $stmt = $conn->prepare("UPDATE `centros_donacion` SET estado = 'aprobado' WHERE id_centro = ?");
            if ($stmt) { $stmt->bind_param("i",$id); $ok = $stmt->execute(); $stmt->close(); $msg = $ok ? 'Aprobado correctamente.' : 'Error al aprobar.'; }
        } elseif ($action === 'reject') {
            if ($type === 'campana') $stmt = $conn->prepare("UPDATE `campañas` SET estado = 'rechazado' WHERE id_campaña = ?");
            else $stmt = $conn->prepare("UPDATE `centros_donacion` SET estado = 'rechazado' WHERE id_centro = ?");
            if ($stmt) { $stmt->bind_param("i",$id); $ok = $stmt->execute(); $stmt->close(); $msg = $ok ? 'Rechazado correctamente.' : 'Error al rechazar.'; }
        } elseif ($action === 'delete') {
            if ($type === 'campana') $stmt = $conn->prepare("DELETE FROM `campañas` WHERE id_campaña = ?");
            else $stmt = $conn->prepare("DELETE FROM `centros_donacion` WHERE id_centro = ?");
            if ($stmt) { $stmt->bind_param("i",$id); $ok = $stmt->execute(); $stmt->close(); $msg = $ok ? 'Eliminado correctamente.' : 'Error al eliminar.'; }
        } else {
            $msg = 'Acción no permitida.';
        }
    } else {
        $msg = 'Solicitud inválida.';
    }

    if (isset($_POST['from']) && $_POST['from'] === 'panel_js') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => (bool)$ok, 'action' => $action, 'type' => $type, 'id' => $id, 'msg' => $msg]);
        $conn->close();
        exit;
    }

    // si no es JS, mostrar mensaje en pantalla
    $mensaje = $msg;
    $tipo_msg = $ok ? 'success' : 'error';
}

// ----------------------------------
// Consultas para mostrar datos en la UI
// ----------------------------------
$pendingCampanas = []; $pendingCentros = []; $allCampanas = []; $allCentros = []; $allUsers = []; $myCampanas = []; $myCentros = [];

if ($conn) {
    if ($user_role === 'admin') {
        $res = $conn->query("SELECT id_campaña, titulo, descripcion, creador_id, fecha_inicio, fecha_fin, meta, imagenes FROM `campañas` WHERE estado = 'pendiente' ORDER BY id_campaña DESC LIMIT 200");
        if ($res) while ($r = $res->fetch_assoc()) $pendingCampanas[] = $r;

        $res = $conn->query("SELECT id_centro, nombre, descripcion, creador_id, imagenes FROM `centros_donacion` WHERE estado = 'pendiente' ORDER BY id_centro DESC LIMIT 200");
        if ($res) while ($r = $res->fetch_assoc()) $pendingCentros[] = $r;

        $res = $conn->query("SELECT id_campaña, titulo, estado, creador_id, fecha_inicio, fecha_fin, imagenes FROM `campañas` ORDER BY id_campaña DESC LIMIT 500");
        if ($res) while ($r = $res->fetch_assoc()) $allCampanas[] = $r;

        $res = $conn->query("SELECT id_centro, nombre, estado, creador_id, imagenes FROM `centros_donacion` ORDER BY id_centro DESC LIMIT 500");
        if ($res) while ($r = $res->fetch_assoc()) $allCentros[] = $r;

        $res = $conn->query("SELECT id_usuario, nombre, email, rol FROM usuarios ORDER BY id_usuario DESC LIMIT 1000");
        if ($res) while ($r = $res->fetch_assoc()) $allUsers[] = $r;

        // Añadir además las campañas/centros creadas por este admin (historial personal)
        $stmt = $conn->prepare("SELECT id_campaña, titulo, estado, fecha_inicio, fecha_fin, meta, imagenes FROM `campañas` WHERE creador_id = ? ORDER BY id_campaña DESC");
        if ($stmt) { $stmt->bind_param("i", $uid); $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $myCampanas[] = $r; $stmt->close(); }
        $stmt = $conn->prepare("SELECT id_centro, nombre, estado, descripcion, imagenes FROM `centros_donacion` WHERE creador_id = ? ORDER BY id_centro DESC");
        if ($stmt) { $stmt->bind_param("i", $uid); $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $myCentros[] = $r; $stmt->close(); }
    } elseif ($user_role === 'donante') {
        $stmt = $conn->prepare("SELECT id_campaña, titulo, estado, fecha_inicio, fecha_fin, meta, imagenes FROM `campañas` WHERE creador_id = ? ORDER BY id_campaña DESC");
        if ($stmt) { $stmt->bind_param("i", $uid); $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $myCampanas[] = $r; $stmt->close(); }
    } elseif ($user_role === 'beneficiario') {
        $stmt = $conn->prepare("SELECT id_centro, nombre, estado, descripcion, imagenes FROM `centros_donacion` WHERE creador_id = ? ORDER BY id_centro DESC");
        if ($stmt) { $stmt->bind_param("i", $uid); $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $myCentros[] = $r; $stmt->close(); }
    }
}

// Asegurar que el usuario actual esté presente en la lista allUsers (si admin)
if ($user_role === 'admin' && $conn) {
    $found = false;
    foreach ($allUsers as $u) { if (isset($u['id_usuario']) && intval($u['id_usuario']) === $uid) { $found = true; break; } }
    if (!$found) {
        $stmt = $conn->prepare("SELECT id_usuario, nombre, email, rol FROM usuarios WHERE id_usuario = ? LIMIT 1");
        if ($stmt) { $stmt->bind_param("i",$uid); $stmt->execute(); $r = $stmt->get_result(); if ($r && $r->num_rows) $allUsers[] = $r->fetch_assoc(); $stmt->close(); }
    }
}

// estadísticas básicas (placeholder)
$stats = [
    'total_campanas' => count($allCampanas) + count($pendingCampanas),
    'total_centros'  => count($allCentros) + count($pendingCentros),
    'pendientes'     => ['campanas' => count($pendingCampanas), 'centros' => count($pendingCentros)],
    'usuarios_total' => count($allUsers),
    'global_metrics' => ['visitas' => 0, 'donaciones_transferencia' => 0, 'donaciones_presencial' => 0]
];

// Después de cargar $allUsers (si admin), separar usuarios por rol para mostrar 3 tablas
$users_donante = [];
$users_beneficiario = [];
$users_admin = [];
if (!empty($allUsers) && is_array($allUsers)) {
    foreach ($allUsers as $u) {
        $r = $u['rol'] ?? '';
        if ($r === 'donante') $users_donante[] = $u;
        elseif ($r === 'beneficiario') $users_beneficiario[] = $u;
        elseif ($r === 'admin') $users_admin[] = $u;
        else $users_donante[] = $u; // default
    }
}

// NUEVO: control de vista (gestionado por PHP)
$currentView = 'buttons'; // pantalla inicial con botones
if (!empty($_REQUEST['view'])) {
	$req = trim($_REQUEST['view']);
	$allowed = ['buttons','metrics','aprobaciones','historial','usuarios','ajustes'];
	if (in_array($req, $allowed, true)) $currentView = $req;
}

// Endpoint: report_issue (graba en log JSON simple)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $user_role) {
    $action = $_POST['action'];
    if ($action === 'report_issue') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $resp = ['ok'=>false,'msg'=>''];
        if ($message !== '') {
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir,0755,true);
            $entry = ['time'=>date('c'),'user_id'=>$uid,'subject'=>$subject,'message'=>$message,'ip'=>$_SERVER['REMOTE_ADDR'] ?? ''];
            $file = $logDir . '/panel_reports.log';
            $ok = @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($ok !== false) { $resp['ok'] = true; $resp['msg'] = 'Reporte registrado. Gracias.'; }
            else { $resp['msg'] = 'No se pudo guardar el reporte en el servidor.'; }
        } else {
            $resp['msg'] = 'El mensaje no puede estar vacío.';
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        $conn->close();
        exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel — SolidariApp</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../crear/crear.css">
  <link rel="stylesheet" href="panel.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
  <header class="topbar">
    <div class="left">
      <img src="../crear/img/logo_header.png" class="brand-logo" alt="SolidariApp">
      <div class="brand-name">SolidariApp</div>
    </div>
    <div class="right">
      <button class="icon-btn bell-btn" title="Notificaciones" onclick="location.href='../notificaciones/'">
        <img src="../crear/img/campanita.png" alt="Notificaciones">
      </button>
    </div>
  </header>

  <main class="content">
    <section class="form-card panel-layout">
      <div class="panel-top">
        <h2>Panel</h2>
        <!-- Pestañas superiores eliminadas: usamos el mosaico/lista vertical de botones -->
      </div>

      <div class="panel-body">
        <div class="panel-main">

          <!-- PANTALLA INICIAL: botones por categoría (visible si view = 'buttons') -->
          <div id="sec-buttons" class="panel-section panel-list" data-visible="<?php echo ($currentView === 'buttons') ? 'true' : 'false'; ?>">
            <?php
              // Lista vertical full-width: los view keys deben coincidir con las secciones existentes
              $tiles = [
                ['id'=>'overview','title'=>'Métricas','desc'=>'Ver estadísticas detalladas y KPIs','btn'=>'Ver métricas'],
                ['id'=>'aprobaciones','title'=>'Aprobaciones','desc'=>'Revisar y aprobar campañas y centros','btn'=>'Ir a Aprobaciones'],
                ['id'=>'historial','title'=>'Historial','desc'=>'Historial de campañas y centros creados','btn'=>'Ver historial'],
                ['id'=>'usuarios','title'=>'Usuarios','desc'=>'Gestionar usuarios y roles','btn'=>'Gestionar usuarios'],
                ['id'=>'ajustes','title'=>'Ajustes','desc'=>'Reportes, notificaciones y perfil','btn'=>'Ir a Ajustes'],
              ];
              foreach ($tiles as $t):
            ?>
              <div class="panel-card" style="width:100%;display:flex;justify-content:space-between;align-items:center;">
                <div style="flex:1">
                  <div style="font-weight:800;font-size:16px"><?php echo htmlspecialchars($t['title']); ?></div>
                  <div class="muted-small" style="margin-top:6px"><?php echo htmlspecialchars($t['desc']); ?></div>
                </div>
                <form method="get" action="./panel.php" style="margin-left:18px;margin:0">
                  <input type="hidden" name="view" value="<?php echo $t['id']; ?>">
                  <button type="submit" class="btn-small" style="min-width:160px"><?php echo htmlspecialchars($t['btn']); ?></button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- ======================================================
               Secciones principales (están aquí para poder mostrarlas
               dinámicamente sin recargar la página). Cada sección
               incluye un botón volver (.btn-back).
               ====================================================== -->

          <!-- SECTION: OVERVIEW -->
          <div id="sec-overview" class="panel-section" data-visible="<?php echo ($currentView === 'overview') ? 'true' : 'false'; ?>" style="display: <?php echo ($currentView==='overview' ? 'block' : 'none'); ?>; margin-top:8px;">
            <div style="margin-bottom:12px">
              <button type="button" class="btn-small btn-back" data-target="buttons">← Volver</button>
            </div>

            <?php if ($user_role === 'admin'): ?>
              <div class="panel-grid">
                <div class="panel-card">
                  <div style="display:flex;justify-content:space-between;align-items:flex-start">
                    <div>
                      <div style="font-weight:800">Resumen</div>
                      <div class="muted-small">Visión general rápida</div>
                      <div style="display:flex;gap:18px;margin-top:12px">
                        <div><div style="font-weight:800;font-size:20px"><?php echo $stats['total_campanas']; ?></div><div class="muted-small">Campañas totales</div></div>
                        <div><div style="font-weight:800;font-size:20px"><?php echo $stats['total_centros']; ?></div><div class="muted-small">Centros totales</div></div>
                        <div><div style="font-weight:800;font-size:20px"><?php echo $stats['usuarios_total']; ?></div><div class="muted-small">Usuarios</div></div>
                      </div>
                    </div>
                    <div>
                      <button id="btn-metrics-open" class="btn-small">Ver métricas</button>
                    </div>
                  </div>
                </div>

                <div class="panel-card" id="mini-metrics">
                  <div style="font-weight:800">Métricas</div>
                  <div class="muted-small">Indicadores rápidos</div>
                  <div style="display:flex;gap:12px;margin-top:12px;align-items:center;justify-content:space-around">
                    <div style="text-align:center">
                      <canvas id="doughnut-visitas" width="140" height="100"></canvas>
                      <div class="muted-small">Visitas</div>
                    </div>
                    <div style="text-align:center">
                      <canvas id="doughnut-transf" width="140" height="100"></canvas>
                      <div class="muted-small">Donaciones (transfer.)</div>
                    </div>
                    <div style="text-align:center">
                      <canvas id="doughnut-pres" width="140" height="100"></canvas>
                      <div class="muted-small">Donaciones (presencial)</div>
                    </div>
                  </div>
                </div>

              </div>

              <div id="metrics-detailed" class="panel-card" style="margin-top:12px;display:none">
                <div style="font-weight:900;font-size:18px">Datos y Estadísticas</div>
                <div class="muted-small" style="margin-bottom:12px">Análisis detallado: visualizaciones, donaciones y rendimiento</div>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                  <div style="flex:1; min-width:280px">
                    <canvas id="chart-line-visits" style="width:100%;height:220px"></canvas>
                  </div>
                  <div style="flex:1; min-width:260px">
                    <canvas id="chart-bar-donations" style="width:100%;height:220px"></canvas>
                  </div>
                </div>
                <div style="margin-top:12px" class="muted-small">Nota: estos gráficos usan valores placeholder si no hay métricas registradas en la base.</div>
              </div>

            <?php else: ?>
              <div class="panel-card">
                <div style="font-weight:800">Bienvenido, <?php echo htmlspecialchars($user_name ?: 'usuario'); ?></div>
                <div class="muted-small">Rol: <?php echo htmlspecialchars($user_role); ?></div>
                <div style="margin-top:10px">Accedé a tu historial y ajustes desde las secciones.</div>
              </div>
            <?php endif; ?>
          </div>

          <!-- SECTION: APROBACIONES -->
          <div id="sec-aprobaciones" class="panel-section" data-visible="<?php echo ($currentView === 'aprobaciones') ? 'true' : 'false'; ?>" style="display: <?php echo ($currentView==='aprobaciones' ? 'block' : 'none'); ?>; margin-top:8px;">
            <div style="margin-bottom:12px">
              <button type="button" class="btn-small btn-back" data-target="buttons">← Volver</button>
            </div>

            <div class="subtabs">
              <button class="subtab active" data-sub="campanas">Campañas</button>
              <button class="subtab" data-sub="centros">Centros</button>
            </div>

            <div class="subcontent" id="sub-campanas" style="display:block">
              <?php if (count($pendingCampanas) === 0): ?>
                <div class="message">No hay campañas pendientes.</div>
              <?php else: ?>
                <div class="admin-table-wrapper">
                  <table class="admin-table" style="width:100%;border-collapse:collapse">
                    <thead><tr><th style="width:64px">Img</th><th>ID</th><th>Título</th><th>Creado por</th><th>Fechas</th><th>Meta</th><th>Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach($pendingCampanas as $pc): ?>
                        <tr data-row-type="campana" data-row-id="<?php echo (int)$pc['id_campaña']; ?>">
                          <td>
                            <?php
                              $thumb = '';
                              if (!empty($pc['imagenes'])) {
                                $imgs = json_decode($pc['imagenes'], true);
                                if (is_array($imgs) && count($imgs)) $thumb = $imgs[0];
                              }
                              // Normalizar ruta si no es URL absoluta
                              if ($thumb && !preg_match('#^(https?:)?//#i', $thumb) && substr($thumb,0,1) !== '/') {
                                  $thumb = '../uploads/' . ltrim($thumb, '/');
                              }
                            ?>
                            <?php if ($thumb): ?>
                              <img src="<?php echo htmlspecialchars($thumb); ?>" alt="" onerror="this.onerror=null;this.src='../crear/img/placeholder.png'">
                            <?php else: ?>
                              <img src="../crear/img/placeholder.png" alt="">
                            <?php endif; ?>
                          </td>
                          <td><?php echo (int)$pc['id_campaña']; ?></td>
                          <td class="td-title"><?php echo htmlspecialchars($pc['titulo']); ?></td>
                          <td><?php echo (int)$pc['creador_id']; ?></td>
                          <td><?php echo htmlspecialchars(($pc['fecha_inicio'] ?? '') . ' → ' . ($pc['fecha_fin'] ?? '')); ?></td>
                          <td><?php echo htmlspecialchars($pc['meta']); ?></td>
                          <td class="actions-col">
                            <button class="btn-small action-admin" data-action="approve" data-type="campana" data-id="<?php echo (int)$pc['id_campaña']; ?>">Aprobar</button>
                            <button class="btn-small action-admin danger" data-action="reject" data-type="campana" data-id="<?php echo (int)$pc['id_campaña']; ?>">Rechazar</button>
                            <button class="btn-small action-admin ghost" data-action="delete" data-type="campana" data-id="<?php echo (int)$pc['id_campaña']; ?>">Eliminar</button>
                            <button class="btn-small edit-btn" data-type="campana" data-id="<?php echo (int)$pc['id_campaña']; ?>">Editar</button>
                            <button class="btn-small view-btn" data-type="campana" data-id="<?php echo (int)$pc['id_campaña']; ?>">Vista</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>

            <div class="subcontent" id="sub-centros" style="display:none">
              <?php if (count($pendingCentros) === 0): ?>
                <div class="message">No hay centros pendientes.</div>
              <?php else: ?>
                <div class="admin-table-wrapper">
                  <table class="admin-table" style="width:100%;border-collapse:collapse">
                    <thead><tr><th style="width:64px">Img</th><th>ID</th><th>Nombre</th><th>Creado por</th><th>Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach($pendingCentros as $pc): ?>
                        <tr data-row-type="centro" data-row-id="<?php echo (int)$pc['id_centro']; ?>">
                          <td>
                            <?php
                              $thumbC = '';
                              if (!empty($pc['imagenes'])) {
                                $imgsC = json_decode($pc['imagenes'], true);
                                if (is_array($imgsC) && count($imgsC)) $thumbC = $imgsC[0];
                              }
                              // Normalizar ruta si no es URL absoluta
                              if ($thumbC && !preg_match('#^(https?:)?//#i', $thumbC) && substr($thumbC,0,1) !== '/') {
                                  $thumbC = '../uploads/' . ltrim($thumbC, '/');
                              }
                            ?>
                            <?php if ($thumbC): ?>
                              <img src="<?php echo htmlspecialchars($thumbC); ?>" alt="" onerror="this.onerror=null;this.src='../crear/img/placeholder.png'">
                            <?php else: ?>
                              <img src="../crear/img/placeholder.png" alt="">
                            <?php endif; ?>
                          </td>
                          <td><?php echo (int)$pc['id_centro']; ?></td>
                          <td class="td-title"><?php echo htmlspecialchars($pc['nombre']); ?></td>
                          <td><?php echo (int)$pc['creador_id']; ?></td>
                          <td class="actions-col">
                            <button class="btn-small action-admin" data-action="approve" data-type="centro" data-id="<?php echo (int)$pc['id_centro']; ?>">Aprobar</button>
                            <button class="btn-small action-admin danger" data-action="reject" data-type="centro" data-id="<?php echo (int)$pc['id_centro']; ?>">Rechazar</button>
                            <button class="btn-small action-admin ghost" data-action="delete" data-type="centro" data-id="<?php echo (int)$pc['id_centro']; ?>">Eliminar</button>
                            <button class="btn-small edit-btn" data-type="centro" data-id="<?php echo (int)$pc['id_centro']; ?>">Editar</button>
                            <button class="btn-small view-btn" data-type="centro" data-id="<?php echo (int)$pc['id_centro']; ?>">Vista</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- SECTION: HISTORIAL -->
          <div id="sec-historial" class="panel-section" data-visible="<?php echo ($currentView === 'historial') ? 'true' : 'false'; ?>" style="display: <?php echo ($currentView==='historial' ? 'block' : 'none'); ?>; margin-top:8px;">
            <div style="margin-bottom:12px">
              <button type="button" class="btn-small btn-back" data-target="buttons">← Volver</button>
            </div>

            <?php if ($user_role === 'admin'): ?>
              <div class="panel-card">
                <div style="font-weight:800">Últimas campañas (todas)</div>
                <div class="muted-small">Listado breve, acciones rápidas</div>
                <div class="admin-table-wrapper" style="margin-top:10px">
                  <table class="admin-table">
                    <thead><tr><th>ID</th><th>Título</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach($allCampanas as $c): ?>
                        <tr data-row-type="campana" data-row-id="<?php echo (int)$c['id_campaña']; ?>">
                          <td><?php echo (int)$c['id_campaña']; ?></td>
                          <td><?php echo htmlspecialchars($c['titulo']); ?></td>
                          <td class="status-col"><?php echo htmlspecialchars($c['estado']); ?></td>
                          <td>
                            <button class="btn-small view-btn" data-type="campana" data-id="<?php echo (int)$c['id_campaña']; ?>">Vista</button>
                            <button class="btn-small edit-btn" data-type="campana" data-id="<?php echo (int)$c['id_campaña']; ?>">Editar</button>
                            <button class="btn-small action-admin ghost" data-action="delete" data-type="campana" data-id="<?php echo (int)$c['id_campaña']; ?>">Eliminar</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="panel-card" style="margin-top:12px">
                <div style="font-weight:800">Tu historial de campañas (personales)</div>
                <div class="muted-small">Campañas creadas por vos</div>
                <div class="admin-table-wrapper" style="margin-top:10px">
                  <table class="admin-table">
                    <thead><tr><th>ID</th><th>Título</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach($myCampanas as $c): ?>
                        <tr data-row-type="campana" data-row-id="<?php echo (int)$c['id_campaña']; ?>">
                          <td><?php echo (int)$c['id_campaña']; ?></td>
                          <td><?php echo htmlspecialchars($c['titulo']); ?></td>
                          <td class="status-col"><?php echo htmlspecialchars($c['estado']); ?></td>
                          <td>
                            <button class="btn-small view-btn" data-type="campana" data-id="<?php echo (int)$c['id_campaña']; ?>">Vista</button>
                            <button class="btn-small edit-btn" data-type="campana" data-id="<?php echo (int)$c['id_campaña']; ?>">Editar</button>
                            <button class="btn-small action-admin ghost" data-action="delete" data-type="campana" data-id="<?php echo (int)$c['id_campaña']; ?>">Eliminar</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="panel-card" style="margin-top:12px">
                <div style="font-weight:800">Tu historial de centros (personales)</div>
                <div class="muted-small">Centros creados por vos</div>
                <div class="admin-table-wrapper" style="margin-top:10px">
                  <table class="admin-table">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach($myCentros as $c): ?>
                        <tr data-row-type="centro" data-row-id="<?php echo (int)$c['id_centro']; ?>">
                          <td><?php echo (int)$c['id_centro']; ?></td>
                          <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                          <td class="status-col"><?php echo htmlspecialchars($c['estado']); ?></td>
                          <td>
                            <button class="btn-small view-btn" data-type="centro" data-id="<?php echo (int)$c['id_centro']; ?>">Vista</button>
                            <button class="btn-small edit-btn" data-type="centro" data-id="<?php echo (int)$c['id_centro']; ?>">Editar</button>
                            <button class="btn-small action-admin ghost" data-action="delete" data-type="centro" data-id="<?php echo (int)$c['id_centro']; ?>">Eliminar</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

            <?php else: ?>
              <!-- Donante / Beneficiario: mostrar su historial (ya existentes) -->
              <!-- ...existing user-specific historial markup... -->
            <?php endif; ?>
          </div>

          <!-- SECTION: USUARIOS -->
          <div id="sec-usuarios" class="panel-section" data-visible="<?php echo ($currentView === 'usuarios') ? 'true' : 'false'; ?>" style="display: <?php echo ($currentView==='usuarios' ? 'block' : 'none'); ?>; margin-top:8px;">
            <div style="margin-bottom:12px">
              <button type="button" class="btn-small btn-back" data-target="buttons">← Volver</button>
            </div>

            <?php if ($user_role !== 'admin'): ?>
              <div class="message">Acceso solo para administradores.</div>
            <?php else: ?>
              <div class="panel-card">
                <div style="font-weight:800">Usuarios registrados</div>
                <div class="muted-small">Gestioná roles y visualizá información</div>

                <!-- DONANTES -->
                <h4 style="margin-top:12px">Donantes</h4>
                <div class="admin-table-wrapper" style="margin-bottom:12px">
                  <table class="admin-table">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach($users_donante as $u): $roleClass = 'role-donante'; ?>
                        <tr class="<?php echo $roleClass; ?>" data-user-id="<?php echo (int)$u['id_usuario']; ?>">
                          <td><?php echo (int)$u['id_usuario']; ?></td>
                          <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                          <td><?php echo htmlspecialchars($u['email']); ?></td>
                          <td>
                            <select class="role-select" data-user="<?php echo (int)$u['id_usuario']; ?>">
                              <option value="donante" <?php if($u['rol']=='donante') echo 'selected'; ?>>Donante</option>
                              <option value="beneficiario" <?php if($u['rol']=='beneficiario') echo 'selected'; ?>>Beneficiario</option>
                              <option value="admin" <?php if($u['rol']=='admin') echo 'selected'; ?>>Admin</option>
                            </select>
                          </td>
                          <td>
                            <button class="btn-small role-save" data-user="<?php echo (int)$u['id_usuario']; ?>">Guardar rol</button>
                            <button class="btn-small view-user" data-user="<?php echo (int)$u['id_usuario']; ?>">Ver</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <!-- BENEFICIARIOS -->
                <h4 style="margin-top:8px">Beneficiarios / Centros</h4>
                <div class="admin-table-wrapper" style="margin-bottom:12px">
                  <table class="admin-table">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach($users_beneficiario as $u): $roleClass = 'role-beneficiario'; ?>
                        <tr class="<?php echo $roleClass; ?>" data-user-id="<?php echo (int)$u['id_usuario']; ?>">
                          <td><?php echo (int)$u['id_usuario']; ?></td>
                          <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                          <td><?php echo htmlspecialchars($u['email']); ?></td>
                          <td>
                            <select class="role-select" data-user="<?php echo (int)$u['id_usuario']; ?>">
                              <option value="donante" <?php if($u['rol']=='donante') echo 'selected'; ?>>Donante</option>
                              <option value="beneficiario" <?php if($u['rol']=='beneficiario') echo 'selected'; ?>>Beneficiario</option>
                              <option value="admin" <?php if($u['rol']=='admin') echo 'selected'; ?>>Admin</option>
                            </select>
                          </td>
                          <td>
                            <button class="btn-small role-save" data-user="<?php echo (int)$u['id_usuario']; ?>">Guardar rol</button>
                            <button class="btn-small view-user" data-user="<?php echo (int)$u['id_usuario']; ?>">Ver</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <!-- ADMINS -->
                <h4 style="margin-top:8px">Administradores</h4>
                <div class="admin-table-wrapper" style="margin-bottom:12px">
                  <table class="admin-table">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach($users_admin as $u): $roleClass = 'role-admin'; ?>
                        <tr class="<?php echo $roleClass; ?>" data-user-id="<?php echo (int)$u['id_usuario']; ?>">
                          <td><?php echo (int)$u['id_usuario']; ?></td>
                          <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                          <td><?php echo htmlspecialchars($u['email']); ?></td>
                          <td>
                            <select class="role-select" data-user="<?php echo (int)$u['id_usuario']; ?>">
                              <option value="donante" <?php if($u['rol']=='donante') echo 'selected'; ?>>Donante</option>
                              <option value="beneficiario" <?php if($u['rol']=='beneficiario') echo 'selected'; ?>>Beneficiario</option>
                              <option value="admin" <?php if($u['rol']=='admin') echo 'selected'; ?>>Admin</option>
                            </select>
                          </td>
                          <td>
                            <button class="btn-small role-save" data-user="<?php echo (int)$u['id_usuario']; ?>">Guardar rol</button>
                            <button class="btn-small view-user" data-user="<?php echo (int)$u['id_usuario']; ?>">Ver</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

              </div>
            <?php endif; ?>
          </div>

          <!-- SECTION: AJUSTES -->
          <div id="sec-ajustes" class="panel-section" data-visible="<?php echo ($currentView === 'ajustes') ? 'true' : 'false'; ?>" style="display: <?php echo ($currentView==='ajustes' ? 'block' : 'none'); ?>; margin-top:8px;">
            <div style="margin-bottom:12px">
              <button type="button" class="btn-small btn-back" data-target="buttons">← Volver</button>
            </div>

            <div class="panel-grid">
              <div class="panel-card">
                <div style="font-weight:800">Ajustes generales</div>
                <div class="muted-small">Configuraciones básicas de la aplicación</div>
                <div style="margin-top:10px">
                  <div style="display:flex;flex-direction:column;gap:8px">
                    <!-- Modo mantenimiento eliminado por petición -->
                    <label>Notificaciones globales</label>
                    <div style="display:flex;flex-direction:column;gap:8px">
                      <label class="switch"><input type="checkbox" id="notif-email"><span class="slider"></span> Notificaciones por email</label>
                      <label class="switch"><input type="checkbox" id="notif-web"><span class="slider"></span> Notificaciones web</label>
                      <label class="switch"><input type="checkbox" id="notif-sms"><span class="slider"></span> Notificaciones SMS (simulado)</label>
                      <div style="margin-top:8px"><button id="save-notif" class="btn-small">Guardar preferencias</button></div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="panel-card">
                <div style="font-weight:800">Ajustes de cuenta</div>
                <div class="muted-small">Modificar tus preferencias y datos</div>
                <div style="margin-top:10px">
                  <a class="btn-small" href="../perfil/perfil.php">Editar perfil</a>
                </div>
              </div>
            </div>

            <div class="panel-card" style="margin-top:12px">
              <div style="font-weight:800">Reportar un problema</div>
              <div class="muted-small">Envia un reporte al equipo (registrado en el servidor)</div>
              <div style="margin-top:8px">
                <input type="text" id="report-subject" placeholder="Asunto (opcional)" style="width:100%;padding:8px;border:1px solid #e6eefc;border-radius:8px"><br>
                <textarea id="report-message" placeholder="Describe el problema..." style="width:100%;margin-top:8px;padding:8px;border:1px solid #e6eefc;border-radius:8px;min-height:120px"></textarea>
                <div class="report-actions" style="margin-top:8px;display:flex;justify-content:flex-start;gap:8px">
                  <button id="send-report" class="btn-small">Enviar reporte</button>
                </div>
              </div>
            </div>
          </div>

        </div> <!-- .panel-main -->

        <!-- Nota: la panel-side fue ocultada por CSS; la dejamos fuera del flujo -->
        <!-- ...existing right / side code removed or left commented ... -->

      </div> <!-- .panel-body -->

      <!-- Modal confirm (global) -->
      <div id="modal-confirm" class="modal hidden" aria-hidden="true">
        <div class="modal-card">
          <div id="modal-msg" style="font-weight:800">Confirmar acción</div>
          <div id="modal-sub" class="muted-small" style="margin-top:8px"></div>
          <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
            <button id="modal-cancel" class="btn-small">Cancelar</button>
            <button id="modal-confirm-btn" class="btn-small danger">Confirmar</button>
          </div>
        </div>
      </div>

      <!-- Modal edit (inline) -->
      <div id="modal-edit" class="modal hidden" aria-hidden="true">
        <div class="modal-card">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <div id="modal-edit-title" style="font-weight:800">Editar elemento</div>
            <button id="modal-edit-close" class="btn-small" aria-label="Cerrar">Cerrar</button>
          </div>

          <form id="modal-edit-form" style="margin-top:12px">
            <input type="hidden" name="type" id="edit-type">
            <input type="hidden" name="id" id="edit-id">

            <div id="edit-fields">
              <!-- campos dinámicos insertados por JS -->
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
              <button type="button" id="modal-edit-cancel" class="btn-small">Cancelar</button>
              <button type="submit" id="modal-edit-save" class="btn-small">Guardar</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal user detalle -->
      <div id="modal-user" class="modal hidden" aria-hidden="true">
        <div class="modal-card" style="max-width:520px">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <div id="modal-user-title" style="font-weight:800">Usuario</div>
            <button id="modal-user-close" class="btn-small">Cerrar</button>
          </div>
          <div id="modal-user-body" style="margin-top:12px"></div>
        </div>
      </div>

    </section>
  </main>

  <nav class="bottom-nav" role="navigation" aria-label="Navegación principal">
    <a href="../inicio/inicio.html" class="nav-item" title="Inicio">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3l9 8h-3v7h-4v-5H10v5H6v-7H3z"/></svg>
      <span class="nav-label">Inicio</span>
    </a>

    <a href="./panel.php" class="nav-item active" title="Panel">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
      <span class="nav-label">Panel</span>
    </a>

    <a href="../crear/crear.php" class="nav-item" title="Crear">
      <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11 11V6h2v5h5v2h-5v5h-2v-5H6v-2z"/></svg>
      <span class="nav-label">Crear</span>
    </a>

    <a href="../foros/foros.php" class="nav-item" title="Foro">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v14l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
      <span class="nav-label">Foro</span>
    </a>

    <a href="../perfil/perfil.php" class="nav-item" title="Perfil">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-4 0-8 2-8 6v2h16v-2c0-4-4-6-8-6z"/></svg>
      <span class="nav-label">Perfil</span>
    </a>
  </nav>

  <script>
    // export PANEL_DATA (igual que antes)
    window.PANEL_DATA = <?php
      $export = [
        'role' => $user_role,
        'pendingCampanas' => $pendingCampanas,
        'pendingCentros' => $pendingCentros,
        'allCampanas' => $allCampanas,
        'allCentros' => $allCentros,
        'allUsers' => $allUsers,
        'myCampanas' => $myCampanas,
        'myCentros' => $myCentros,
        'stats' => $stats
      ];
      echo json_encode($export, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>;
    // vista inicial para JS (permite comportamiento sin recarga)
    window.INIT_VIEW = "<?php echo $currentView; ?>";
  </script>

  <script src="panel.js"></script>
</body>
</html>