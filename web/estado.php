<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>NovaHarvest - Estado del servidor</title>
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
    <h1>Estado del servidor</h1>

    <p>Fecha y hora del servidor: <?= date('d/m/Y H:i:s') ?></p>

    <p>Contenedor PHP: <?= getenv('HOSTNAME') ?></p>

    <p>Página generada por PHP-FPM</p>
</main>

    <footer>
        NovaHarvest · IES Valle del Jerte · Curso 2026-2027.
    </footer>
</body>
</html>