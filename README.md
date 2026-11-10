# NovaHarvest

Proyecto IAW de Jaime Pico.

## Arranque

### Fase 1: web estática

Arrancar el entorno:

```bash
docker compose up -d
```

Abrir http://localhost:8080/ para consultar la portada.

### Fase 2: PHP-FPM

Construir y arrancar el entorno:

```bash
docker compose up -d --build
```

Comprobar los servicios:

```bash
docker compose ps
```

Comprobar la configuración de nginx:

```bash
docker compose exec web nginx -t
```

Comprobar las extensiones de PHP:

```bash
docker compose exec php php -m
```

Deben aparecer pdo_mysql y mysqli.

Abrir http://localhost:8080/estado.php para comprobar
la fecha, la hora y el identificador del contenedor PHP.

### Parar el entorno

```bash
docker compose down
```

## Arquitectura

Actualmente hay dos servicios:

- web: nginx sirve los archivos estáticos y publica el puerto 8080.
- php: PHP-FPM ejecuta las páginas PHP por el puerto interno 9000.

Los dos servicios montan la carpeta web en
/usr/share/nginx/html.

## Configuración

| Archivo | Función |
|---|---|
| compose.yaml | Define los servicios, puertos y montajes |
| nginx/default.conf | Configura nginx y el envío de PHP a PHP-FPM |
| php/Dockerfile | Construye PHP con pdo_mysql y mysqli |
| php/www.conf | Configura los trabajadores de PHP-FPM |
| php/php.ini | Configura memoria, errores y zona horaria |

## Base de datos

Se incorporará MariaDB en la fase 3.