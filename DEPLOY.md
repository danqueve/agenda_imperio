# Deploy a producción (VPS/cPanel)

Guía para pasar Agenda de Cobranza de WAMP local a producción. Pensada
para un hosting con cPanel (lo más común para este tipo de proyecto en
Argentina), pero aplica igual a un VPS con Apache + cPanel-like tooling.

---

## 0. Antes de empezar

- **PHP**: usar 8.3 o superior si el hosting lo ofrece (MultiPHP Manager
  en cPanel). Evitar 8.1/8.2 - ya sin soporte de seguridad. El código no
  usa nada específico de 8.3+, así que 8.1+ funcionaría si no hay otra
  opción, pero no es lo recomendado.
- **MySQL/MariaDB**: el esquema (`sql/01_schema.sql`) es compatible con
  ambos motores (probado contra MySQL 8.4 en desarrollo). No hace falta
  ningún ajuste según cuál tenga el hosting.
- **Zona horaria**: el código ya fija `America/Argentina/Buenos_Aires`
  por `date_default_timezone_set()` en `public/index.php`,
  `cron_apertura_tickets.php` y `public/cron_diario.php` - no depende de
  la config de `php.ini` del hosting ni hay que tocarla.

---

## 1. Base de datos propia (`c2881399_agenda`)

1. En cPanel → "Bases de datos MySQL": crear la base `c2881399_agenda`
   (mismo nombre que en local - ninguna query cambia) y un usuario nuevo
   con todos los permisos sobre ella.
2. Importar en orden, vía phpMyAdmin o `mysql < archivo.sql`:
   - `sql/01_schema.sql`
   - `sql/02_usuarios.sql` (usuarios iniciales con password "cambiar123"
     - **hacé que Alejandro la cambie apenas entre la primera vez**;
     hoy el sistema no tiene una pantalla de "cambiar mi contraseña",
     así que el cambio se hace por ahora actualizando `password_hash`
     directo en la base, o dándolo de alta de nuevo desde
     "Administrar usuarios" con otra password y desactivando el viejo)

---

## 2. Subir el código

1. Subir todo el contenido de este repo a una carpeta en el hosting
   (por ejemplo `/home/usuario/agenda/`).
2. **Document Root del subdominio (`agenda.tudominio.com.ar`) apuntando
   a `agenda/public`, NO a `agenda/`.** Esto es lo que hace que
   `config/`, `sql/`, `src/`, `views/` y `files/` queden fuera del
   alcance de cualquier URL - la protección real, no solo el `.htaccess`
   de defensa en profundidad que ya viaja en el repo para cuando se
   prueba en WAMP.
3. Confirmar que `public/.htaccess` viajó con el resto (algunos clientes
   FTP ocultan archivos que empiezan con `.` por default).

---

## 3. Configs reales (nunca commitear estos archivos completos)

1. Copiar `config/database.prod.php.example` → `config/database.php` y
   completar `DB_USER`/`DB_PASS` con los del paso 1.
2. Copiar `config/sas_api.prod.php.example` → `config/sas_api.php`.
   `SAS_API_URL` y `SAS_API_TOKEN` se completan en el paso 4, una vez
   que `sas_side/` esté desplegado y sepas la URL real.

---

## 4. Mover `sas_side/` al hosting de SAS (coordinación aparte)

`sas_side/api_readonly.php` y su config **no se despliegan acá** - viven
en el hosting donde corre SAS Imperio (`c2881399_credit`), casi seguro
la misma cuenta cPanel a juzgar por el prefijo compartido. Esto requiere
coordinar con quien administra ese hosting:

1. Correr `sql/03_sas_readonly.sql` **contra la base de SAS**, no la de
   la Agenda - crea el usuario `agenda_readonly` con `GRANT SELECT`
   únicamente sobre `c2881399_credit`.
2. Copiar `sas_side/api_readonly.php` y
   `sas_side/config_readonly.prod.php.example` (renombrado a
   `config_readonly.php`) a ese hosting, en una ruta accesible por URL.
3. Completar `SAS_DB_PASS` (la password real del usuario del paso 1) y
   generar un `SAS_API_TOKEN_VALIDO` propio (por ejemplo con
   `php -r "echo bin2hex(random_bytes(32));"`).
4. Volver a `config/sas_api.php` (paso 3) y completar `SAS_API_URL` con
   la URL real, y `SAS_API_TOKEN` con el **mismo** token generado acá.
5. Probar con curl antes de seguir:
   ```
   curl -H "Authorization: Bearer EL_TOKEN" "https://.../api_readonly.php?accion=atrasados"
   ```
   **Gotcha conocido de hosting compartido (PHP-FPM/CGI):** a veces el
   header `Authorization` no llega a `getallheaders()` sin agregar
   `CGIPassAuth On` (Apache 2.4+) al `.htaccess` de esa carpeta, o sin el
   `SetEnvIf Authorization` que ya trae `public/.htaccess` de este repo
   (copiar ese mismo bloque también del lado de SAS si hace falta). No
   asumas que porque anduvo en WAMP va a andar igual en cPanel - probalo
   apenas subís.

---

## 5. HTTPS

1. Activar AutoSSL para el subdominio (cPanel → SSL/TLS Status).
2. Una vez confirmado que el certificado está activo, agregar al
   `public/.htaccess` (arriba de todo, antes de las demás reglas):
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
   No viaja ya incluido en el repo para no arriesgar un loop de
   redirección si se prueba el deploy antes de que el certificado esté
   listo.
3. `cookie_secure` de la sesión ya se autodetecta en `public/index.php`
   (`true` apenas la request llega por HTTPS) - no hay que tocar nada
   ahí, solo confirmar con las DevTools que la cookie `PHPSESSID` quedó
   marcada `Secure` después de este paso.

---

## 6. Cron Jobs (cPanel → "Cron Jobs")

Dos jobs distintos, con propósitos distintos:

| Job | Comando | Frecuencia sugerida |
|---|---|---|
| Apertura automática de tickets | `php /home/usuario/agenda/cron_apertura_tickets.php` | Cada 1 hora en horario laboral (es un respaldo: `lista_tickets.php` ya sincroniza solo con SAS cuando alguien la mira, con caché de 10 min - este cron cubre los momentos en que nadie la está mirando, por ejemplo de noche) |
| Marcar agendas vencidas | `php /home/usuario/agenda/public/cron_diario.php` | 1 vez por día, temprano (ej. 6:00 AM), antes de que el personal entre a trabajar - así el recordatorio del login ya muestra el estado correcto desde la primera entrada del día |

Notar la ruta: usá el path completo del archivo en el filesystem
(`/home/usuario/agenda/...`), no una URL - cPanel corre esto por CLI.
Como alternativa para `cron_diario.php`, si el hosting solo permite
crons vía URL, usar:
```
curl -s "https://agenda.tudominio.com.ar/cron_diario.php?token=EL_TOKEN_DE_CRON_DIARIO_TOKEN"
```
(cambiar `CRON_DIARIO_TOKEN` en `public/cron_diario.php` por uno propio
antes de depender de esta vía - hoy tiene un valor placeholder).

---

## 7. Checklist final antes de dar el ok

- [ ] `https://agenda.tudominio.com.ar/config/database.php` (o
      `/sql/...`, `/src/...`) devuelve 403 o no carga - confirma que el
      Document Root quedó bien apuntado a `public/`.
- [ ] Login funciona con `alejandro` y con un usuario `supervision`,
      cada uno ve lo que le corresponde (ver `TESTING.md`).
- [ ] `lista_tickets.php` sincroniza contra SAS real sin error.
- [ ] Los dos Cron Jobs están dados de alta y corrieron al menos una vez
      sin error (revisar el log de salida que deja cPanel).
- [ ] Certificado SSL activo, `PHPSESSID` sale con `Secure`.
- [ ] Cambiaste la password de `alejandro` y de cualquier usuario
      sembrado con "cambiar123".
