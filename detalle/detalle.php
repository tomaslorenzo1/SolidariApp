<?php
// detalle/detalle.php
require_once("../db.php"); // tu archivo de conexión, ajusta la ruta si es diferente

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
$id   = isset($_GET['id']) ? intval($_GET['id']) : null;
$tipoNorm = ($tipo === 'campana' || $tipo === 'campaña') ? 'campana' : ($tipo === 'centro' ? 'centro' : null);

if (!$tipo || !$id) {
    echo "<h2>Parámetros inválidos</h2>";
    exit;
}

if ($tipoNorm === "campana") {
    $sql = "SELECT * FROM campañas WHERE id_campaña = ?";
} elseif ($tipoNorm === "centro") {
    $sql = "SELECT * FROM centros_donacion WHERE id_centro = ?";
} else {
    echo "<h2>Tipo inválido</h2>";
    exit;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "<h2>No se encontró el registro</h2>";
    exit;
}

// Convertir imágenes JSON a array
$imagenes = [];
if (!empty($data['imagenes'])) {
    $decoded = json_decode($data['imagenes'], true);
    if (is_array($decoded)) $imagenes = $decoded;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle - <?php echo htmlspecialchars($tipoNorm === 'campana' ? ($data['titulo'] ?? 'Campaña') : ($data['nombre'] ?? 'Centro')); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="detalle.css">
</head>
<body>
    <!-- Header consistente con el resto de la app -->
    <header class="topbar" role="banner">
      <div class="left">
        <img src="../inicio/img/logo_header.png" class="brand-logo" alt="SolidariApp">
        <div class="brand-name">SolidariApp</div>
      </div>
      <div class="right">
        <button class="icon-btn close-btn" title="Cerrar" onclick="location.href='../inicio/inicio.html'" aria-label="Cerrar detalle">
          <!-- X en SVG -->
          <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="#fff" d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7a1 1 0 0 0-1.41 1.41L10.59 12l-4.9 4.89a1 1 0 1 0 1.41 1.41L12 13.41l4.89 4.9a1 1 0 0 0 1.41-1.41L13.41 12l4.9-4.89a1 1 0 0 0-.01-1.4z"/></svg>
        </button>
      </div>
    </header>

    <main class="content" role="main">
      <!-- Carrusel de imágenes -->
      <section class="hero">
        <div class="carousel" id="carousel" aria-label="Galería de imágenes">
          <button class="c-btn prev" id="cPrev" aria-label="Imagen anterior">&#10094;</button>
          <div class="c-track" id="cTrack">
            <?php if (count($imagenes)):
              foreach ($imagenes as $img): ?>
                <div class="c-slide"><img src="../<?php echo htmlspecialchars($img); ?>" alt="Imagen"></div>
              <?php endforeach; else: ?>
                <div class="c-slide"><img src="../index/img/logo_header.png" alt="Sin imagen"></div>
            <?php endif; ?>
          </div>
          <button class="c-btn next" id="cNext" aria-label="Imagen siguiente">&#10095;</button>
        </div>
      </section>

      <!-- Título -->
      <section class="head">
        <h1 class="title"><?php echo htmlspecialchars($tipoNorm === 'campana' ? ($data['titulo'] ?? 'Campaña') : ($data['nombre'] ?? 'Centro')); ?></h1>
        <!-- Chips de categorías -->
        <?php
          $cats = array_filter(array_map('trim', explode(',', $data['categorias'] ?? '')));
          if (count($cats)):
        ?>
        <div class="chips">
          <?php foreach ($cats as $c): ?>
            <span class="chip"><?php echo htmlspecialchars($c); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

      <!-- Descripción -->
      <section class="card">
        <h2>Descripción</h2>
        <p class="desc"><?php echo nl2br(htmlspecialchars($data['descripcion'] ?? '')); ?></p>
      </section>

      <!-- Meta / Progreso (si aplica) -->
      <?php if ($tipoNorm === 'campana'): ?>
      <section class="card">
        <h2>Meta</h2>
        <div class="meta-row">
          <?php $meta = intval(preg_replace('/[^\d]/','', $data['meta'] ?? '0')); $progreso = 0; ?>
          <div class="progress">
            <div class="bar" style="width: <?php echo min(100, $progreso); ?>%"></div>
          </div>
          <div class="meta-text">
            <span>Progreso: <?php echo $progreso; ?>%</span>
            <span class="sep">•</span>
            <span>Objetivo: <?php echo number_format($meta, 0, ',', '.'); ?></span>
          </div>
        </div>
        <div class="dates">
          <?php if (!empty($data['fecha_inicio'])): ?><div><strong>Inicio:</strong> <?php echo htmlspecialchars($data['fecha_inicio']); ?></div><?php endif; ?>
          <?php if (!empty($data['fecha_fin'])): ?><div><strong>Fin:</strong> <?php echo htmlspecialchars($data['fecha_fin']); ?></div><?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- Información de contacto y ubicación -->
      <section class="card">
        <h2>Información</h2>
        <?php if (!empty($data['horario'])): ?><div class="row"><strong>Horario:</strong> <span><?php echo htmlspecialchars($data['horario']); ?></span></div><?php endif; ?>
        <?php if (!empty($data['direccion'])): ?><div class="row"><strong>Dirección:</strong> <span><?php echo htmlspecialchars($data['direccion']); ?></span></div><?php endif; ?>
        <?php if (!empty($data['lat']) && !empty($data['lng'])): ?>
          <div class="row"><strong>Mapa:</strong> <a class="link" target="_blank" href="https://www.google.com/maps?q=<?php echo $data['lat']; ?>,<?php echo $data['lng']; ?>">Ver ubicación exacta</a></div>
        <?php endif; ?>
      </section>

      <!-- Acción: Donar -->
      <section class="cta">
        <button class="btn-primary" id="btnDonate">Realizar donación</button>
        <p class="note">Para donaciones de dinero, pronto integraremos un QR. Si tu campaña incluye ALIAS o CVU, podrá generarse automáticamente.</p>
      </section>
    </main>

    <!-- Modal Donación -->
    <div class="modal hidden" id="donateModal" role="dialog" aria-hidden="true">
      <div class="modal-card">
        <div class="modal-head">
          <h3>Realizar donación</h3>
          <button class="x" id="mClose" aria-label="Cerrar">×</button>
        </div>
        <div class="modal-body">
          <p>Seleccioná una opción:</p>
          <div class="m-actions">
            <button class="btn-light" id="donarPresencial" aria-pressed="false">Donar presencialmente</button>
            <button class="btn-primary" id="donarDinero" aria-pressed="false">Donar dinero</button>
          </div>

          <div class="qr-area hidden" id="qrArea">
            <!-- Mensaje dinámico -->
            <p id="donMessage" class="don-message" role="status"></p>

            <!-- QR -->
            <img id="qrImg" alt="QR de pago" width="220" height="220" class="hidden" />
            <canvas id="qrCanvas" width="220" height="220" aria-label="QR" class="hidden"></canvas>

            <!-- Datos para donación con dinero (Alias / CVU / Link) -->
            <div class="don-data hidden" id="donData" style="text-align:left;width:100%;max-width:360px">
              <div style="margin-top:6px"><strong>Alias:</strong> <span id="aliasText">-</span></div>
              <div style="margin-top:4px"><strong>CVU:</strong> <span id="cvuText">-</span></div>
              <div id="mpLinkRow" style="margin-top:6px" class="hidden"><strong>Link:</strong> <a id="mpLink" href="#" target="_blank" rel="noopener">Abrir pago</a></div>
            </div>

            <!-- Datos para donación presencial (Contacto / Whatsapp) -->
            <div class="contact-data hidden" id="contactData" style="text-align:left;width:100%;max-width:360px">
              <div style="margin-top:6px"><strong>Contacto:</strong> <span id="contactText">-</span></div>
              <div style="margin-top:6px"><strong>Whatsapp link:</strong> <a id="waLink" href="#" target="_blank" rel="noopener">Abrir WhatsApp</a></div>
            </div>

            <!-- Mensajes específicos según opción -->
            <div class="qr-note hidden" id="qrNoteDinero">
              Si el creador provee un link de pago, generaremos un QR que abre el checkout de Mercado Pago. También verás Alias y CVU para transferencia.
            </div>
            <div class="qr-note hidden" id="qrNotePresencial">
              Si el creador provee un link de whatsapp, generaremos un QR que nos redirige al chat.
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
      window.DETALLE_DATA = {
        tipo: <?php echo json_encode($tipoNorm); ?>,
        titulo: <?php echo json_encode($tipoNorm === 'campana' ? ($data['titulo'] ?? '') : ($data['nombre'] ?? '')); ?>,
        direccion: <?php echo json_encode($data['direccion'] ?? ''); ?>,
        lat: <?php echo json_encode($data['lat'] ?? null); ?>,
        lng: <?php echo json_encode($data['lng'] ?? null); ?>,
        alias_mp: <?php echo json_encode($data['alias_mp'] ?? ''); ?>,
        cvu_mp: <?php echo json_encode($data['cvu_mp'] ?? ''); ?>,
        link_pago_mp: <?php echo json_encode($data['link_pago_mp'] ?? ''); ?>,
        whatsapp_link: <?php echo json_encode($data['whatsapp_link'] ?? ''); ?>,
        telefono_contacto: <?php echo json_encode($data['telefono'] ?? $data['telefono_contacto'] ?? ''); ?>
      };
    </script>
    <script src="detalle.js"></script>
</body>
</html>