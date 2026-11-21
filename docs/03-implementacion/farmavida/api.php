<?php
/* ===========================================================
   FARMAVIDA - api.php
   TODOS los endpoints que antes eran archivos sueltos llamados
   por fetch() desde script.js ahora viven aquí, en uno solo.
   Se identifican con el campo "accion" del JSON que se envía:

     registrar_cliente     (antes: guardar_cliente.php)
     guardar_conversacion  (antes: guardar_conversacion.php)
     carrito_agregar       (antes: carrito_agregar.php)
     carrito_quitar        (antes: carrito_quitar.php)
     carrito_eliminar      (antes: carrito_eliminar.php)
     carrito_datos         (antes: carrito_datos.php)
     carrito_confirmar     (antes: carrito_confirmar.php)

   script.js debe mandar, en el body JSON de cada fetch(),
   { accion: '...', ...resto de los datos }.

   ---------------------------------------------------------
   IDIOMA DE LOS MENSAJES:
   idioma.js guarda una cookie "farmavida_idioma" (es/en) cada
   vez que el usuario cambia de idioma con el botón 🌐. Aquí la
   leemos para responder los mensajes ('mensaje' => ...) en el
   idioma correcto. La función t($es, $en) es un traductor
   rapidito: recibe el texto en español y en inglés, y devuelve
   el que corresponda.
   =========================================================== */

header('Content-Type: application/json');
require 'config.php';
require 'automatizacion.php';
require 'servicios/compra_confirmada.php';
require 'servicios/alertas.php';

$idioma = ((($_COOKIE['farmavida_idioma'] ?? 'es')) === 'en') ? 'en' : 'es';

function t($es, $en) {
    global $idioma;
    return $idioma === 'en' ? $en : $es;
}

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$accion = $datos['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    /* =======================================================
       REGISTRO DE CLIENTE (antes guardar_cliente.php)
       ======================================================= */
    case 'registrar_cliente': {
        if (empty($datos['password'])) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Faltan datos, incluida la contraseña.', 'Missing data, including the password.')]);
            break;
        }

        // El correo debe ser único
        $stmtCheck = $conexion->prepare("SELECT id FROM clientes WHERE correo = ?");
        $stmtCheck->bind_param('s', $datos['correo']);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->fetch_assoc()) {
            http_response_code(409);
            echo json_encode(['error' => true, 'mensaje' => t('Ese correo ya está registrado. Inicia sesión en su lugar.', 'That email is already registered. Please log in instead.')]);
            $stmtCheck->close();
            break;
        }
        $stmtCheck->close();

        $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);

        $stmt = $conexion->prepare(
            "INSERT INTO clientes (nombre, ciudad, telefono, correo, password, direccion, documento, condicion_salud, eps)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sssssssss',
            $datos['nombre'], $datos['ciudad'], $datos['telefono'], $datos['correo'],
            $passwordHash, $datos['direccion'], $datos['documento'],
            $datos['condicionSalud'], $datos['eps']
        );

        if ($stmt->execute()) {
            $_SESSION['cliente_id'] = $conexion->insert_id;
            $_SESSION['cliente_nombre'] = $datos['nombre'];
            echo json_encode(['error' => false, 'mensaje' => t('Cliente registrado correctamente.', 'Customer registered successfully.')]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => true, 'mensaje' => t('Error al guardar: ', 'Error saving: ') . $stmt->error]);
        }
        $stmt->close();
        break;
    }

    /* =======================================================
       GUARDAR CONVERSACIÓN DEL CHAT (antes guardar_conversacion.php)
       ======================================================= */
    case 'guardar_conversacion': {
        if (empty($datos['mensajes']) || !is_array($datos['mensajes'])) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('No se recibió ninguna conversación válida.', 'No valid conversation was received.')]);
            break;
        }

        $inicioMysql = date('Y-m-d H:i:s', strtotime($datos['inicio'] ?? 'now'));
        $finMysql = date('Y-m-d H:i:s', strtotime($datos['fin'] ?? 'now'));
        $calificacion = (isset($datos['calificacion']) && $datos['calificacion'] !== null && $datos['calificacion'] !== '')
            ? (int)$datos['calificacion']
            : null;

        $stmt = $conexion->prepare("INSERT INTO conversaciones (fecha_inicio, fecha_fin, calificacion) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $inicioMysql, $finMysql, $calificacion);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => true, 'mensaje' => t('Error al guardar la conversación: ', 'Error saving the conversation: ') . $stmt->error]);
            $stmt->close();
            break;
        }
        $conversacionId = $conexion->insert_id;
        $stmt->close();

        $stmtMsg = $conexion->prepare("INSERT INTO mensajes (conversacion_id, tipo, texto, hora) VALUES (?, ?, ?, ?)");
        foreach ($datos['mensajes'] as $m) {
            $tipo = (isset($m['tipo']) && $m['tipo'] === 'user') ? 'user' : 'bot';
            $texto = $m['texto'] ?? '';
            $hora = isset($m['hora']) ? date('Y-m-d H:i:s', strtotime($m['hora'])) : date('Y-m-d H:i:s');
            $stmtMsg->bind_param('isss', $conversacionId, $tipo, $texto, $hora);
            $stmtMsg->execute();
        }
        $stmtMsg->close();

        // --- Flujo 5: alerta por Telegram si la calificación es baja (1 a 3) ---
        if ($calificacion !== null && $calificacion <= 3) {
            $transcripcion = '';
            foreach ($datos['mensajes'] as $m) {
                $quien = (($m['tipo'] ?? '') === 'user') ? 'Cliente' : 'Bot';
                $transcripcion .= $quien . ': ' . mb_substr(trim($m['texto'] ?? ''), 0, 300) . "\n";
            }
            $datosAlerta = [
                'conversacion_id' => $conversacionId,
                'calificacion'    => $calificacion,
                'fecha'           => $finMysql,
                'total_mensajes'  => count($datos['mensajes']),
                'transcripcion'   => mb_substr($transcripcion, 0, 2500),
            ];
            // Flujo 5 (propio, sin n8n): se envía DESPUÉS de responderle al cliente
            despuesDeResponder(function () use ($datosAlerta) {
                flujoCalificacionBaja($datosAlerta);
            });
        }

        echo json_encode(['error' => false, 'mensaje' => t('Conversación guardada correctamente.', 'Conversation saved successfully.')]);
        break;
    }

    /* =======================================================
       CARRITO: agregar (antes carrito_agregar.php)
       ======================================================= */
    case 'carrito_agregar': {
        if (!clienteLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como cliente para agregar productos al carrito.', 'You must log in as a customer to add products to the cart.')]);
            break;
        }
        $productoId = isset($datos['producto_id']) ? (int)$datos['producto_id'] : 0;
        if ($productoId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Producto inválido.', 'Invalid product.')]);
            break;
        }

        $stmt = $conexion->prepare("SELECT id, nombre, estado, cantidad_disponible FROM productos WHERE id = ?");
        $stmt->bind_param('i', $productoId);
        $stmt->execute();
        $producto = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$producto) {
            http_response_code(404);
            echo json_encode(['error' => true, 'mensaje' => t('Ese producto no existe.', 'That product does not exist.')]);
            break;
        }

        if ($producto['estado'] !== 'activo') {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Ese producto ya no está disponible.', 'That product is no longer available.')]);
            break;
        }

        if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
            $_SESSION['carrito'] = [];
        }

        $cantidadEnCarrito = $_SESSION['carrito'][$productoId] ?? 0;
        $stockDisponible = (int)$producto['cantidad_disponible'];

        if ($cantidadEnCarrito + 1 > $stockDisponible) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'mensaje' => $stockDisponible > 0
                    ? t(
                        "Ya tienes en tu carrito todo el stock disponible de \"{$producto['nombre']}\" ({$stockDisponible} unidad(es)).",
                        "You already have all the available stock of \"{$producto['nombre']}\" ({$stockDisponible} unit(s)) in your cart."
                      )
                    : t(
                        "\"{$producto['nombre']}\" no tiene unidades disponibles en este momento.",
                        "\"{$producto['nombre']}\" has no units available right now."
                      )
            ]);
            break;
        }

        $_SESSION['carrito'][$productoId] = $cantidadEnCarrito + 1;

        echo json_encode([
            'error' => false,
            'mensaje' => t('Producto añadido al carrito.', 'Product added to cart.'),
            'totalItems' => array_sum($_SESSION['carrito'])
        ]);
        break;
    }

    /* =======================================================
       CARRITO: quitar 1 unidad (antes carrito_quitar.php)
       ======================================================= */
    case 'carrito_quitar': {
        if (!clienteLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como cliente.', 'You must log in as a customer.')]);
            break;
        }
        $productoId = isset($datos['producto_id']) ? (int)$datos['producto_id'] : 0;
        if ($productoId <= 0 || empty($_SESSION['carrito'][$productoId])) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Ese producto no está en tu carrito.', 'That product is not in your cart.')]);
            break;
        }
        $_SESSION['carrito'][$productoId]--;
        if ($_SESSION['carrito'][$productoId] <= 0) {
            unset($_SESSION['carrito'][$productoId]);
        }
        echo json_encode(['error' => false, 'mensaje' => t('Carrito actualizado.', 'Cart updated.')]);
        break;
    }

    /* =======================================================
       CARRITO: eliminar línea completa (antes carrito_eliminar.php)
       ======================================================= */
    case 'carrito_eliminar': {
        if (!clienteLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como cliente.', 'You must log in as a customer.')]);
            break;
        }
        $productoId = isset($datos['producto_id']) ? (int)$datos['producto_id'] : 0;
        if ($productoId <= 0 || !isset($_SESSION['carrito'][$productoId])) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Ese producto no está en tu carrito.', 'That product is not in your cart.')]);
            break;
        }
        unset($_SESSION['carrito'][$productoId]);
        echo json_encode(['error' => false, 'mensaje' => t('Producto eliminado del carrito.', 'Product removed from cart.')]);
        break;
    }

    /* =======================================================
       CARRITO: leer contenido (antes carrito_datos.php)
       ======================================================= */
    case 'carrito_datos': {
        if (!clienteLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como cliente.', 'You must log in as a customer.')]);
            break;
        }
        $carritoSesion = $_SESSION['carrito'] ?? [];
        $items = [];
        $total = 0;

        foreach ($carritoSesion as $productoId => $cantidad) {
            $stmt = $conexion->prepare("SELECT id, nombre, precio, icono FROM productos WHERE id = ?");
            $stmt->bind_param('i', $productoId);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$producto) {
                unset($_SESSION['carrito'][$productoId]);
                continue;
            }

            $subtotal = $producto['precio'] * $cantidad;
            $total += $subtotal;

            $items[] = [
                'producto_id' => (int)$producto['id'],
                'nombre' => $producto['nombre'],
                'icono' => $producto['icono'],
                'precio' => (float)$producto['precio'],
                'cantidad' => (int)$cantidad,
                'subtotal' => (float)$subtotal
            ];
        }

        echo json_encode(['error' => false, 'items' => $items, 'total' => $total]);
        break;
    }

    /* =======================================================
       CARRITO: confirmar compra (antes carrito_confirmar.php)
       ======================================================= */
            case 'carrito_confirmar': {
        if (!clienteLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como cliente para comprar.', 'You must log in as a customer to buy.')]);
            break;
        }
        $carritoSesion = $_SESSION['carrito'] ?? [];
        if (empty($carritoSesion)) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Tu carrito está vacío.', 'Your cart is empty.')]);
            break;
        }

        $stmtProd = $conexion->prepare("SELECT id, nombre, precio, estado, cantidad_disponible FROM productos WHERE id = ?");
        $stmtPedido = $conexion->prepare("INSERT INTO pedidos (cliente_id, producto_id, precio) VALUES (?, ?, ?)");
        // El UPDATE solo descuenta si sigue habiendo stock suficiente en ese instante
        // (evita que dos compras simultáneas dejen el stock en negativo).
        $stmtStock = $conexion->prepare(
            "UPDATE productos SET cantidad_disponible = cantidad_disponible - ?
             WHERE id = ? AND cantidad_disponible >= ?"
        );

        $nombresComprados = [];
        $nombresSinStock = [];
        $huboError = false;

        // --- detalle de la compra y alertas ---
        $detalleCompra = [];
        $totalCompra = 0;
        $alertasStock = [];

        foreach ($carritoSesion as $productoId => $cantidad) {
            $stmtProd->bind_param('i', $productoId);
            $stmtProd->execute();
            $producto = $stmtProd->get_result()->fetch_assoc();

            if (!$producto || $producto['estado'] !== 'activo') {
                unset($_SESSION['carrito'][$productoId]);
                continue;
            }

            // Revalidamos el stock real al momento de confirmar, por si cambió
            // desde que se agregó al carrito (otra compra, o el admin lo editó).
            if ((int)$producto['cantidad_disponible'] < $cantidad) {
                $nombresSinStock[] = $producto['nombre'];
                continue;
            }

            $stmtStock->bind_param('iii', $cantidad, $productoId, $cantidad);
            $stmtStock->execute();
            if ($stmtStock->affected_rows === 0) {
                // Otra compra se adelantó y agotó el stock justo antes que esta.
                $nombresSinStock[] = $producto['nombre'];
                continue;
            }

            // --- si quedan pocas unidades, preparamos la alerta ---
            $stockRestante = (int)$producto['cantidad_disponible'] - (int)$cantidad;
            if ($stockRestante <= 5) {
                $alertasStock[] = [
                    'producto' => $producto['nombre'],
                    'stock'    => $stockRestante
                ];
            }

            for ($i = 0; $i < $cantidad; $i++) {
                $stmtPedido->bind_param('iid', $_SESSION['cliente_id'], $productoId, $producto['precio']);
                if (!$stmtPedido->execute()) {
                    $huboError = true;
                }
            }

            // --- guardamos este producto en el detalle ---
            $subtotal = $producto['precio'] * $cantidad;
            $totalCompra += $subtotal;
            $detalleCompra[] = [
                'nombre'   => $producto['nombre'],
                'cantidad' => $cantidad,
                'precio'   => (float)$producto['precio'],
                'subtotal' => (float)$subtotal
            ];

            $nombresComprados[] = $producto['nombre'] . ($cantidad > 1 ? " (x{$cantidad})" : '');
            // Ya se descontó del inventario y se procesó: lo sacamos del carrito.
            unset($_SESSION['carrito'][$productoId]);
        }

        $stmtProd->close();
        $stmtPedido->close();
        $stmtStock->close();

        if ($huboError) {
            http_response_code(500);
            echo json_encode(['error' => true, 'mensaje' => t('Hubo un problema registrando algunos productos de tu compra.', 'There was a problem registering some products from your purchase.')]);
            break;
        }

        if (!empty($nombresSinStock) && empty($nombresComprados)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'mensaje' => t(
                    'No hay suficiente stock disponible para: ' . implode(', ', $nombresSinStock) . '. Ajusta las cantidades en tu carrito.',
                    'There is not enough stock available for: ' . implode(', ', $nombresSinStock) . '. Adjust the quantities in your cart.'
                )
            ]);
            break;
        }

        $mensajeFinal = empty($nombresComprados)
            ? t('No había productos válidos en tu carrito.', 'There were no valid products in your cart.')
            : t('¡Compra confirmada! ', 'Purchase confirmed! ') . implode(', ', $nombresComprados) . '.';

        if (!empty($nombresSinStock)) {
            $mensajeFinal .= t(
                ' (Sin stock suficiente, quedaron en tu carrito: ' . implode(', ', $nombresSinStock) . '.)',
                ' (Not enough stock, these stayed in your cart: ' . implode(', ', $nombresSinStock) . '.)'
            );
        }

        // --- Flujo 1: correo de confirmación de compra ---
        if (!empty($detalleCompra)) {
            $stmtCli = $conexion->prepare("SELECT nombre, correo FROM clientes WHERE id = ?");
            $stmtCli->bind_param('i', $_SESSION['cliente_id']);
            $stmtCli->execute();
            $cli = $stmtCli->get_result()->fetch_assoc();
            $stmtCli->close();

            if ($cli) {
                // Automatización 1 (propia, sin n8n): el correo se envía DESPUÉS de
                // responderle al cliente, así no espera a Gmail.
                $datosCorreo = [
                    'nombre' => $cli['nombre'],
                    'correo' => $cli['correo'],
                    'items'  => $detalleCompra,
                    'total'  => (float)$totalCompra,
                    'idioma' => $idioma,
                    'fecha'  => date('d/m/Y H:i')
                ];
                despuesDeResponder(function () use ($datosCorreo) {
                    flujoCompraConfirmada($datosCorreo);
                });
            }
        }

        // --- Flujo 2 (propio, sin n8n): alerta de stock bajo por Telegram ---
        // Un solo mensaje con todos los productos de la compra que quedaron con poco stock.
        if (!empty($alertasStock)) {
            $alertasCopia = $alertasStock;
            despuesDeResponder(function () use ($alertasCopia) {
                flujoStockBajo($alertasCopia);
            });
        }

        // --- Flujo 3 (propio, sin n8n): informe de productos agotados por Telegram ---
        $seAgoto = false;
        foreach ($alertasStock as $a) {
            if ($a['stock'] <= 0) { $seAgoto = true; break; }
        }

        if ($seAgoto) {
            // precio > 0 excluye "Medicamento formulado", que siempre tiene stock 0
            $resAgotados = $conexion->query(
                "SELECT nombre FROM productos
                 WHERE cantidad_disponible <= 0 AND estado = 'activo' AND precio > 0
                 ORDER BY nombre"
            );
            $agotados = [];
            while ($fila = $resAgotados->fetch_assoc()) {
                $agotados[] = $fila['nombre'];
            }

            $datosAgotados = [
                'total'     => count($agotados),
                'productos' => $agotados,
                'fecha'     => date('d/m/Y H:i')
            ];
            despuesDeResponder(function () use ($datosAgotados) {
                flujoInformeAgotados($datosAgotados);
            });
        }

        echo json_encode([
            'error' => false,
            'mensaje' => $mensajeFinal
        ]);
        break;
    }

    /* =======================================================
       PRODUCTOS: listar (uso del panel de administración)
       Muestra TODOS los productos (activos e inactivos) con
       todos sus campos, para poder editarlos.
       ======================================================= */
    case 'producto_listar': {
        if (!adminLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como administrador.', 'You must log in as an administrator.')]);
            break;
        }

        $productos = [];
        $res = $conexion->query(
            "SELECT id, nombre, descripcion, categoria, precio, cantidad_disponible, estado, icono, imagen
             FROM productos ORDER BY id DESC"
        );
        while ($p = $res->fetch_assoc()) {
            $p['id'] = (int)$p['id'];
            $p['precio'] = (float)$p['precio'];
            $p['cantidad_disponible'] = (int)$p['cantidad_disponible'];
            $productos[] = $p;
        }

        echo json_encode(['error' => false, 'productos' => $productos]);
        break;
    }

    /* =======================================================
       PRODUCTOS: crear
       ======================================================= */
    case 'producto_crear': {
        if (!adminLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como administrador.', 'You must log in as an administrator.')]);
            break;
        }

        $nombre = trim($datos['nombre'] ?? '');
        $descripcion = trim($datos['descripcion'] ?? '');
        $categoria = trim($datos['categoria'] ?? '');
        $precio = isset($datos['precio']) ? (float)$datos['precio'] : -1;
        $cantidad = isset($datos['cantidad_disponible']) ? (int)$datos['cantidad_disponible'] : -1;
        $icono = trim($datos['icono'] ?? '💊');
        $imagen = trim($datos['imagen'] ?? '') ?: null;
        $estado = ($datos['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';

        if ($nombre === '' || $descripcion === '' || $categoria === '' || $precio < 0 || $cantidad < 0) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Faltan datos o son inválidos (nombre, descripción, categoría, precio y cantidad son obligatorios).', 'Missing or invalid data (name, description, category, price and quantity are required).')]);
            break;
        }

        $stmt = $conexion->prepare(
            "INSERT INTO productos (nombre, descripcion, categoria, precio, cantidad_disponible, estado, icono, imagen)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssdisss', $nombre, $descripcion, $categoria, $precio, $cantidad, $estado, $icono, $imagen);
        // Nota: 'sssdisss' tiene 8 letras para 8 parámetros: s,s,s,d,i,s,s,s

        if ($stmt->execute()) {
            echo json_encode(['error' => false, 'mensaje' => t('Producto creado correctamente.', 'Product created successfully.'), 'id' => $conexion->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => true, 'mensaje' => t('Error al crear el producto: ', 'Error creating the product: ') . $stmt->error]);
        }
        $stmt->close();
        break;
    }

    /* =======================================================
       PRODUCTOS: editar
       ======================================================= */
    case 'producto_editar': {
        if (!adminLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como administrador.', 'You must log in as an administrator.')]);
            break;
        }

        $id = isset($datos['id']) ? (int)$datos['id'] : 0;
        $nombre = trim($datos['nombre'] ?? '');
        $descripcion = trim($datos['descripcion'] ?? '');
        $categoria = trim($datos['categoria'] ?? '');
        $precio = isset($datos['precio']) ? (float)$datos['precio'] : -1;
        $cantidad = isset($datos['cantidad_disponible']) ? (int)$datos['cantidad_disponible'] : -1;
        $icono = trim($datos['icono'] ?? '💊');
        $imagen = trim($datos['imagen'] ?? '') ?: null;
        $estado = ($datos['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';

        if ($id <= 0 || $nombre === '' || $descripcion === '' || $categoria === '' || $precio < 0 || $cantidad < 0) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Faltan datos o son inválidos.', 'Missing or invalid data.')]);
            break;
        }

        $stmt = $conexion->prepare(
            "UPDATE productos
             SET nombre = ?, descripcion = ?, categoria = ?, precio = ?, cantidad_disponible = ?,
                 estado = ?, icono = ?, imagen = ?
             WHERE id = ?"
        );
        $stmt->bind_param('sssdisssi', $nombre, $descripcion, $categoria, $precio, $cantidad, $estado, $icono, $imagen, $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows === 0) {
                // No es necesariamente un error: puede que no cambiara ningún valor.
                echo json_encode(['error' => false, 'mensaje' => t('Producto actualizado (sin cambios detectados).', 'Product updated (no changes detected).')]);
            } else {
                echo json_encode(['error' => false, 'mensaje' => t('Producto actualizado correctamente.', 'Product updated successfully.')]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => true, 'mensaje' => t('Error al actualizar el producto: ', 'Error updating the product: ') . $stmt->error]);
        }
        $stmt->close();
        break;
    }

    /* =======================================================
       PRODUCTOS: eliminar
       Eliminación LÓGICA (soft delete): el producto pasa a
       estado 'inactivo' y deja de mostrarse en el catálogo,
       pero no se borra de la base de datos para no romper el
       historial de pedidos que ya lo referencian (tabla pedidos
       tiene una FK hacia productos).
       ======================================================= */
    case 'producto_eliminar': {
        if (!adminLogueado()) {
            http_response_code(403);
            echo json_encode(['error' => true, 'mensaje' => t('Debes iniciar sesión como administrador.', 'You must log in as an administrator.')]);
            break;
        }

        $id = isset($datos['id']) ? (int)$datos['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => true, 'mensaje' => t('Producto inválido.', 'Invalid product.')]);
            break;
        }

        $stmt = $conexion->prepare("UPDATE productos SET estado = 'inactivo' WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            echo json_encode(['error' => false, 'mensaje' => t('Producto eliminado (marcado como inactivo).', 'Product deleted (marked as inactive).')]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => true, 'mensaje' => t('Error al eliminar el producto: ', 'Error deleting the product: ') . $stmt->error]);
        }
        $stmt->close();
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error' => true, 'mensaje' => t('Acción no reconocida: ', 'Unrecognized action: ') . htmlspecialchars($accion)]);
}

// (la conexión se cierra sola al terminar; las tareas diferidas todavía la usan para el log)
