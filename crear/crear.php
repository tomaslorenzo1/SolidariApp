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

// Manejo POST (crear)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // campo "tipo" indica 'campana' o 'centro'
    $tipo = $_POST['tipo'] ?? '';

    // datos comunes
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categorias = trim($_POST['categorias_hidden'] ?? ''); // coma-sep (desde JS)
    $horario = trim($_POST['horario'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    $telefono = trim($_POST['telefono'] ?? '');
    // meta permitimos texto (ej: "1000 frazadas") o num
    $meta = trim($_POST['meta'] ?? '');

    // Sanitizar (básico)
    // (en produccion, validar con más cuidado)
    if ($tipo === 'campana') {
        $fecha_inicio = $_POST['fecha_inicio'] ?: null;
        $fecha_fin = $_POST['fecha_fin'] ?: null;

        // Insert into campañas
        $sql = "INSERT INTO `campañas` 
            (titulo, descripcion, lat, lng, categorias, horario, fecha_inicio, fecha_fin, meta, creador_id, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $mensaje = "Error en la base de datos: " . $conn->error;
            $tipo_msg = 'error';
        } else {
            // forzar tipos: lat/lng a float o null
            $lat_db = is_null($lat) || $lat === '' ? null : floatval($lat);
            $lng_db = is_null($lng) || $lng === '' ? null : floatval($lng);
            $creador = $uid;
            // bind: s = string, d = double, i = int
            $stmt->bind_param(
                "ssddsssssi",
                $titulo,
                $descripcion,
                $lat_db,
                $lng_db,
                $categorias,
                $horario,
                $fecha_inicio,
                $fecha_fin,
                $meta,
                $creador
            );
            if ($stmt->execute()) {
                $last_id = $stmt->insert_id;
                $mensaje = "Campaña creada correctamente. Quedará en estado PENDIENTE hasta su aprobación.";
                $tipo_msg = 'success';

                // manejo de imágenes: guardarlas en /uploads/campanas/
                if (!empty($_FILES['imagenes']) && count($_FILES['imagenes']['name']) > 0) {
                    $saved = [];
                    $uploadDir = __DIR__ . '/../uploads/campanas/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    for ($i = 0; $i < count($_FILES['imagenes']['name']); $i++) {
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

            } else {
                $mensaje = "Error al guardar la campaña: " . $stmt->error;
                $tipo_msg = 'error';
            }
            $stmt->close();
        }

    } elseif ($tipo === 'centro') {
        // campos propios centro
        $nombre = $titulo;
        // Insert centros_donacion
        $sql = "INSERT INTO `centros_donacion` 
            (nombre, descripcion, direccion, lat, lng, categorias, horario, creador_id, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $mensaje = "Error en la base de datos: " . $conn->error;
            $tipo_msg = 'error';
        } else {
            $lat_db = is_null($lat) || $lat === '' ? null : floatval($lat);
            $lng_db = is_null($lng) || $lng === '' ? null : floatval($lng);
            $creador = $uid;
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

                // manejo de imágenes: guardarlas en /uploads/centros/
                if (!empty($_FILES['imagenes']) && count($_FILES['imagenes']['name']) > 0) {
                    $saved = [];
                    $uploadDir = __DIR__ . '/../uploads/centros/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    for ($i = 0; $i < count($_FILES['imagenes']['name']); $i++) {
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
                $mensaje = "Error al guardar el centro: " . $stmt->error;
                $tipo_msg = 'error';
            }
            $stmt->close();
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
      <?php endif; ?>

      <!-- Tabs: elegir tipo -->
      <div class="tabs" role="tablist">
        <button id="tabCamp" class="tab active" type="button" role="tab" aria-selected="true">Crear campaña / colecta</button>
        <button id="tabCentro" class="tab" type="button" role="tab" aria-selected="false">Crear centro de donación</button>
      </div>

      <!-- FORM: Campaña -->
      <form id="formCampana" class="form" method="POST" enctype="multipart/form-data" action="crear.php">
        <input type="hidden" name="tipo" value="campana">

        <!-- IMAGENES al principio -->
        <label>Fotos (opcional)</label>
        <div class="file-picker">
          <label class="file-btn" for="imgsCamp">Seleccionar imágenes</label>
          <input type="file" id="imgsCamp" name="imagenes[]" accept="image/*" multiple style="display:none">
          <div class="file-note">Máx 6 imágenes • JPG, PNG, WEBP • 2MB c/u</div>
        </div>
        <div id="previewCamp" class="preview-grid"></div>

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
            <input type="range" id="metaRange" min="0" max="99999999" step="1" value="0">
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

        <label>Horario</label>
        <input name="horario" placeholder="Ej: Lun-Vie 09:00-18:00">

        <label>Dirección (buscador)</label>
        <input id="addressCamp" name="direccion" placeholder="Ingresá la dirección y seleccioná una opción" autocomplete="off" required>
        <div id="addrResultsCamp" class="addr-results hidden" aria-hidden="true"></div>
        <input type="hidden" name="lat" id="latCamp">
        <input type="hidden" name="lng" id="lngCamp">

        <label>Teléfono de contacto</label>
        <input name="telefono" placeholder="+5411... (solo números y prefijo)">

        <div style="margin-top:14px;">
          <button class="btn-primary" type="submit">Crear campaña</button>
        </div>
      </form>

      <!-- FORM: Centro -->
      <form id="formCentro" class="form hidden" method="POST" enctype="multipart/form-data" action="crear.php">
        <input type="hidden" name="tipo" value="centro">

        <!-- IMAGENES al principio -->
        <label>Fotos del lugar (opcional)</label>
        <div class="file-picker">
          <label class="file-btn" for="imgsCentro">Seleccionar imágenes</label>
          <input type="file" id="imgsCentro" name="imagenes[]" accept="image/*" multiple style="display:none">
          <div class="file-note">Máx 6 imágenes • JPG, PNG, WEBP • 2MB c/u</div>
        </div>
        <div id="previewCentro" class="preview-grid"></div>

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

        <label>Dirección (buscador)</label>
        <input id="addressCentro" name="direccion" placeholder="Ingresá la dirección y seleccioná una opción" autocomplete="off" required>
        <div id="addrResultsCentro" class="addr-results hidden" aria-hidden="true"></div>
        <input type="hidden" name="lat" id="latCentro">
        <input type="hidden" name="lng" id="lngCentro">

        <label>Horario</label>
        <input name="horario" placeholder="Ej: Lun-Vie 09:00-18:00">

        <label>Teléfono de contacto</label>
        <input name="telefono" placeholder="+5411... (solo números y prefijo)">

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

    <a href="../panel/panel.html" class="nav-item" title="Panel">
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

  <script src="crear.js"></script>
</body>
</html>