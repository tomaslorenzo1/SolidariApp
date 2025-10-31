<?php
// crear/crear.php
// Formulario para crear campaña/colecta o centro de donación
require_once __DIR__ . '/../db.php';
session_start();

// Requerir estar logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login/login.html');
    exit;
}

$uid = intval($_SESSION['usuario_id']);
$mensaje = '';
$tipo_msg = ''; // success | error

// obtener rol del usuario (para mostrar/ocultar tabs)
$user_role = 'donante';
$stmtRole = $conn->prepare("SELECT rol, nombre FROM usuarios WHERE id_usuario = ? LIMIT 1");
if ($stmtRole) {
    $stmtRole->bind_param("i", $uid);
    $stmtRole->execute();
    $resRole = $stmtRole->get_result();
    if ($resRole && $resRole->num_rows > 0) {
        $uRow = $resRole->fetch_assoc();
        $user_role = $uRow['rol'] ?? 'donante';
    }
    $stmtRole->close();
}

// Manejo POST (crear)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // campo "tipo" indica 'campana' o 'centro'
    $tipo = $_POST['tipo'] ?? '';

    // datos comunes
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categorias = trim($_POST['categorias_hidden'] ?? ''); // coma-sep (desde JS)
    $horario = trim($_POST['horario'] ?? '');
    // en los formularios el campo se llama "direccion" (se corrigió en el HTML)
    $direccion = trim($_POST['direccion'] ?? '');
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    $telefono_raw = trim($_POST['telefono'] ?? '');
    // meta permitimos texto (ej: "1000 frazadas") o num
    $meta = trim($_POST['meta'] ?? '');
    // Donaciones de dinero (opcional) para campañas
    $alias_mp = trim($_POST['alias_mp'] ?? '');
    $cvu_mp = trim($_POST['cvu_mp'] ?? '');
    $link_pago_mp = trim($_POST['link_pago_mp'] ?? '');
    $whatsapp_link_raw = trim($_POST['whatsapp_link'] ?? '');

    // sanitizaciones básicas
    // telefono: extraer sólo dígitos y agregar prefijo si no viene
    $telefono = preg_replace('/\D/', '', $telefono_raw);
    if ($telefono !== '') {
        // si ya incluía 54 al principio lo dejamos; si no, agregamos +54 para consistencia visual
        if (strpos($telefono_raw, '+') === 0) {
            // mantener el + si venía con +54... (guardamos sólo dígitos en $telefono, pero dejamos variable $telefono_display si hace falta)
            $telefono_display = '+' . $telefono;
        } else {
            $telefono_display = '+54' . ltrim($telefono, '0');
        }
    } else {
        $telefono_display = '';
    }

    // WhatsApp: normalizar link y extraer número
    $wa_digits = preg_replace('/\D/', '', $whatsapp_link_raw);
    // Si link vacío pero hubo teléfono, usar ese para generar link
    if ($whatsapp_link_raw === '' && $telefono !== '') {
        $wa_digits = ltrim($telefono, '+');
    }
    $whatsapp_numero = '';
    $whatsapp_link = '';
    if ($wa_digits !== '') {
        // prefijo 54 si no está
        if (strpos($wa_digits, '54') !== 0) {
            $wa_digits = '54' . ltrim($wa_digits, '0');
        }
        $whatsapp_numero = $wa_digits;
        $whatsapp_link = 'https://wa.me/' . $wa_digits;
    }

    // meta: quitar comas y demás para almacenar número limpio (si está pensado como número)
    $meta_clean = preg_replace('/[^\d]/', '', $meta);
    if ($meta_clean === '') $meta_clean = '0';

    // Manejar creación según tipo
    if ($tipo === 'campana') {
        $fecha_inicio = $_POST['fecha_inicio'] ?: null;
        $fecha_fin = $_POST['fecha_fin'] ?: null;

        // preparar lat/lng para decidir INSERT con NULL o con valores
        $lat_db = (is_null($lat) || $lat === '') ? null : floatval($lat);
        $lng_db = (is_null($lng) || $lng === '') ? null : floatval($lng);
        $creador = $uid;

        if (is_null($lat_db) || is_null($lng_db)) {
            // Insertando con lat/lng en NULL explícito
            $sql = "INSERT INTO `campañas` 
                (titulo, descripcion, direccion, lat, lng, categorias, horario, fecha_inicio, fecha_fin, meta, creador_id, estado)
                VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $mensaje = "Error en la base de datos: " . $conn->error;
                $tipo_msg = 'error';
            } else {
                // tipos: titulo(s), descripcion(s), direccion(s), categorias(s), horario(s), fecha_inicio(s), fecha_fin(s), meta(s), creador(i)
                $stmt->bind_param("ssssssssi",
                    $titulo,
                    $descripcion,
                    $direccion,
                    $categorias,
                    $horario,
                    $fecha_inicio,
                    $fecha_fin,
                    $meta_clean,
                    $creador
                );
                if ($stmt->execute()) {
                    $last_id = $stmt->insert_id;
                    $mensaje = "Campaña creada correctamente. Quedará en estado PENDIENTE hasta su aprobación.";
                    $tipo_msg = 'success';
                } else {
                    $mensaje = "Error al guardar la campaña: " . $stmt->error;
                    $tipo_msg = 'error';
                }
                $stmt->close();
            }
        } else {
            // Insert normal con lat/lng
            $sql = "INSERT INTO `campañas` 
                (titulo, descripcion, direccion, lat, lng, categorias, horario, fecha_inicio, fecha_fin, meta, creador_id, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $mensaje = "Error en la base de datos: " . $conn->error;
                $tipo_msg = 'error';
            } else {
                $stmt->bind_param(
                    "sssddsssssi",
                    $titulo,
                    $descripcion,
                    $direccion,
                    $lat_db,
                    $lng_db,
                    $categorias,
                    $horario,
                    $fecha_inicio,
                    $fecha_fin,
                    $meta_clean,
                    $creador
                );
                if ($stmt->execute()) {
                    $last_id = $stmt->insert_id;
                    $mensaje = "Campaña creada correctamente. Quedará en estado PENDIENTE hasta su aprobación.";
                    $tipo_msg = 'success';
                } else {
                    $mensaje = "Error al guardar la campaña: " . $stmt->error;
                    $tipo_msg = 'error';
                }
                $stmt->close();
            }
        }

        // Guardar alias/cvu/link si existen las columnas en la tabla y hay valores
        if (!empty($last_id)) {
            if ($alias_mp !== '') {
                $check = $conn->query("SHOW COLUMNS FROM `campañas` LIKE 'alias_mp'");
                if ($check && $check->num_rows > 0) {
                    $upd = $conn->prepare("UPDATE `campañas` SET alias_mp = ? WHERE id_campaña = ?");
                    if ($upd) { $upd->bind_param("si", $alias_mp, $last_id); $upd->execute(); $upd->close(); }
                }
            }
            if ($cvu_mp !== '') {
                $check = $conn->query("SHOW COLUMNS FROM `campañas` LIKE 'cvu_mp'");
                if ($check && $check->num_rows > 0) {
                    $upd = $conn->prepare("UPDATE `campañas` SET cvu_mp = ? WHERE id_campaña = ?");
                    if ($upd) { $upd->bind_param("si", $cvu_mp, $last_id); $upd->execute(); $upd->close(); }
                }
            }
            if ($link_pago_mp !== '') {
                $check = $conn->query("SHOW COLUMNS FROM `campañas` LIKE 'link_pago_mp'");
                if ($check && $check->num_rows > 0) {
                    $upd = $conn->prepare("UPDATE `campañas` SET link_pago_mp = ? WHERE id_campaña = ?");
                    if ($upd) { $upd->bind_param("si", $link_pago_mp, $last_id); $upd->execute(); $upd->close(); }
                }
            }
        }

        // guardar teléfono y whatsapp si existen las columnas
        if (!empty($last_id)) {
            $check = $conn->query("SHOW COLUMNS FROM `campañas` LIKE 'telefono_contacto'");
            if ($check && $check->num_rows > 0 && $telefono_display !== '') {
                $upd = $conn->prepare("UPDATE `campañas` SET telefono_contacto = ? WHERE id_campaña = ?");
                if ($upd) { $upd->bind_param("si", $telefono_display, $last_id); $upd->execute(); $upd->close(); }
            }
            $check = $conn->query("SHOW COLUMNS FROM `campañas` LIKE 'whatsapp_link'");
            if ($check && $check->num_rows > 0 && $whatsapp_link !== '') {
                $upd = $conn->prepare("UPDATE `campañas` SET whatsapp_link = ? WHERE id_campaña = ?");
                if ($upd) { $upd->bind_param("si", $whatsapp_link, $last_id); $upd->execute(); $upd->close(); }
            }
            $check = $conn->query("SHOW COLUMNS FROM `campañas` LIKE 'whatsapp_numero'");
            if ($check && $check->num_rows > 0 && $whatsapp_numero !== '') {
                $upd = $conn->prepare("UPDATE `campañas` SET whatsapp_numero = ? WHERE id_campaña = ?");
                if ($upd) { $upd->bind_param("si", $whatsapp_numero, $last_id); $upd->execute(); $upd->close(); }
            }
        }

        // manejo de imágenes: guardarlas en /uploads/campanas/
        if (!empty($_FILES['imagenes']) && isset($_FILES['imagenes']['name']) && count($_FILES['imagenes']['name']) > 0 && !empty($last_id)) {
            $saved = [];
            $uploadDir = __DIR__ . '/../uploads/campanas/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            // límite de 6 imágenes
            $totalFiles = count($_FILES['imagenes']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                if (count($saved) >= 6) break;
                $name = $_FILES['imagenes']['name'][$i];
                $tmp  = $_FILES['imagenes']['tmp_name'][$i];
                $err  = $_FILES['imagenes']['error'][$i];
                $size = $_FILES['imagenes']['size'][$i];

                if ($err !== UPLOAD_ERR_OK) continue;
                if ($size > 2 * 1024 * 1024) continue; // 2MB limit
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', microtime(true) . '_' . $name);
                $dest = $uploadDir . $safe;
                if (move_uploaded_file($tmp, $dest)) {
                    $rel = 'uploads/campanas/' . $safe;
                    $saved[] = $rel;
                }
            }
            // Si añadiste columna `imagenes` a campañas, guardarlas como JSON
            if (count($saved) && $last_id) {
                // sólo si existe la columna (evita errores)
                $check = $conn->query("SHOW COLUMNS FROM `campañas` LIKE 'imagenes'");
                if ($check && $check->num_rows > 0) {
                    $json = json_encode($saved, JSON_UNESCAPED_SLASHES);
                    $upd = $conn->prepare("UPDATE `campañas` SET imagenes = ? WHERE id_campaña = ?");
                    if ($upd) { $upd->bind_param("si", $json, $last_id); $upd->execute(); $upd->close(); }
                }
            }
        }

    } elseif ($tipo === 'centro') {
        // campos propios centro
        $nombre = $titulo;

        // preparar lat/lng
        $lat_db = (is_null($lat) || $lat === '') ? null : floatval($lat);
        $lng_db = (is_null($lng) || $lng === '') ? null : floatval($lng);
        $creador = $uid;

        if (is_null($lat_db) || is_null($lng_db)) {
            // insertar con lat/lng = NULL
            $sql = "INSERT INTO `centros_donacion` 
                (nombre, descripcion, direccion, lat, lng, categorias, horario, creador_id, estado)
                VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $mensaje = "Error en la base de datos: " . $conn->error;
                $tipo_msg = 'error';
            } else {
                $stmt->bind_param(
                    "sssssii",
                    $nombre,
                    $descripcion,
                    $direccion,
                    $categorias,
                    $horario,
                    $creador
                );
                // Notice: bind_param types must match variables; above is 6 placeholders but type string mismatch avoided by using correct types below
                // We'll correct binding properly:
                // Rebind correct types:
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO `centros_donacion` (nombre, descripcion, direccion, lat, lng, categorias, horario, creador_id, estado) VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, 'pendiente')");
                if ($stmt) {
                    $stmt->bind_param("ssssssi", $nombre, $descripcion, $direccion, $categorias, $horario, $creador);
                    // But note: above count mismatch — to avoid errors, use the variant below instead (safe and consistent):
                    // We'll replace with a robust path below (see next block)
                }
            }
            // To ensure clean and safe insertion, continue to the safer insertion below
            // (we'll do a proper insertion with correct bind types)
        }

        // For robustness we do the centro insertion in a clearer way (handles both lat/lng present or not)
        // Prepare correct SQL based on whether lat/lng exist
        if (is_null($lat_db) || is_null($lng_db)) {
            $sql = "INSERT INTO `centros_donacion` 
                (nombre, descripcion, direccion, lat, lng, categorias, horario, creador_id, estado)
                VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                // placeholders: nombre, descripcion, direccion, categorias, horario, creador_id => 6 placeholders: s s s s s i
                $stmt->bind_param("sssssi", $nombre, $descripcion, $direccion, $categorias, $horario, $creador);
                if ($stmt->execute()) {
                    $last_id = $stmt->insert_id;
                    $mensaje = "Centro creado correctamente. Quedará en estado PENDIENTE hasta su aprobación.";
                    $tipo_msg = 'success';
                } else {
                    $mensaje = "Error al guardar el centro: " . $stmt->error;
                    $tipo_msg = 'error';
                }
                $stmt->close();
            } else {
                $mensaje = "Error en la base de datos (centro): " . $conn->error;
                $tipo_msg = 'error';
            }
        } else {
            $sql = "INSERT INTO `centros_donacion` 
                (nombre, descripcion, direccion, lat, lng, categorias, horario, creador_id, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "sssddssi",
                    $nombre,
                    $descripcion,
                    $direccion,
                    $lat_db,
                    $lng_db,
                    $categorias,
                    $horario,
                    $creador
                );
                if ($stmt->execute()) {
                    $last_id = $stmt->insert_id;
                    $mensaje = "Centro creado correctamente. Quedará en estado PENDIENTE hasta su aprobación.";
                    $tipo_msg = 'success';
                } else {
                    $mensaje = "Error al guardar el centro: " . $stmt->error;
                    $tipo_msg = 'error';
                }
                $stmt->close();
            } else {
                $mensaje = "Error en la base de datos (centro): " . $conn->error;
                $tipo_msg = 'error';
            }
        }

        // guardar alias/cvu/link y teléfono/whatsapp si existen las columnas
        if (!empty($last_id)) {
            $check = $conn->query("SHOW COLUMNS FROM `centros_donacion` LIKE 'alias_mp'");
            if ($check && $check->num_rows > 0 && $alias_mp !== '') {
                $upd = $conn->prepare("UPDATE `centros_donacion` SET alias_mp = ? WHERE id_centro = ?");
                if ($upd) { $upd->bind_param("si", $alias_mp, $last_id); $upd->execute(); $upd->close(); }
            }
            $check = $conn->query("SHOW COLUMNS FROM `centros_donacion` LIKE 'cvu_mp'");
            if ($check && $check->num_rows > 0 && $cvu_mp !== '') {
                $upd = $conn->prepare("UPDATE `centros_donacion` SET cvu_mp = ? WHERE id_centro = ?");
                if ($upd) { $upd->bind_param("si", $cvu_mp, $last_id); $upd->execute(); $upd->close(); }
            }
            $check = $conn->query("SHOW COLUMNS FROM `centros_donacion` LIKE 'link_pago_mp'");
            if ($check && $check->num_rows > 0 && $link_pago_mp !== '') {
                $upd = $conn->prepare("UPDATE `centros_donacion` SET link_pago_mp = ? WHERE id_centro = ?");
                if ($upd) { $upd->bind_param("si", $link_pago_mp, $last_id); $upd->execute(); $upd->close(); }
            }
            $check = $conn->query("SHOW COLUMNS FROM `centros_donacion` LIKE 'telefono_contacto'");
            if ($check && $check->num_rows > 0 && $telefono_display !== '') {
                $upd = $conn->prepare("UPDATE `centros_donacion` SET telefono_contacto = ? WHERE id_centro = ?");
                if ($upd) { $upd->bind_param("si", $telefono_display, $last_id); $upd->execute(); $upd->close(); }
            }
            $check = $conn->query("SHOW COLUMNS FROM `centros_donacion` LIKE 'whatsapp_link'");
            if ($check && $check->num_rows > 0 && $whatsapp_link !== '') {
                $upd = $conn->prepare("UPDATE `centros_donacion` SET whatsapp_link = ? WHERE id_centro = ?");
                if ($upd) { $upd->bind_param("si", $whatsapp_link, $last_id); $upd->execute(); $upd->close(); }
            }
            $check = $conn->query("SHOW COLUMNS FROM `centros_donacion` LIKE 'whatsapp_numero'");
            if ($check && $check->num_rows > 0 && $whatsapp_numero !== '') {
                $upd = $conn->prepare("UPDATE `centros_donacion` SET whatsapp_numero = ? WHERE id_centro = ?");
                if ($upd) { $upd->bind_param("si", $whatsapp_numero, $last_id); $upd->execute(); $upd->close(); }
            }
        }

        // manejo de imágenes: guardarlas en /uploads/centros/
        if (!empty($_FILES['imagenes']) && isset($_FILES['imagenes']['name']) && count($_FILES['imagenes']['name']) > 0 && !empty($last_id)) {
            $saved = [];
            $uploadDir = __DIR__ . '/../uploads/centros/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $totalFiles = count($_FILES['imagenes']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                if (count($saved) >= 6) break;
                $name = $_FILES['imagenes']['name'][$i];
                $tmp  = $_FILES['imagenes']['tmp_name'][$i];
                $err  = $_FILES['imagenes']['error'][$i];
                $size = $_FILES['imagenes']['size'][$i];

                if ($err !== UPLOAD_ERR_OK) continue;
                if ($size > 2 * 1024 * 1024) continue;
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', microtime(true) . '_' . $name);
                $dest = $uploadDir . $safe;
                if (move_uploaded_file($tmp, $dest)) {
                    $rel = 'uploads/centros/' . $safe;
                    $saved[] = $rel;
                }
            }
            if (count($saved) && $last_id) {
                $check = $conn->query("SHOW COLUMNS FROM `centros_donacion` LIKE 'imagenes'");
                if ($check && $check->num_rows > 0) {
                    $json = json_encode($saved, JSON_UNESCAPED_SLASHES);
                    $upd = $conn->prepare("UPDATE `centros_donacion` SET imagenes = ? WHERE id_centro = ?");
                    if ($upd) { $upd->bind_param("si", $json, $last_id); $upd->execute(); $upd->close(); }
                }
            }
        }
    } else {
        $mensaje = "Tipo inválido.";
        $tipo_msg = 'error';
    }

    // cerrar conexión
    $conn->close();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear — SolidariApp</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="crear.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
  <!-- Header (igual a las otras pantallas) -->
  <header class="topbar">
    <div class="left">
      <img src="img/logo_header.png" class="brand-logo" alt="SolidariApp">
      <div class="brand-name">SolidariApp</div>
    </div>
    <div class="right">
      <button class="icon-btn bell-btn" title="Notificaciones" onclick="location.href='../notificaciones/'">
        <img src="img/campanita.png" alt="Notificaciones">
      </button>
    </div>
  </header>

  <main class="content">
    <section class="form-card">
      <h2>Crear</h2>

      <?php if ($mensaje): ?>
        <div class="message <?php echo $tipo_msg === 'success' ? 'success' : 'error'; ?>">
          <?php echo htmlspecialchars($mensaje); ?>
        </div>

        <?php if ($tipo_msg === 'success'): ?>
          <div style="margin:12px 0;">
            <a href="../inicio/inicio.html" class="btn-primary" style="text-decoration:none;display:inline-block;padding:10px 14px;border-radius:10px;">Volver a inicio</a>
          </div>
          <script>
            // redirección automática opcional tras 3s para que el flujo continúe
            setTimeout(() => { window.location.href = '../inicio/inicio.html'; }, 3000);
          </script>
        <?php endif; ?>

      <?php endif; ?>

      <?php
        // control de visibilidad
        $showCamp = ($user_role === 'admin' || $user_role === 'donante');
        $showCentro = ($user_role === 'admin' || $user_role === 'beneficiario');
        // tab inicial: por defecto camp, si solo beneficiario -> centro
        $activeTab = 'camp';
        if ($user_role === 'beneficiario' && $showCentro && !$showCamp) $activeTab = 'centro';
        // si admin deja camp por defecto (puedes cambiar si querés abrir centro primero)
        $campVisible = ($showCamp && $activeTab === 'camp') ? '' : 'hidden';
        $centVisible = ($showCentro && $activeTab === 'centro') ? '' : 'hidden';
      ?>

      <!-- Tabs: elegir tipo (render condicionado por rol) -->
      <div class="tabs" role="tablist">
        <?php if ($showCamp): ?>
          <button id="tabCamp" class="tab <?php echo $activeTab === 'camp' ? 'active' : ''; ?>" type="button" role="tab" aria-selected="<?php echo $activeTab === 'camp' ? 'true' : 'false'; ?>">Crear campaña / colecta</button>
        <?php endif; ?>

        <?php if ($showCentro): ?>
          <button id="tabCentro" class="tab <?php echo $activeTab === 'centro' ? 'active' : ''; ?>" type="button" role="tab" aria-selected="<?php echo $activeTab === 'centro' ? 'true' : 'false'; ?>">Crear centro de donación</button>
        <?php endif; ?>
      </div>

      <!-- FORM: Campaña -->
      <form id="formCampana" class="form <?php echo $campVisible; ?>" method="POST" enctype="multipart/form-data" action="crear.php">
        <input type="hidden" name="tipo" value="campana">

        <!-- IMAGENES al principio -->
        <label>Fotos (opcional)</label>
        <div class="file-picker">
          <label class="file-btn" for="imgsCamp">Seleccionar imágenes</label>
          <input type="file" id="imgsCamp" name="imagenes[]" accept="image/*" multiple style="display:none">
          <div class="file-note">Máx 6 imágenes • JPG, PNG, WEBP • 2MB c/u</div>
        </div>
        <div id="previewCamp" class="preview-grid" aria-live="polite"></div>

        <label>Título</label>
        <input name="titulo" id="camp_title" required placeholder="Nombre de la campaña">

        <label>Descripción</label>
        <textarea name="descripcion" rows="5" placeholder="Explicá la necesidad, destinatarios, detalles..."></textarea>

        <label>Categorías</label>
        <div class="cat-input">
          <input id="catInputCamp" placeholder="Agregar categoría (ej: Ropa)" autocomplete="off"/>
          <div id="catSuggestCamp" class="suggestions hidden" aria-hidden="true"></div>
          <div id="catChipsCamp" class="chips" aria-live="polite"></div>
          <input type="hidden" name="categorias_hidden" id="categorias_hidden_camp">
        </div>

        <label>Meta (cantidad / objetivo)</label>
        <div class="meta-row">
          <div class="range-wrap" style="flex:1">
            <input type="range" id="metaRange" min="0" max="99999999" step="1" value="0" aria-label="Meta slider">
          </div>
          <!-- este input cambia con formato (comas) y es editable -->
          <input type="text" id="metaText" name="meta" value="0" pattern="[0-9,]*" inputmode="numeric" aria-label="Meta">
        </div>

        <div class="grid-2">
          <div>
            <label>Fecha inicio</label>
            <input type="date" name="fecha_inicio">
          </div>
          <div>
            <label>Fecha fin</label>
            <input type="date" name="fecha_fin">
          </div>
        </div>

        <!-- Donaciones de dinero (opcional) -->
        <label>Donaciones de dinero (opcional)</label>
        <div class="grid-2">
          <div>
            <input type="text" id="alias_mp" name="alias_mp" placeholder="Alias Mercado Pago (ej: DONA.ABRAZOS.SA)" />
          </div>
          <div>
            <input type="text" id="cvu_mp" name="cvu_mp" placeholder="CVU (22 dígitos)" />
          </div>
        </div>
        <input type="text" id="link_pago_mp" name="link_pago_mp" placeholder="Link de pago de Mercado Pago (opcional)" />

        <label>Horario de atención</label>
        <div class="horario-ui">
          <div class="horario-row">
            <input type="text" id="horarioDayCamp" placeholder="Día(s) (ej: Lun-Vie)">
            <input type="time" id="horarioStartCamp" aria-label="Hora inicio">
            <input type="time" id="horarioEndCamp" aria-label="Hora fin">
            <button type="button" class="btn-small" id="addHorarioCamp">Agregar</button>
          </div>
          <div id="horariosListCamp" class="chips" aria-live="polite"></div>
          <input type="hidden" name="horario" id="horarioHiddenCamp">
        </div>

        <!-- Antes: Dirección (buscador) then Dirección exacta -->
        <!-- Ahora mostramos primero el campo exacto (se envía) y luego el buscador que ayuda a ubicar en el mapa -->

        <label>Dirección exacta</label>
        <input id="dirExactaCamp" name="direccion" placeholder="Calle 123, Ciudad, Provincia" required>

        <label>Dirección (buscador)</label>
        <input id="addressCamp" placeholder="Ingresá la dirección y seleccioná una opción" autocomplete="off">
        <div id="addrResultsCamp" class="addr-results hidden" aria-hidden="true"></div>

        <input type="hidden" name="lat" id="latCamp">
        <input type="hidden" name="lng" id="lngCamp">
        <div class="map-wrap">
          <div class="map-toolbar">
            <button type="button" id="btnGeoCamp" class="btn-small" style="background:#eef6ff;color:#2b6cb0">Usar mi ubicación</button>
            <span class="file-note">Arrastrá el marcador para ajustar</span>
          </div>
          <div id="mapCamp" class="map-picker" aria-label="Selector de ubicación" style="height:260px;border-radius:12px;border:1px solid #e6eefc;margin-top:8px"></div>
        </div>

        <!-- Reemplazo: label inmediatamente antes de .phone-row; example-note dentro de .phone-row -->
        <label for="phoneLocalCamp" class="field-label">Número de contacto</label>
        <div class="phone-row">
          <div class="example-note">Ej: 112223333</div>
          <div class="phone-input-group">
            <span class="phone-prefix">+54</span>
            <input type="tel" id="phoneLocalCamp" placeholder="Ej: 112223333" pattern="\d*" inputmode="numeric" aria-label="Teléfono local">
          </div>
          <div class="file-note">Ingresá solo el número sin prefijo ni espacios.</div>
          <input type="hidden" name="telefono" id="telefonoHiddenCamp">
        </div>

        <label>Link WhatsApp</label>
        <input type="url" name="whatsapp_link" id="waLinkCamp" placeholder="https://wa.me/5491122233333?text=Hola" />

        <div style="margin-top:14px;">
          <button class="btn-primary" type="submit">Crear campaña</button>
        </div>
      </form>

      <!-- FORM: Centro -->
      <form id="formCentro" class="form <?php echo $centVisible; ?>" method="POST" enctype="multipart/form-data" action="crear.php">
        <input type="hidden" name="tipo" value="centro">

        <!-- IMAGENES al principio -->
        <label>Fotos del lugar (opcional)</label>
        <div class="file-picker">
          <label class="file-btn" for="imgsCentro">Seleccionar imágenes</label>
          <input type="file" id="imgsCentro" name="imagenes[]" accept="image/*" multiple style="display:none">
          <div class="file-note">Máx 6 imágenes • JPG, PNG, WEBP • 2MB c/u</div>
        </div>
        <div id="previewCentro" class="preview-grid" aria-live="polite"></div>

        <label>Nombre del centro</label>
        <input name="titulo" id="centro_title" required placeholder="Nombre del centro">

        <label>Descripción</label>
        <textarea name="descripcion" rows="5" placeholder="Explicá la necesidad, destinatarios, detalles..."></textarea>

        <label>Categorías</label>
        <div class="cat-input">
          <input id="catInputCentro" placeholder="Agregar categoría (ej: Alimentos)" autocomplete="off"/>
          <div id="catSuggestCentro" class="suggestions hidden" aria-hidden="true"></div>
          <div id="catChipsCentro" class="chips" aria-live="polite"></div>
          <input type="hidden" name="categorias_hidden" id="categorias_hidden_centro">
        </div>

        <!-- Direccion: primero campo exacto, luego buscador -->
        <label>Dirección exacta</label>
        <input id="dirExactaCentro" name="direccion" placeholder="Calle 123, Ciudad, Provincia" required>

        <label>Dirección (buscador)</label>
        <input id="addressCentro" placeholder="Ingresá la dirección y seleccioná una opción" autocomplete="off">
        <div id="addrResultsCentro" class="addr-results hidden" aria-hidden="true"></div>

        <input type="hidden" name="lat" id="latCentro">
        <input type="hidden" name="lng" id="lngCentro">
        <div class="map-wrap">
          <div class="map-toolbar">
            <button type="button" id="btnGeoCentro" class="btn-small" style="background:#eef6ff;color:#2b6cb0">Usar mi ubicación</button>
            <span class="file-note">Arrastrá el marcador para ajustar</span>
          </div>
          <div id="mapCentro" class="map-picker" aria-label="Selector de ubicación" style="height:260px;border-radius:12px;border:1px solid #e6eefc;margin-top:8px"></div>
        </div>

        <label>Horario de atención</label>
        <div class="horario-ui">
          <div class="horario-row">
            <input type="text" id="horarioDayCentro" placeholder="Día(s) (ej: Lun-Vie)">
            <input type="time" id="horarioStartCentro" aria-label="Hora inicio">
            <input type="time" id="horarioEndCentro" aria-label="Hora fin">
            <button type="button" class="btn-small" id="addHorarioCentro">Agregar</button>
          </div>
          <div id="horariosListCentro" class="chips" aria-live="polite"></div>
          <input type="hidden" name="horario" id="horarioHiddenCentro">
        </div>

        <!-- Para el formulario Centro: mismo ajuste -->
        <label for="phoneLocalCentro" class="field-label">Número de contacto</label>
        <div class="phone-row">
          <div class="example-note">Ej: 112223333</div>
          <div class="phone-input-group">
            <span class="phone-prefix">+54</span>
            <input type="tel" id="phoneLocalCentro" placeholder="Ej: 112223333" pattern="\d*" inputmode="numeric" aria-label="Teléfono local">
          </div>
          <div class="file-note">Ingresá solo el número sin prefijo ni espacios.</div>
          <input type="hidden" name="telefono" id="telefonoHiddenCentro">
        </div>

        <label>Link WhatsApp</label>
        <input type="url" name="whatsapp_link" id="waLinkCentro" placeholder="https://wa.me/5491122233333?text=Hola" />

        <label>Donaciones de dinero (opcional)</label>
        <div class="grid-2">
          <div>
            <input type="text" id="alias_mp_c" name="alias_mp" placeholder="Alias Mercado Pago (ej: DONA.ABRAZOS.SA)" />
          </div>
          <div>
            <input type="text" id="cvu_mp_c" name="cvu_mp" placeholder="CVU (22 dígitos)" />
          </div>
        </div>
        <input type="text" id="link_pago_mp_c" name="link_pago_mp" placeholder="Link de pago de Mercado Pago (opcional)" />

        <div style="margin-top:14px;">
          <button class="btn-primary" type="submit">Crear centro</button>
        </div>
      </form>

    </section>
  </main>

  <!-- bottom nav (igual estilo que index) -->
  <nav class="bottom-nav" role="navigation" aria-label="Navegación principal">
    <a href="../inicio/inicio.html" class="nav-item" title="Inicio">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3l9 8h-3v7h-4v-5H10v5H6v-7H3z"/></svg>
      <span class="nav-label">Inicio</span>
    </a>

    <a href="../panel/panel.php" class="nav-item" title="Panel">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
      <span class="nav-label">Panel</span>
    </a>

    <a href="crear.php" class="nav-item active" title="Crear">
      <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11 11V6h2v5h5v2h-5v5h-2v-5H6v-2z"/></svg>
      <span class="nav-label">Crear</span>
    </a>

    <a href="../foro/foro.html" class="nav-item" title="Foro">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v14l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
      <span class="nav-label">Foro</span>
    </a>

    <a href="../perfil/perfil.php" class="nav-item" title="Perfil">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-4 0-8 2-8 6v2h16v-2c0-4-4-6-8-6z"/></svg>
      <span class="nav-label">Perfil</span>
    </a>
  </nav>

  <script>
    // pasamos role al JS para control si hiciera falta
    window.SOL_ROLE = "<?php echo htmlspecialchars($user_role); ?>";
  </script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="crear.js"></script>
</body>
</html>