<?php
// Inventario IberoTech - CRUD con Bootstrap y diseño responsive
// Ubicación en Laragon: C:\laragon\www\crud-ibero_2\index.php

require_once "conexion.php";

$error = "";

// -------------------------------------------------------------------------
// 1. PROCESAR ACCIONES POST: CREAR, ACTUALIZAR, ELIMINAR Y MODIFICAR STOCK
// -------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    // ELIMINAR PRODUCTO (DELETE)
    if ($accion === "eliminar") {
        $idEliminar = intval($_POST["id"] ?? 0);

        if ($idEliminar > 0) {
            $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
            $stmt->execute([$idEliminar]);
            header("Location: index.php?ok=eliminado");
            exit;
        }
    }

    // SUMAR UNA UNIDAD AL STOCK
    if ($accion === "sumar_stock") {
        $id = intval($_POST["id"] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE productos SET stock = stock + 1 WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: index.php?ok=stock_aumentado");
            exit;
        }
    }

    // RESTAR UNA UNIDAD AL STOCK (SIN PERMITIR VALORES NEGATIVOS)
    if ($accion === "restar_stock") {
        $id = intval($_POST["id"] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE productos SET stock = stock - 1 WHERE id = ? AND stock > 0");
            $stmt->execute([$id]);
            header("Location: index.php?ok=stock_disminuido");
            exit;
        }
    }

    // CREAR O ACTUALIZAR PRODUCTO (INSERT / UPDATE)
    if ($accion === "guardar") {
        $id = intval($_POST["id"] ?? 0);
        $nombre = trim($_POST["nombre"] ?? "");
        $marca = trim($_POST["marca"] ?? "");
        $categoria = trim($_POST["categoria"] ?? "");
        $precio = $_POST["precio"] ?? "";
        $stock = $_POST["stock"] ?? "";

        if ($nombre === "" || $marca === "" || $categoria === "" || $precio === "" || $stock === "") {
            $error = "Todos los campos son obligatorios.";
        } elseif (strlen($nombre) < 3) {
            $error = "El nombre debe tener al menos 3 caracteres.";
        } elseif (!is_numeric($precio) || filter_var($stock, FILTER_VALIDATE_INT) === false || intval($stock) < 0) {
            $error = "El precio debe ser numérico y el stock debe ser un número entero mayor o igual a 0.";
        } elseif (floatval($precio) < 1000) {
            $error = "El precio debe ser mayor o igual a 1000 pesos.";
        } else {
            if ($id > 0) {
                // UPDATE: modificar producto existente
                $sql = "UPDATE productos SET nombre = ?, marca = ?, categoria = ?, precio = ?, stock = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $marca, $categoria, floatval($precio), intval($stock), $id]);
                header("Location: index.php?ok=actualizado");
                exit;
            } else {
                // INSERT: registrar nuevo producto
                $sql = "INSERT INTO productos (nombre, marca, categoria, precio, stock) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $marca, $categoria, floatval($precio), intval($stock)]);
                header("Location: index.php?ok=creado");
                exit;
            }
        }
    }
}

// -------------------------------------------------------------------------
// 2. SI PULSARON "EDITAR", BUSCAR ESE PRODUCTO PARA LLENAR EL FORMULARIO
// -------------------------------------------------------------------------
$productoEditar = [
    "id" => 0,
    "nombre" => "",
    "marca" => "",
    "categoria" => "Accesorios",
    "precio" => "",
    "stock" => 0
];

if (isset($_GET["editar"])) {
    $idEditar = intval($_GET["editar"]);
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$idEditar]);
    $encontrado = $stmt->fetch();

    if ($encontrado) {
        $productoEditar = $encontrado;
    }
}

// Si falla la validación, conservar los datos que la persona escribió.
if ($error !== "" && ($_POST["accion"] ?? "") === "guardar") {
    $productoEditar = [
        "id" => intval($_POST["id"] ?? 0),
        "nombre" => trim($_POST["nombre"] ?? ""),
        "marca" => trim($_POST["marca"] ?? ""),
        "categoria" => trim($_POST["categoria"] ?? "Accesorios"),
        "precio" => $_POST["precio"] ?? "",
        "stock" => $_POST["stock"] ?? 0
    ];
}

// -------------------------------------------------------------------------
// 3. CONSULTAR PRODUCTOS (SELECT) Y BUSCAR POR NOMBRE O CATEGORÍA
// -------------------------------------------------------------------------
$busqueda = trim($_GET["q"] ?? "");

if ($busqueda !== "") {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE nombre LIKE ? OR categoria LIKE ? ORDER BY id DESC");
    $termino = "%" . $busqueda . "%";
    $stmt->execute([$termino, $termino]);
} else {
    $stmt = $pdo->query("SELECT * FROM productos ORDER BY id DESC");
}

$productos = $stmt->fetchAll();

// Calcular totales del inventario
$totalUnidades = 0;
$valorInventario = 0;
foreach ($productos as $p) {
    $totalUnidades += $p["stock"];
    $valorInventario += ($p["precio"] * $p["stock"]);
}

$categorias = ["Computadores", "Monitores", "Accesorios", "Almacenamiento", "Audio", "Redes"];

// Evita mostrar caracteres HTML introducidos en nombres y búsquedas.
function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// Clases Bootstrap para el estado del stock.
function claseStock($stock) {
    if ($stock == 0) return "text-bg-danger";
    if ($stock <= 3) return "text-bg-warning";
    return "text-bg-success";
}

function textoStock($stock) {
    if ($stock == 0) return "Agotado";
    if ($stock <= 3) return "¡Últimas " . $stock . " uds!";
    return $stock . " disp.";
}

$mensajesOk = [
    "creado" => "Producto agregado correctamente.",
    "actualizado" => "Producto actualizado correctamente.",
    "eliminado" => "Producto eliminado correctamente.",
    "stock_aumentado" => "Se agregó una unidad al stock.",
    "stock_disminuido" => "Se procesó la disminución del stock."
];
$ok = $_GET["ok"] ?? "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Inventario IberoTech — PHP, MySQL & Bootstrap</title>

    <!-- Bootstrap 5: estilos para diseño responsive -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">

    <!-- Estilos propios: mantenemos el aspecto azul del proyecto -->
    <style>
        body { background-color: #f4f6f9; }
        .encabezado {
            background: linear-gradient(120deg, #0d2b4a, #1178be);
            color: #fff;
            border-radius: 16px;
        }
        .tarjeta { border: 1px solid #e7edf4; border-radius: 14px; }
        .indicador { border: 1px solid #e7edf4; border-radius: 14px; }
        .indicador .valor { font-size: 1.35rem; font-weight: 700; }
        .tabla-productos { font-size: 0.88rem; }
        .tabla-productos th { white-space: nowrap; }
        .acciones { display: flex; flex-wrap: wrap; gap: 5px; }
        .acciones form { margin: 0; }
        .boton-accion { min-width: 38px; }
        .nombre-producto { overflow-wrap: anywhere; }
        .min-w-0 { min-width: 0; }
        @media (max-width: 575.98px) {
            .encabezado h1 { font-size: 1.35rem; }
            .encabezado { border-radius: 12px; }
            .indicador .valor { font-size: 1.2rem; }
        }
        /* Botón principal: Agregar al inventario */
        .btn-primary {
            --bs-btn-bg: #1178be;
            --bs-btn-border-color: #1178be;
            --bs-btn-hover-bg: #0d649e;
            --bs-btn-hover-border-color: #0d649e;
        }

        /* Botón Buscar: azul oscuro */
        form[method="GET"] .btn-primary {
            --bs-btn-bg: #0d2b4a;
            --bs-btn-border-color: #0d2b4a;
            --bs-btn-hover-bg: #17476f;
            --bs-btn-hover-border-color: #17476f;
        }

        /* Botón Editar: azul claro */
        .btn-outline-primary {
            --bs-btn-color: #1178be;
            --bs-btn-bg: #eef6ff;
            --bs-btn-border-color: #b6d8f2;
            --bs-btn-hover-color: #1178be;
            --bs-btn-hover-bg: #dcecff;
            --bs-btn-hover-border-color: #b6d8f2;
        }

        /* Botón Borrar: rojo claro */
        .btn-outline-danger {
            --bs-btn-color: #b91c1c;
            --bs-btn-bg: #fef2f2;
            --bs-btn-border-color: #fecaca;
            --bs-btn-hover-color: #b91c1c;
            --bs-btn-hover-bg: #fee2e2;
            --bs-btn-hover-border-color: #fecaca;
        }

        /* Botones +1, -1 y Cancelar: grises */
        .btn-outline-success, .btn-outline-secondary {
            --bs-btn-color: #1a1a2e;
            --bs-btn-bg: #e4e7ec;
            --bs-btn-border-color: #e4e7ec;
            --bs-btn-hover-color: #1a1a2e;
            --bs-btn-hover-bg: #d4d8de;
            --bs-btn-hover-border-color: #d4d8de;
        }

        /* Estados del inventario */

        /* Stock disponible: verde */
        .badge.text-bg-success {
            background-color: #dcfce7 !important;
            color: #166534 !important;
        }

        /* Stock bajo: naranja */
        .badge.text-bg-warning {
            background-color: #ffedd5 !important;
            color: #c2410c !important;
        }

        /* Stock agotado: rojo */
        .badge.text-bg-danger {
            background-color: #fee2e2 !important;
            color: #991b1b !important;
        }

        /* Alertas de operaciones */
        .alert-success {
            --bs-alert-bg: #ecfdf5;
            --bs-alert-color: #065f46;
            --bs-alert-border-color: #a7f3d0;
        }

        .alert-danger {
            --bs-alert-bg: #fef2f2;
            --bs-alert-color: #991b1b;
            --bs-alert-border-color: #fecaca;
        }

        /* Tabla de productos */
        .tabla-productos {
            --bs-table-color: #1a1a2e;
            --bs-table-border-color: #e4e7ec;
            --bs-table-striped-bg: transparent;
            --bs-table-hover-bg: transparent;
        }

        .tabla-productos thead {
            --bs-table-bg: #f8fafc;
        }
        /* Tamaño del texto de las etiquetas de stock */
        .badge {
            font-size: 0.75rem;
        }

/* Columna Producto */
.tabla-productos th:nth-child(2),
.tabla-productos td:nth-child(2) {
    min-width: 150px;
}

/* Columna Stock */
.tabla-productos th:nth-child(6),
.tabla-productos td:nth-child(6) {
    padding-left: 5px;
    padding-right: 5px;
}

/* Botones de acciones en una sola fila */
.acciones {
    display: flex;
    flex-wrap: nowrap;
    gap: 5px;
    white-space: nowrap;
}
    </style>
</head>
<body>
    <main class="container-xl py-3 py-md-4">

        <!-- Encabezado -->
        <header class="encabezado p-3 p-md-4 mb-4 shadow-sm">
            <h1 class="h3 fw-bold mb-1">Inventario IberoTech — Aplicación CRUD (PHP + MySQL + Bootstrap)</h1>
        </header>

        <!-- Alertas de operaciones -->
        <?php if (isset($mensajesOk[$ok])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= escapar($mensajesOk[$ok]) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger" role="alert"><?= escapar($error) ?></div>
        <?php endif; ?>

        <!-- Indicadores: una columna en celular y tres desde tablet -->
        <section class="row row-cols-1 row-cols-md-3 g-3 mb-4" aria-label="Resumen del inventario">
            <div class="col">
                <div class="indicador bg-white p-3 h-100 shadow-sm">
                    <div class="text-secondary small mb-1">Productos listados</div>
                    <div class="valor"><?= count($productos) ?></div>
                </div>
            </div>
            <div class="col">
                <div class="indicador bg-white p-3 h-100 shadow-sm">
                    <div class="text-secondary small mb-1">Unidades en stock</div>
                    <div class="valor"><?= intval($totalUnidades) ?> uds.</div>
                </div>
            </div>
            <div class="col">
                <div class="indicador bg-white p-3 h-100 shadow-sm">
                    <div class="text-secondary small mb-1">Valor del inventario</div>
                    <div class="valor">$<?= number_format($valorInventario, 0, ",", ".") ?></div>
                </div>
            </div>
        </section>

        <!-- Bootstrap Grid: formulario e inventario se apilan en celular -->
        <div class="row g-4 align-items-start">
            <!-- Formulario para crear y editar -->
            <section class="col-12 col-xl-4" aria-label="Formulario de productos">
                <div class="card tarjeta shadow-sm">
                    <div class="card-body p-3 p-md-4">
                        <h2 class="h5 fw-bold mb-3">
                            <?= $productoEditar["id"] > 0 ? "Editar producto #" . intval($productoEditar["id"]) : "Nuevo producto" ?>
                        </h2>

                        <form method="POST" action="index.php">
                            <input type="hidden" name="accion" value="guardar">
                            <input type="hidden" name="id" value="<?= intval($productoEditar["id"]) ?>">

                            <div class="mb-3">
                                <label for="nombre" class="form-label fw-semibold">Nombre del producto</label>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                       minlength="3" required value="<?= escapar($productoEditar["nombre"]) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="marca" class="form-label fw-semibold">Marca</label>
                                <input type="text" id="marca" name="marca" class="form-control"
                                       maxlength="60" required value="<?= escapar($productoEditar["marca"]) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="categoria" class="form-label fw-semibold">Categoría</label>
                                <select id="categoria" name="categoria" class="form-select" required>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= escapar($cat) ?>"
                                            <?= $productoEditar["categoria"] === $cat ? "selected" : "" ?>>
                                            <?= escapar($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="precio" class="form-label fw-semibold">Precio unitario ($ COP)</label>
                                <input type="number" id="precio" name="precio" class="form-control"
                                       min="1000" step="any" required value="<?= escapar($productoEditar["precio"]) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="stock" class="form-label fw-semibold">Stock (unidades)</label>
                                <input type="number" id="stock" name="stock" class="form-control"
                                       min="0" step="1" required value="<?= escapar($productoEditar["stock"]) ?>">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <?= $productoEditar["id"] > 0 ? "Guardar cambios" : "Agregar al inventario" ?>
                            </button>

                            <?php if ($productoEditar["id"] > 0): ?>
                                <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar edición</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Consulta y búsqueda del inventario -->
            <section class="col-12 col-xl-8" aria-label="Lista de productos">
                <div class="card tarjeta shadow-sm">
                    <div class="card-body p-3 p-md-4">
                        <h2 class="h5 fw-bold mb-3">Productos del inventario</h2>

                        <!-- Buscar: horizontal en escritorio, vertical en celular -->
                        <form method="GET" action="index.php" class="row g-2 mb-3">
                            <div class="col-12 col-sm">
                                <label for="busqueda" class="visually-hidden">Buscar productos</label>
                                <input type="search" id="busqueda" name="q" class="form-control"
                                       placeholder="Buscar por nombre o categoría..."
                                       value="<?= escapar($busqueda) ?>">
                            </div>
                            <div class="col-12 col-sm-auto d-grid">
                                <button type="submit" class="btn btn-primary">Buscar</button>
                            </div>
                            <?php if ($busqueda !== ""): ?>
                                <div class="col-12 col-sm-auto d-grid">
                                    <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
                                </div>
                            <?php endif; ?>
                        </form>

                        <?php if (count($productos) === 0): ?>
                            <div class="alert alert-light border mb-0">No se encontraron productos.</div>
                        <?php else: ?>
                            <!-- Vista para pantallas grandes: tabla Bootstrap -->
                            <div class="table-responsive d-none d-xl-block">
                                <table class="table table-hover table-striped align-middle tabla-productos mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Producto</th>
                                            <th scope="col">Marca</th>
                                            <th scope="col">Categoría</th>
                                            <th scope="col">Precio</th>
                                            <th scope="col">Stock</th>
                                            <th scope="col">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productos as $p): ?>
                                            <tr>
                                                <td>#<?= intval($p["id"]) ?></td>
                                                <td class="fw-semibold nombre-producto"><?= escapar($p["nombre"]) ?></td>
                                                <td><?= escapar($p["marca"]) ?></td>
                                                <td><?= escapar($p["categoria"]) ?></td>
                                                <td>$<?= number_format($p["precio"], 0, ",", ".") ?></td>
                                                <td><span class="badge <?= claseStock($p["stock"]) ?>"><?= escapar(textoStock($p["stock"])) ?></span></td>
                                                <td>
                                                    <div class="acciones">
                                                        <a href="index.php?editar=<?= intval($p["id"]) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                        <form method="POST" action="index.php">
                                                            <input type="hidden" name="accion" value="sumar_stock">
                                                            <input type="hidden" name="id" value="<?= intval($p["id"]) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success boton-accion" aria-label="Sumar una unidad a <?= escapar($p["nombre"]) ?>">+1</button>
                                                        </form>
                                                        <form method="POST" action="index.php">
                                                            <input type="hidden" name="accion" value="restar_stock">
                                                            <input type="hidden" name="id" value="<?= intval($p["id"]) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary boton-accion" aria-label="Restar una unidad a <?= escapar($p["nombre"]) ?>" <?= $p["stock"] == 0 ? "disabled" : "" ?>>-1</button>
                                                        </form>
                                                        <form method="POST" action="index.php" onsubmit="return confirm('¿Seguro que deseas eliminar este producto?');">
                                                            <input type="hidden" name="accion" value="eliminar">
                                                            <input type="hidden" name="id" value="<?= intval($p["id"]) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Vista responsive: tarjetas legibles en celulares y tablets -->
                            <div class="d-xl-none">
                                <?php foreach ($productos as $p): ?>
                                    <article class="border rounded-3 p-3 mb-3 bg-white">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="min-w-0">
                                                <div class="small text-secondary">Producto #<?= intval($p["id"]) ?></div>
                                                <h3 class="h6 fw-bold mb-1 nombre-producto"><?= escapar($p["nombre"]) ?></h3>
                                            </div>
                                            <span class="badge <?= claseStock($p["stock"]) ?> text-wrap">
                                                <?= escapar(textoStock($p["stock"])) ?>
                                            </span>
                                        </div>
                                        <dl class="row g-1 mb-3 small">
                                            <dt class="col-4 text-secondary fw-normal">Marca</dt>
                                            <dd class="col-8 mb-0 nombre-producto"><?= escapar($p["marca"]) ?></dd>
                                            <dt class="col-4 text-secondary fw-normal">Categoría</dt>
                                            <dd class="col-8 mb-0"><?= escapar($p["categoria"]) ?></dd>
                                            <dt class="col-4 text-secondary fw-normal">Precio</dt>
                                            <dd class="col-8 mb-0 fw-bold">$<?= number_format($p["precio"], 0, ",", ".") ?></dd>
                                        </dl>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="index.php?editar=<?= intval($p["id"]) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                            <form method="POST" action="index.php">
                                                <input type="hidden" name="accion" value="sumar_stock">
                                                <input type="hidden" name="id" value="<?= intval($p["id"]) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">+1</button>
                                            </form>
                                            <form method="POST" action="index.php">
                                                <input type="hidden" name="accion" value="restar_stock">
                                                <input type="hidden" name="id" value="<?= intval($p["id"]) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" <?= $p["stock"] == 0 ? "disabled" : "" ?>>-1</button>
                                            </form>
                                            <form method="POST" action="index.php" onsubmit="return confirm('¿Seguro que deseas eliminar este producto?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= intval($p["id"]) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        <footer class="text-center text-secondary small py-4">Inventario IberoTech - Proyecto académico CRUD</footer>
    </main>

    <!-- Bootstrap JS: permite cerrar las alertas -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
            crossorigin="anonymous"></script>
</body>
</html>
