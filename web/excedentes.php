<?php
// Lee la configuración que Compose entrega a PHP.
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'),
    getenv('DB_NAME')
);

$pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'));
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$excedentes = $pdo->query(
    'SELECT producto, productor, pueblo, kilos, precio_kg
     FROM excedentes
     WHERE kilos > 0
     ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>NovaHarvest - Excedentes disponibles</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>
<body>
    <header>
        <img src="/img/logo.png" alt="Logotipo de NovaHarvest">
        <p>NovaHarvest</p>

        <nav>
            <a href="/">Inicio</a>
            <a href="/excedentes.php">Excedentes</a>
            <a href="/avisos.html">Avísame</a>
            <a href="/temporada.md">Temporada</a>
            <a href="/estado.php">Estado</a>
        </nav>
    </header>

    <main>
    <h1>Excedentes disponibles</h1>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Productor</th>
                <th>Pueblo</th>
                <th>Kilos</th>
                <th>Precio/kg</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($excedentes as $excedente) { ?>
                <tr>
                    <td><?= $excedente['producto'] ?></td>
                    <td><?= $excedente['productor'] ?></td>
                    <td><?= $excedente['pueblo'] ?></td>
                    <td><?= $excedente['kilos'] ?></td>
                    <td><?= number_format($excedente['precio_kg'], 2, ',', '.') ?> €</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</main>

    <footer>
        NovaHarvest · IES Valle del Jerte · Curso 2026-2027.
    </footer>
</body>
</html>