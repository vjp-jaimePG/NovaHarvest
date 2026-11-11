# Cuestiones UT1 — NovaHarvest

Jaime Pico

Las comprobaciones de HTTP, PHP y MariaDB se hicieron en una prueba aislada en Debian con el código de NovaHarvest. Los resultados de Compose proceden de la validación de los archivos del repositorio y de las salidas que aparecieron durante el montaje del proyecto.

## 1. Software necesario

| Imagen o extensión | Uso en NovaHarvest |
|---|---|
| `nginx:1.27-alpine` | Sirve los archivos estáticos, gestiona HTTPS y envía las peticiones PHP a FastCGI. Alpine permite usar una imagen base ligera. |
| `php:8.3-fpm-alpine` | Es la base de la imagen de PHP. La variante FPM mantiene procesos que ejecutan los scripts que recibe de nginx. |
| `mariadb:11` | Guarda los excedentes y los avisos. |
| `adminer:5` | Permite consultar las tablas desde el navegador. |
| `pdo_mysql` | Es el controlador que usa PDO para conectar con MariaDB. |
| `mysqli` | Es la otra extensión de acceso a MySQL y MariaDB que instala el Dockerfile. |

Las imágenes están etiquetadas para limitar la serie de versiones que usa el proyecto. Con `latest`, una descarga posterior podría traer una versión distinta sin haber cambiado el Compose. La configuración de la fase final identifica las imágenes `nginx:1.27-alpine`, `mariadb:11` y `adminer:5`. El servicio `php` se construye con su Dockerfile.

En la prueba de PHP en Debian, los módulos relacionados con MariaDB fueron:

```text
mysqli
mysqlnd
PDO
pdo_mysql
```

Si utilizara directamente `php:8.3-fpm-alpine` sin el Dockerfile, faltaría `pdo_mysql` y `excedentes.php` fallaría al conectar con el error `could not find driver`.

## 2. Tecnologías

Con CGI, el servidor web lanzaría un proceso para ejecutar `excedentes.php` en cada petición. Para cien peticiones se producirían cien arranques del intérprete. Con FastCGI, nginx envía las peticiones a PHP-FPM, que reutiliza los procesos trabajadores que ya están en ejecución. Con Apache y `mod_php`, el intérprete se carga dentro de los procesos de Apache.

En NovaHarvest, separar nginx y PHP-FPM permite que la portada siga funcionando si se para PHP. Los contenedores comparten el kernel y cada servicio conserva su propia imagen, por lo que no hace falta una máquina virtual por servicio. Uso PDO porque ofrece una interfaz común para distintas bases de datos y me permite preparar la consulta de los avisos con parámetros con nombre.

## 3. Cliente y servidor

| Archivos | Tipo y procesamiento |
|---|---|
| `index.html`, `avisos.html`, `404.html` y `50x.html` | Estáticos. nginx entrega el HTML y el navegador lo muestra. |
| `temporada.md`, `css/estilos.css` e `img/logo.png` | Estáticos. nginx los entrega y el navegador los muestra o aplica. |
| `excedentes.php`, `avisos.php` y `estado.php` | Dinámicos. PHP-FPM ejecuta el código y nginx devuelve el HTML generado. |

Al consultar el código fuente de `excedentes.php` se ve la tabla HTML que generó PHP en el servidor. El atributo `required` lo comprueba el navegador antes de enviar el formulario. En la prueba, un POST directo a `avisos.php` con los tres campos vacíos devolvió **HTTP 200**. La consulta a MariaDB confirmó **una fila con nombre, correo y producto vacíos**. Las columnas `NOT NULL` admiten cadenas vacías. Al enviar el POST a `avisos.html`, nginx devolvió **HTTP 405** porque esa ruta sirve un archivo estático.

## 4. nginx

Estas son tres líneas del registro de acceso de la prueba aislada:

```text
127.0.0.1 - - [11/Nov/2026:11:09:14 +0100] "GET /excedentes.php HTTP/1.1" 200 2067 "-" "-"
127.0.0.1 - - [11/Nov/2026:11:09:14 +0100] "GET /css/estilos.css HTTP/1.1" 200 1242 "-" "-"
127.0.0.1 - - [11/Nov/2026:11:09:14 +0100] "GET /nada.html HTTP/1.1" 404 808 "https://localhost:44635/" "-"
```

Cada línea indica la IP, los campos de identidad y usuario, la fecha, el método y la ruta, la versión HTTP, el código de respuesta, los bytes enviados, la página de procedencia y el agente del cliente. El 404 indica que la petición de prueba a `nada.html` venía de la portada. El puerto `44635` era el puerto HTTPS asignado a esa prueba aislada.

El CSS ocupó **1242 bytes sin compresión** y **574 bytes con gzip**. Con el bloque de Markdown, `temporada.md` devolvió `text/plain; charset=utf-8`. Al quitar ese bloque en la prueba, devolvió `text/plain` sin el charset. `types { }` está dentro de ese `location` para que nginx siga reconociendo los tipos del CSS y del PNG en las demás rutas.

## 5. MariaDB

Compose monta `dbdata` en `/var/lib/mysql`. Con el nombre predeterminado del clon `novaharvest-prueba`, el volumen se llama `novaharvest-prueba_dbdata`. `docker compose down` conserva el volumen. `docker compose down -v` lo elimina junto con las tablas y los avisos que contiene. En la prueba aislada, el aviso siguió en la tabla al reiniciar MariaDB con el mismo directorio de datos.

Al cambiar la contraseña solo en PHP durante la prueba, `excedentes.php` devolvió **HTTP 500** y el registro mostró `Access denied for user`. Si se cambia `DB_PASS` en `.env` y se recrean los contenedores, PHP recibe la nueva contraseña. MariaDB conserva la que tenía guardada en el volumen inicializado.

`fecha` se asigna con `CURRENT_TIMESTAMP` en MariaDB y `estado.php` usa `Europe/Madrid`. En la prueba, `SELECT NOW(), @@session.time_zone, @@system_time_zone` devolvió `2026-11-11 11:09:14`, `SYSTEM` y `CET`. Estado mostraba `11/11/2026 11:09:14`. Las horas coincidieron en ese entorno. Si MariaDB usa UTC y PHP usa la hora de Madrid, se mostrará una hora distinta para el mismo momento.

## 6. Ejecución de un archivo PHP

Al abrir `http://localhost:8080/excedentes.php`, Docker lleva la petición al puerto 80 de nginx. nginx responde con un 301 y el navegador vuelve a pedir la ruta por HTTPS en el puerto 8443, que llega al puerto 443 del contenedor. El bloque `location ~ \.php$` envía la petición a `php:9000` por FastCGI. La directiva `SCRIPT_FILENAME` indica qué archivo debe ejecutar PHP-FPM. PHP consulta `db:3306` mediante PDO, genera la tabla y nginx devuelve el HTML al navegador.

Los dos servicios montan `./web` en `/usr/share/nginx/html` para que la ruta que calcula nginx exista en PHP-FPM. Al dirigir FastCGI a una ruta distinta en la prueba, la respuesta fue **404**, apareció `File not found.` y el registro mostró `Primary script unknown`. `/nada.html` dio un 404 con la página personalizada de nginx. `/nada.php` también dio 404, pero con `File not found.`, porque esa respuesta la genera PHP-FPM.

## 7. PHP-FPM

El proceso maestro de PHP-FPM se inicia como `root` en el contenedor y el pool `www` configura los trabajadores como `www-data`. Con `pm = dynamic`, arranca con 2 trabajadores, mantiene entre 1 y 3 libres y admite hasta 5 en total. Si los cinco están ocupados, una sexta petición puede esperar en la cola hasta que se libere uno.

Compose monta `php/php.ini` en `/usr/local/etc/php/conf.d/novaharvest.ini`. `php --ini` permite comprobar qué archivo carga PHP y `ps aux` permite distinguir el proceso maestro de los trabajadores del pool. En la prueba de PHP en Debian, el archivo del proyecto dio **11:09:37, Europe/Madrid**. Al ejecutar PHP sin ese archivo, el resultado fue **10:09:37, UTC**.

## 8. Acceso a la base de datos

En la configuración validada de Compose, PHP recibe estas cuatro variables: `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS`. `DB_HOST` vale `db`, que es el nombre del servicio de MariaDB en la red interna. Compose toma `DB_PASS` de `.env` y el código usa `getenv('DB_PASS')` al crear PDO. La contraseña de `root` se pasa a MariaDB para su inicialización.

En `avisos.php`, `$consulta->execute([...])` envía `$_POST['producto']` como valor de `:producto`. Si se concatenara el valor en la consulta, un apóstrofo podría romper la sentencia y una entrada manipulada podría cambiar su significado. Por ejemplo, un nombre como `Tomate d'Alacant` debe quedar guardado entero como texto. Con los parámetros, PDO mantiene separado el dato de la sentencia SQL.

Tras importar el SQL, el usuario de la aplicación tenía `USAGE` y el siguiente permiso:

```sql
GRANT SELECT, INSERT ON `novaharvest`.* TO `novaharvest_app`@`%`
```

`SELECT` sirve para leer los excedentes e `INSERT` para guardar avisos. Al intentar `DELETE` con el usuario de la aplicación, MariaDB rechazó la operación con el error **1142**.

## 9. Seguridad

| Medida | Qué protege y cómo se comprobó |
|---|---|
| PHP y MariaDB sin puertos publicados | Compose permite comprobar que esos servicios se usan por la red interna. |
| Adminer en `127.0.0.1:8081` | El puerto se publica en la interfaz local y queda limitado al propio equipo. |
| `.env` y certificados excluidos | `git check-ignore -v` señaló las líneas 2 y 5 de `.gitignore`. El historial de esas rutas está vacío. |
| Trabajadores `www-data` | `php/www.conf` fija el usuario y grupo de los procesos que ejecutan los scripts. |
| Usuario `novaharvest_app` | `SHOW GRANTS` confirmó `SELECT` e `INSERT`. MariaDB rechazó `DELETE`. |
| HTTPS | La petición HTTP devolvió 301 y la página respondió por HTTPS. |
| `display_errors = Off` | Con MariaDB parada, `estado.php` devolvió 500 con el cuerpo vacío. |
| `client_max_body_size 64k` | Un POST de 100 KB devolvió 413. |

El navegador avisa porque el certificado es autofirmado. Para evitar el aviso, el navegador debe confiar en el certificado o en la entidad que lo firma y el nombre debe coincidir con el de la web. En la prueba con la base de datos parada y `display_errors = On`, se vieron una `PDOException` y la ruta interna de `estado.php`. La configuración final deja los errores de PHP fuera de la respuesta al visitante.

## 10. Pruebas y diagnóstico

| Petición con PHP-FPM parado en la prueba aislada | Resultado | Tiempo |
|---|---|---|
| `/estado.php` | 502 y página «Servicio no disponible» | 0,002 s |
| `/` | 200 y portada disponible | 0,002 s |

La portada sigue funcionando porque nginx lee `index.html` directamente. La página Estado necesita PHP-FPM. Si solo se para MariaDB, PHP-FPM sigue recibiendo la petición y falla al conectar con la base de datos. Con `display_errors = Off`, la prueba devolvió 500 con el cuerpo vacío.

El healthcheck de `db` comprueba la conexión y que InnoDB esté inicializado. En la configuración final, `php` y `adminer` esperan a que MariaDB pase de `starting` a `healthy`. Sin `condition: service_healthy`, podrían arrancar mientras la base todavía se está inicializando.

Durante el montaje tuve un **502** en `estado.php`. `docker compose logs --tail=30 web php db` mostró `Connection refused` cuando nginx intentaba conectar con PHP-FPM en el puerto 9000. También coloqué por error `healthcheck` al mismo nivel que `db`. `docker compose config -q` señaló `services.healthcheck` y rechazó sus propiedades. La corrección fue meter el bloque dentro de `db`, alineado con `image` y `volumes`. Después se validaron las configuraciones de Compose de las cuatro fases.

## 11. Documentación

La copia `novaharvest-prueba` sirve para comprobar cómo se prepara el proyecto desde un clon limpio. Los pasos son copiar `.env.example` a `.env`, rellenar las contraseñas, generar el certificado con OpenSSL, arrancar con `docker compose up -d --build` y cargar `db/novaharvest.sql` como `root`. El README indica también cómo usar `docker compose ps` para comprobar los servicios.

En la revisión del clon de GitHub se generó un certificado con `CN=localhost` y pasó la validación de Compose. Los servicios reconocidos fueron `db`, `php`, `web` y `adminer`. Después de importar el SQL en la prueba aislada, la consulta devolvió estas dos tablas:

```text
avisos
excedentes
```

En esa misma prueba, `estado.php` respondió **HTTP 200** y mostró la versión de MariaDB. El README anterior describía la fase 2 y faltaban los pasos de `.env`, HTTPS y la importación inicial. El README actualizado ya recoge esos pasos en orden. Se versiona `.env.example` para indicar las variables necesarias. Cada equipo prepara su `.env`, su certificado y su clave privada. La configuración de Compose utiliza esos archivos y el SQL se importa manualmente una vez por cada volumen nuevo.