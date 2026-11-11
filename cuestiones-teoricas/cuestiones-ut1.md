# Cuestiones UT1 — NovaHarvest

Jaime Pico

Las comprobaciones del repositorio corresponden al commit `a7b8a74`. Los resultados de HTTP, PHP y SQL proceden de una prueba aislada con nginx, PHP-FPM y MariaDB ejecutados directamente en Debian, con el código y la configuración de NovaHarvest adaptados a los puertos y rutas de esa prueba. Los resultados de Docker Compose que se citan son de la validación de la configuración o de las capturas del proyecto.

## 1. Software necesario

| Imagen o extensión | Uso en NovaHarvest |
|---|---|
| `nginx:1.27-alpine` | Sirve HTML, CSS, imágenes y Markdown, gestiona HTTPS y pasa los PHP a FastCGI. Alpine permite usar una imagen base ligera. |
| `php:8.3-fpm-alpine` | Es la base del Dockerfile. La variante FPM recibe las peticiones FastCGI y ejecuta los scripts con sus trabajadores. |
| `mariadb:11` | Guarda los excedentes y los avisos. |
| `adminer:5` | Permite consultar y administrar las tablas desde el navegador. |
| `pdo_mysql` | Es el controlador que permite a PDO conectarse a MariaDB. |
| `mysqli` | Es la otra extensión de acceso a MySQL y MariaDB instalada por el Dockerfile. Los scripts de NovaHarvest usan PDO. |

Las etiquetas fijan la serie de versiones de cada servicio y evitan depender de `latest`. Compose valida las imágenes `nginx:1.27-alpine`, `mariadb:11` y `adminer:5`, y genera la imagen de `php` a partir del Dockerfile. El listado de módulos de la prueba en Debian mostró `mysqli`, `mysqlnd`, `PDO` y `pdo_mysql`. Si usara directamente la imagen base de PHP, faltaría `pdo_mysql` y la conexión de `excedentes.php` fallaría con `could not find driver`.

## 2. Tecnologías

Con CGI, el servidor web arrancaría un proceso del intérprete para cada petición a `excedentes.php`. Cien peticiones supondrían cien arranques del intérprete. Con FastCGI, nginx entrega las peticiones a PHP-FPM, que mantiene trabajadores activos y los reutiliza. Con Apache y `mod_php`, el intérprete se ejecutaría dentro de los procesos de Apache. Separar nginx y PHP-FPM permite que la portada siga disponible si PHP se para. Los contenedores comparten el kernel y cada servicio conserva su propia imagen. Uso PDO porque tiene una interfaz común para distintas bases de datos y permite preparar el `INSERT` de los avisos con parámetros con nombre.

## 3. Cliente y servidor

| Archivos | Tipo y procesamiento |
|---|---|
| `index.html`, `avisos.html`, `404.html`, `50x.html` | Estáticos. nginx entrega el HTML y el navegador lo muestra. |
| `temporada.md`, `css/estilos.css`, `img/logo.png` | Estáticos. nginx los entrega y el navegador los muestra o aplica. |
| `excedentes.php`, `avisos.php`, `estado.php` | Dinámicos. PHP-FPM ejecuta el código y nginx devuelve el HTML generado. |

Al consultar el código fuente de `excedentes.php` se recibe la tabla en HTML. PHP ya se ha ejecutado en el servidor. `required` lo comprueba el navegador antes de enviar el formulario. En la prueba, el POST directo con los tres campos vacíos devolvió **200** y la tabla `avisos` contenía **una fila con nombre, correo y producto vacíos**. MariaDB acepta cadenas vacías en esas columnas aunque sean `NOT NULL`. Al enviar el POST a `avisos.html`, nginx devolvió **405** porque es un archivo estático.

## 4. nginx

Estas son líneas del registro de acceso de la prueba aislada:

```text
127.0.0.1 - - [11/Nov/2026:11:09:14 +0100] "GET /excedentes.php HTTP/1.1" 200 2067 "-" "-"
127.0.0.1 - - [11/Nov/2026:11:09:14 +0100] "GET /css/estilos.css HTTP/1.1" 200 1242 "-" "-"
127.0.0.1 - - [11/Nov/2026:11:09:14 +0100] "GET /nada.html HTTP/1.1" 404 808 "https://localhost:44635/" "-"
```

Los campos indican la IP, el identificador y usuario si existen, la fecha, el método y la ruta con la versión HTTP, el código de respuesta, los bytes enviados, la página de procedencia y el agente del navegador. En el 404, la procedencia de la petición de prueba era la portada. El CSS pasó de **1242 bytes** sin comprimir a **574 bytes** de contenido comprimido. Con el bloque Markdown, `temporada.md` devolvió `text/plain; charset=utf-8`. Al quitar ese bloque en la prueba devolvió `text/plain` sin el charset. Dejo `types { }` dentro de ese `location` para que nginx siga reconociendo los tipos del CSS y del PNG.

## 5. MariaDB

Compose monta el volumen lógico `dbdata` en `/var/lib/mysql`. En un clon llamado `novaharvest-prueba`, con el nombre de proyecto predeterminado de Compose, el volumen se llama `novaharvest-prueba_dbdata`. `docker compose down` conserva el volumen y `docker compose down -v` lo elimina junto con los avisos. En la prueba aislada, el aviso siguió en la tabla al reiniciar MariaDB con el mismo directorio de datos. Cambié la contraseña solo en PHP y el listado pasó a devolver **500**, con `Access denied for user` en el registro. Cambiar `DB_PASS` en `.env` y recrear los contenedores tiene ese mismo efecto si el volumen ya estaba inicializado, porque MariaDB conserva la contraseña anterior.

`fecha` se asigna con `CURRENT_TIMESTAMP` en MariaDB y la página Estado usa `Europe/Madrid` en PHP. En la prueba, `SELECT NOW(), @@session.time_zone, @@system_time_zone` devolvió `2026-11-11 11:09:14`, `SYSTEM` y `CET`. Estado mostraba `11/11/2026 11:09:14`. Coincidían en ese entorno. Si MariaDB usa UTC en Docker, las horas pueden diferir aunque correspondan al mismo momento.

## 6. Ejecución de un archivo PHP

Al pedir `http://localhost:8080/excedentes.php`, Docker dirige la petición al puerto 80 de nginx y nginx responde 301 hacia la misma ruta por HTTPS en el puerto 8443. La segunda petición llega al puerto 443 del contenedor. El bloque `location ~ \.php$` envía la petición a `php:9000` por FastCGI, con la ruta del script en `SCRIPT_FILENAME`. PHP consulta MariaDB en `db:3306` con PDO y genera el HTML. nginx lo devuelve al navegador.

Ambos contenedores montan `./web` en `/usr/share/nginx/html` para que la ruta enviada por nginx exista también en PHP. En la prueba dirigí FastCGI a una ruta distinta y obtuve **404**, el texto `File not found.` y el mensaje `Primary script unknown` en el registro. `/nada.html` dio un 404 con mi página personalizada. `/nada.php` dio un 404 de PHP-FPM con `File not found.`, porque nginx pasa esa petición a PHP y deja que la respuesta de FastCGI llegue al navegador.

## 7. PHP-FPM

En Docker, el proceso maestro de PHP-FPM se inicia como `root` y el pool `www` configura a los trabajadores como `www-data`. `pm = dynamic` empieza con 2 trabajadores, mantiene de 1 a 3 libres y permite un máximo de 5 en total. Si los cinco están ocupados, una sexta petición puede esperar en la cola hasta que uno se libere.

Compose monta `php/php.ini` en `/usr/local/etc/php/conf.d/novaharvest.ini`. En el contenedor, `php --ini` sirve para comprobar que se carga ese archivo y `ps aux` permite ver el maestro y el pool. En la prueba de PHP en Debian, con el archivo del proyecto la hora fue **11:09:37, Europe/Madrid**. Al ejecutar PHP sin ese archivo, mostró **10:09:37, UTC**.

## 8. Acceso a la base de datos

En la configuración validada, `php` recibe `DB_HOST=db`, `DB_NAME`, `DB_USER` y `DB_PASS`. Compose toma `DB_PASS` de `.env` y el código la lee con `getenv('DB_PASS')` al crear PDO. `db` se resuelve por su nombre en la red de Compose. `DB_ROOT_PASSWORD` se usa para inicializar MariaDB y solo se pasa al servicio `db`.

`avisos.php` ejecuta el `INSERT` con `$consulta->execute([...])`. El valor de `$_POST['producto']` se envía como parámetro `:producto`. Si se concatenara en el SQL, un valor con un apóstrofo podría romper la sentencia y una entrada preparada con mala intención podría cambiar su significado. Con el parámetro, un apóstrofo sigue siendo parte del dato. El SQL importado dio `USAGE` y este permiso:

```sql
GRANT SELECT, INSERT ON `novaharvest`.* TO `novaharvest_app`@`%`
```

`SELECT` permite leer los excedentes e `INSERT` permite guardar avisos. La prueba con el usuario de la aplicación rechazó `DELETE` con el error **1142**.

## 9. Seguridad

| Medida | Resultado comprobado |
|---|---|
| PHP y MariaDB sin puertos publicados | En la configuración de Compose ambos servicios carecen de `ports`. |
| Adminer limitado al propio equipo | Compose publica `127.0.0.1:8081` hacia el puerto interno 8080. |
| `.env` y certificados excluidos | `git check-ignore -v` señaló las líneas 2 y 5 de `.gitignore`. El historial de esas rutas está vacío. |
| Trabajadores de PHP como `www-data` | `php/www.conf` asigna `www-data` en `user` y `group`. |
| Usuario de base de datos con permisos limitados | MariaDB permitió consultar y registrar avisos y rechazó `DELETE`. |
| HTTPS | La petición HTTP devolvió 301 y la ruta respondió por HTTPS. |
| `display_errors = Off` | Con MariaDB parada, la prueba devolvió 500 con el cuerpo vacío. |
| Límite del cuerpo a 64 KB | Un POST de 100 KB devolvió 413. |

El aviso del navegador aparece porque el certificado es autofirmado. Para evitarlo, el cliente debe confiar en el certificado o en la entidad que lo firma y el nombre del certificado debe coincidir con el de la web. En la prueba con MariaDB parada, al activar `display_errors` apareció una `PDOException` y se mostró la ruta interna de `estado.php`. El archivo de la entrega tiene `display_errors = Off`.

## 10. Pruebas y diagnóstico

| Petición en la prueba aislada con PHP parado | Respuesta | Tiempo |
|---|---|---|
| `/estado.php` | 502 con «Servicio no disponible» | 0,002 s |
| `/` | 200, portada disponible | 0,002 s |

nginx sirve la portada desde disco. Para Estado necesita conectar con PHP-FPM. Si solo se para la base de datos, PHP-FPM sigue recibiendo la petición y el fallo ocurre dentro de PHP. Con los errores desactivados, la respuesta de la prueba fue 500 con cuerpo vacío. El healthcheck de Compose comprueba la conexión y que InnoDB esté inicializado. `php` y `adminer` esperan a que `db` pase de `starting` a `healthy`. Si solo se comprobara que el contenedor ha arrancado, una petición podría llegar mientras MariaDB sigue inicializándose.

Durante el proyecto tuve un **502** en `/estado.php`. `docker compose logs --tail=30 web php db` mostró `Connection refused` al conectar nginx con PHP-FPM en el puerto 9000. También tuve un error de sangría al añadir el healthcheck. `docker compose config -q` marcó `services.healthcheck` y rechazó sus propiedades. Puse el bloque dentro de `db`, al mismo nivel que `image` y `volumes`. En la revisión, el Compose de las cuatro fases pasó la validación.

## 11. Documentación

La comprobación empieza con un clon del repositorio, como la copia `novaharvest-prueba`. A partir del README se crea `.env` con `cp .env.example .env`, se completan las contraseñas, se generan los certificados con OpenSSL, se arranca con `docker compose up -d --build` y se carga `db/novaharvest.sql` como `root`. `docker compose ps` sirve para comprobar que aparecen los cuatro servicios y que `db` está `healthy`. En la revisión del clon de GitHub, la configuración pasó `compose config -q`. Tras importar el SQL en la prueba aislada, `SHOW TABLES` devolvió `avisos` y `excedentes`. Estado respondió **200** y mostró la versión de MariaDB.

El README anterior todavía describía la fase 2 y faltaban los pasos de `.env`, HTTPS y la carga inicial de la base de datos. El README de la entrega recoge esos pasos en orden. `.env.example` indica las variables necesarias y cada equipo genera su propio `.env` y sus certificados para mantener las contraseñas y la clave privada fuera de Git. En esta configuración, Compose parte de esos archivos ya preparados y la carga inicial del SQL se hace manualmente una vez por volumen.