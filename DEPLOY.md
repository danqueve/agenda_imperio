# Deploy a producción — VPS Ferozo Panel (AlmaLinux 8)

Guía específica para este proyecto: VPS propio con **Panel Ferozo** sobre
**AlmaLinux 8**, acceso por **SSH**, y en el que **ya corre SAS Imperio**
(la base `c2881399_credit`). Esto es más simple que un deploy a hosting
compartido de terceros: es tu propio servidor, así que probablemente
tengas acceso root/sudo por SSH y no dependas de coordinar con otro
administrador para el paso de SAS (sección 5).

**Esta guía la ejecutás vos por SSH** - no fue corrida contra el VPS real
desde acá. Cada bloque de comandos está pensado para copiar/pegar, pero
revisá los valores marcados `CAMBIAR`/`AJUSTAR` antes de correrlos.

---

## 0. Antes de empezar

- **Es un servidor compartido con un sistema en producción real** (SAS
  Imperio gestiona créditos reales). Todo lo de esta guía agrega cosas
  nuevas (un subdominio, una base nueva, un usuario MySQL nuevo, 2 cron
  jobs nuevos) - en ningún paso se toca el sitio, la base ni la config ya
  existente de SAS. Si en algún punto no estás seguro de si algo podría
  afectar a SAS, pará y confirmá antes de seguir.
- **Backup preventivo** antes de tocar nada, aunque esta guía no borre ni
  modifique datos de SAS: `mysqldump c2881399_credit > ~/backup_credit_antes_de_agenda.sql`
  (con las credenciales que ya uses para administrar esa base).
- **PHP**: Panel Ferozo suele traer un selector de versión de PHP por
  dominio/subdominio (buscá algo como "Configuración de PHP" o "Versión
  de PHP" en el panel, una vez creado el subdominio del paso 4). Elegí
  8.1 o superior — el código no usa nada específico de una versión en
  particular. **No instales PHP a mano con `dnf`** en paralelo al que
  gestiona el panel: en un panel de hosting, el PHP-FPM real que atiende
  las requests suele ser el que el panel controla, no el del sistema.
- **MySQL/MariaDB**: `sql/01_schema.sql` ya se probó contra MySQL 8.4 en
  desarrollo y es compatible con MariaDB también. No hace falta ajuste.
- **Zona horaria**: el código ya fija `America/Argentina/Buenos_Aires`
  por `date_default_timezone_set()` en `public/index.php`,
  `cron_apertura_tickets.php` y `public/cron_diario.php` - no depende de
  `php.ini` del servidor, no hay que tocarlo (y si este VPS aloja otros
  sitios además de SAS y la Agenda, mejor no tocar el `php.ini` global de
  todos modos).

---

## 1. Traer el código al VPS (git por SSH)

El repo es `https://github.com/danqueve/agenda_imperio.git`. Si es
privado, la forma más prolija de clonarlo en el VPS sin usar tu password
personal de GitHub es una **deploy key** (una clave SSH de solo lectura,
propia de este repo):

```bash
# En el VPS, como el usuario que va a administrar la Agenda:
ssh-keygen -t ed25519 -f ~/.ssh/agenda_imperio_deploy -N ""
cat ~/.ssh/agenda_imperio_deploy.pub
```

Copiá esa clave pública a GitHub → repo `agenda_imperio` → *Settings* →
*Deploy keys* → *Add deploy key* (no hace falta permiso de escritura).
Después, en el VPS:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github-agenda
    HostName github.com
    User git
    IdentityFile ~/.ssh/agenda_imperio_deploy
    IdentitiesOnly yes
EOF

git clone github-agenda:danqueve/agenda_imperio.git ~/agenda
```

(Si el repo es público, alcanza con `git clone https://github.com/danqueve/agenda_imperio.git ~/agenda` y te ahorrás toda esta parte.)

Para actualizaciones futuras, ya con el repo clonado: `cd ~/agenda && git pull`.

---

## 2. Base de datos propia (`c2881399_agenda`)

1. En **Panel Ferozo** → sección de Bases de datos MySQL: crear la base
   `c2881399_agenda` (mismo nombre que en local, así ninguna query del
   código cambia) y un usuario nuevo con todos los permisos sobre
   *esa* base únicamente (no sobre `c2881399_credit`).
2. Importar el esquema y los usuarios iniciales por SSH:

   ```bash
   mysql -u TU_USUARIO_MYSQL -p c2881399_agenda < ~/agenda/sql/01_schema.sql
   mysql -u TU_USUARIO_MYSQL -p c2881399_agenda < ~/agenda/sql/02_usuarios.sql
   ```

3. `sql/02_usuarios.sql` siembra usuarios con password `cambiar123` -
   **hacé que Alejandro la cambie apenas entre la primera vez**. Hoy el
   sistema no tiene pantalla de "cambiar mi contraseña"; por ahora se
   cambia dándolo de alta de nuevo desde "Administrar usuarios" con otra
   password y desactivando el viejo, o actualizando `password_hash`
   directo en la tabla `usuarios_acceso`.

---

## 3. Configs reales (nunca commitear estos archivos completos)

```bash
cp ~/agenda/config/database.prod.php.example ~/agenda/config/database.php
cp ~/agenda/config/sas_api.prod.php.example   ~/agenda/config/sas_api.php
```

- `config/database.php`: completar `DB_USER`/`DB_PASS` con los del
  paso 2. `DB_HOST` queda en `localhost` (misma base de datos del VPS).
- `config/sas_api.php`: `SAS_API_URL`/`SAS_API_TOKEN` se completan recién
  en el paso 5, una vez que sepas la URL real de `sas_side/api_readonly.php`.

---

## 4. Subdominio en Panel Ferozo → Document Root

1. Panel Ferozo → Subdominios (o Dominios): crear
   `agenda.imperiocomercial.com.ar` (el DNS ya resuelve a este VPS, así
   que solo falta el subdominio del lado del panel).
2. **Document Root apuntando a `~/agenda/public`, NO a `~/agenda/`.**
   Esto es lo que deja `config/`, `sql/`, `src/`, `views/` fuera del
   alcance de cualquier URL - la protección real; el `.htaccess` por
   carpeta que ya viaja en el repo es solo defensa en profundidad para
   cuando el Document Root está mal apuntado (p. ej. en WAMP local).
3. Confirmar que `public/.htaccess` viajó con el resto del clone (con
   git no debería perderse nunca, a diferencia de subir por FTP donde
   algunos clientes esconden archivos que empiezan con `.`).
4. Probar `https://agenda.imperiocomercial.com.ar/?p=login` - debería
   cargar el formulario de login (o dar error de config si faltó algo
   del paso 3).

---

## 5. Conectar con SAS Imperio (mismo VPS)

Como SAS Imperio y la Agenda conviven en el mismo servidor, esto es más
simple que coordinar con un hosting ajeno - pero la separación de
permisos sigue siendo importante: la Agenda nunca debe tener un usuario
MySQL con acceso de escritura a `c2881399_credit`, y por eso sigue
existiendo la capa de API de solo lectura (`sas_side/api_readonly.php`)
en vez de conectar la Agenda directo a esa base.

1. Confirmar el nombre real de la base de SAS (por si no es exactamente
   `c2881399_credit` en este servidor): `mysql -u root -p -e "SHOW DATABASES;"`.
2. Correr `sql/03_sas_readonly.sql` **contra la base de SAS** (ajustar el
   nombre de la base en el archivo si hiciera falta):

   ```bash
   mysql -u root -p c2881399_credit < ~/agenda/sql/03_sas_readonly.sql
   ```

   Esto crea el usuario `agenda_readonly` con `GRANT SELECT` únicamente.
   No modifica ni borra ninguna fila existente.
3. Ubicar la carpeta donde corre el código de SAS Imperio en este VPS
   (la conocés porque ya lo administrás) y copiar ahí:
   - `sas_side/api_readonly.php`
   - `sas_side/config_readonly.prod.php.example` → renombrado a
     `config_readonly.php`, en una ruta accesible por URL desde ese
     mismo dominio/subdominio de SAS.
4. En `config_readonly.php`: completar `SAS_DB_PASS` (la password real
   del usuario `agenda_readonly` del paso 2) y generar un token propio
   directamente en el servidor:

   ```bash
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

5. Volver a `~/agenda/config/sas_api.php` (paso 3) y completar
   `SAS_API_URL` con la URL real de ese `api_readonly.php`, y
   `SAS_API_TOKEN` con el **mismo** token generado en el paso anterior.
6. Probar antes de seguir:

   ```bash
   curl -H "Authorization: Bearer EL_TOKEN" "https://dominio-de-sas/.../api_readonly.php?accion=atrasados"
   ```

   **Gotcha conocido (Apache + PHP-FPM/CGI):** a veces el header
   `Authorization` no llega a `getallheaders()` sin `CGIPassAuth On` en
   el `.htaccess` de esa carpeta, o el `SetEnvIf Authorization` que ya
   trae `public/.htaccess` de este repo (copiar ese mismo bloque también
   del lado de SAS si hiciera falta). Probalo apenas subís, no asumas
   que porque anduvo en WAMP anda igual acá.

---

## 6. HTTPS

Panel Ferozo integra Let's Encrypt: al apuntar el subdominio nuevo
(paso 4) con la opción de generar SSL habilitada, el certificado suele
emitirse e instalarse automáticamente. Confirmá en el panel (sección
SSL/TLS) que `agenda.imperiocomercial.com.ar` tiene certificado activo.

Una vez confirmado, agregar al principio de `public/.htaccess` (antes de
las demás reglas):

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

No viaja ya incluido en el repo para no arriesgar un loop de redirección
si se prueba el deploy antes de que el certificado esté listo.

`cookie_secure` de la sesión ya se autodetecta en `public/index.php`
(`true` apenas la request llega por HTTPS) - solo confirmar con las
DevTools que la cookie `PHPSESSID` sale marcada `Secure` después de este
paso.

---

## 7. Cron Jobs

Como es tu propio VPS con SSH, lo más directo es un `crontab -e` normal
(revisá si Panel Ferozo también tiene una sección de Cron Jobs que
prefieras usar en su lugar - en ese caso cargá los mismos 2 comandos ahí
en vez de por SSH, no los dos a la vez):

```bash
crontab -e
```

```cron
# Apertura automática de tickets - respaldo de la sincronización on-demand
0 * * * * php ~/agenda/cron_apertura_tickets.php >> ~/agenda/cron_apertura.log 2>&1

# Marca agendas vencidas como incumplidas, antes de que entre el personal
0 6 * * * php ~/agenda/public/cron_diario.php >> ~/agenda/cron_diario.log 2>&1
```

Si por algún motivo `public/cron_diario.php` solo puede dispararse por
URL (no por CLI) en este panel, la alternativa es:

```bash
curl -s "https://agenda.imperiocomercial.com.ar/cron_diario.php?token=EL_TOKEN"
```

cambiando antes el placeholder `CAMBIAR_este_token_del_cron_2026` en
`public/cron_diario.php` por un token propio (no el mismo que el de SAS).

---

## 8. Checklist final antes de dar el ok

- [ ] `https://agenda.imperiocomercial.com.ar/config/database.php` (o
      `/sql/...`, `/src/...`, `/views/...`) no carga - confirma que el
      Document Root quedó apuntando a `public/`.
- [ ] Login funciona con `alejandro` y con un usuario `supervision`.
- [ ] `lista_tickets.php` sincroniza contra SAS real sin error (probar
      también el caso "SAS caído" cambiando momentáneamente
      `SAS_API_URL` a algo inválido - debe degradar con aviso, no romper
      la pantalla).
- [ ] Los 2 cron jobs están dados de alta y corrieron al menos una vez
      sin error (revisar `cron_apertura.log`/`cron_diario.log`).
- [ ] Certificado SSL activo, `PHPSESSID` sale con `Secure`.
- [ ] Cambiaste la password de `alejandro` y de cualquier usuario
      sembrado con "cambiar123".
- [ ] El usuario MySQL de la Agenda (paso 2) **no** tiene permisos sobre
      `c2881399_credit`, y el usuario `agenda_readonly` (paso 5) **no**
      tiene permisos de escritura sobre esa misma base - confirmar con
      `SHOW GRANTS FOR 'usuario'@'localhost';` en cada uno.

Después de esto, seguí el plan de prueba manual de `TESTING.md` durante
la primera semana de uso real.
