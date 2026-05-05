# Canal de Denuncias — Empresa Portuaria Coquimbo

Sistema institucional de denuncias seguro y confidencial para la Empresa Portuaria Coquimbo, compuesto por dos portales independientes accesibles a través de un punto de entrada único.

## Portales

| Portal | Ruta | Descripción |
|--------|------|-------------|
| **Landing Page** | `/` | Página de selección de portal |
| **Ley Karin** | `/karin/` | Canal de denuncias de acoso laboral y sexual conforme a la Ley N° 21.643 |
| **Portal Ciudadano** | `/generales/` | Canal de denuncias de irregularidades y conductas contrarias a la probidad |

## Características

- **Sistema dual**: Dos portales independientes, cada uno con su propia base de datos y configuración
- **Punto de entrada único**: Nginx como reverse proxy, exponiendo un único puerto externo
- **Encriptación AES-256-GCM**: Datos sensibles encriptados con libsodium (descripción, datos del denunciante y denunciado, testigos, resolución, notas, archivos adjuntos)
- **Denuncias anónimas**: Reportes sin identificación obligatoria
- **Seguimiento público**: Consulta de estado mediante código único (`DK-` / `DC-`)
- **Panel de administración**: Dashboard con estadísticas, gráficos y gestión de casos
- **Reportes exportables**: CSV y PDF con métricas detalladas
- **Cambio de contraseña obligatorio**: Al primer inicio de sesión
- **Rate limiting por IP**: Protección contra fuerza bruta en login
- **Cabeceras de seguridad HTTP**: CSP, X-Frame-Options, HSTS, Referrer-Policy
- **Registro de actividad**: Auditoría completa de accesos y acciones
- **Dockerizado**: Despliegue con un solo comando

## Stack Tecnológico

- **Backend**: PHP 8.5 + Apache 2
- **Base de datos**: MySQL 8.4 (una instancia por portal)
- **Proxy**: Nginx (reverse proxy, punto de entrada único)
- **Frontend**: Bootstrap 5.3, Bootstrap Icons, Chart.js, GSAP 3
- **PDF**: jsPDF + jsPDF-AutoTable
- **Contenedores**: Docker + Docker Compose

## Requisitos

- Docker Engine y Docker Compose v2+
- Puerto `9090` disponible en el host (configurable mediante `.env`)
- Puertos `3307` y `3308` disponibles para acceso directo a MySQL (opcionales)

## Inicio Rápido

```bash
bash start.sh
```

El script detecta puertos libres, genera claves de encriptación seguras, crea el archivo `.env` y levanta todos los servicios automáticamente.

Una vez iniciado, el sistema queda disponible en:

```
http://<HOST>:9090/
```

## Arquitectura de Servicios

```
Cliente → :9090 (Nginx)
              ├── /           → landing (selector de portales)
              ├── /karin/     → app (Portal Ley Karin)
              └── /generales/ → app-generales (Portal Ciudadano)
```

| Servicio | Descripción |
|----------|-------------|
| `nginx` | Reverse proxy, único puerto expuesto al exterior (`9090`) |
| `app` | Portal Ley Karin — PHP 8.5/Apache |
| `app-generales` | Portal Ciudadano — PHP 8.5/Apache |
| `db` | MySQL 8.4, base de datos `denuncias` |
| `db-generales` | MySQL 8.4, base de datos `denuncias_generales` |
| `landing` | Selector de portales |
| `phpmyadmin` | Administración de BD (solo perfil `dev`) |

> Los puertos directos a los contenedores de aplicación están deshabilitados. Todo el tráfico pasa por Nginx.

## Credenciales y Acceso Inicial

Las credenciales de los usuarios administradores son definidas en el script de inicialización de base de datos (`database/init.sql`). **Todos los usuarios deben cambiar su contraseña en el primer inicio de sesión.**

> ⚠️ **No publicar credenciales en repositorios.** Configurar contraseñas seguras antes del despliegue en producción.

## Encriptación

Los siguientes campos son encriptados en reposo con **libsodium** (XSalsa20-Poly1305):

- Descripción de los hechos
- Datos del denunciante (nombre, email, teléfono, departamento)
- Datos del denunciado (nombre, departamento, cargo)
- Testigos y lugar del incidente
- Resolución y notas de investigación
- Nombres de archivos adjuntos

Cada portal utiliza su propia `ENCRYPTION_KEY` generada automáticamente por `start.sh`.

## Estructura del Proyecto

```
denuncias-docker/
├── config/
│   ├── app.php                # Configuración principal y constantes
│   ├── database.php           # Conexión PDO
│   └── versions.php           # Versiones de librerías CDN (Bootstrap, GSAP, etc.)
├── database/                  # SQL de inicialización — Portal Ley Karin
├── docker/                    # Entrypoint y php.ini
├── includes/                  # Módulos PHP compartidos — Portal Ley Karin
│   ├── bootstrap.php          # Inicialización, constantes y headers de seguridad
│   ├── autenticacion.php      # Autenticación, sesiones y rate limiting
│   ├── encriptacion.php       # Funciones de cifrado/descifrado (libsodium)
│   ├── navbar_publica.php     # Navbar pública con enlace entre portales
│   ├── barra_lateral.php      # Sidebar y topbar del panel de administración
│   └── ...
├── public/                    # DocumentRoot — Portal Ley Karin
│   ├── index.php              # Portal público
│   ├── nueva_denuncia.php     # Formulario de denuncia
│   ├── seguimiento.php        # Consulta de estado por código
│   ├── panel.php              # Dashboard de administración
│   ├── denuncias_admin.php    # Listado y gestión de denuncias
│   ├── detalle_denuncia.php   # Detalle desencriptado de un caso
│   ├── reportes.php           # Reportes exportables (CSV/PDF)
│   ├── admin_usuarios.php     # Gestión de usuarios del sistema
│   ├── registro_actividad.php # Registro de auditoría
│   └── ...
├── denuncias-generales/       # Portal Ciudadano (estructura simétrica)
│   ├── config/
│   │   └── versions.php       # Versiones CDN del Portal Ciudadano
│   ├── database/
│   ├── docker/
│   ├── includes/
│   └── public/
├── landing/                   # Landing page — selector de portales
│   └── public/index.php
├── nginx/
│   └── nginx.conf             # Configuración del reverse proxy
├── scripts/
│   ├── update.sh              # Actualización automática de versiones CDN e infra
│   └── backup-db.sh           # Backup de bases de datos (ambos portales)
├── backups/                   # Dumps de BD generados por backup-db.sh (no versionado)
├── logs/
├── Dockerfile
├── docker-compose.yml
├── .env                       # Variables de entorno (no versionado)
└── start.sh
```

## Variables de Entorno

El archivo `.env` es generado automáticamente por `start.sh`. Las variables disponibles son:

| Variable | Descripción |
|----------|-------------|
| `NGINX_PORT` | Puerto externo del reverse proxy (default: `9090`) |
| `PHP_VERSION` | Versión de PHP para construir las imágenes (default: `8.5`) |
| `MYSQL_VERSION` | Versión de MySQL (default: `8.4`) |
| `DB_PORT` | Puerto MySQL — Portal Ley Karin (default: `3307`) |
| `DB_GENERALES_PORT` | Puerto MySQL — Portal Ciudadano (default: `3308`) |
| `ENCRYPTION_KEY` | Clave de encriptación — Portal Ley Karin |
| `GENERALES_ENCRYPTION_KEY` | Clave de encriptación — Portal Ciudadano |
| `DB_PASS` | Contraseña del usuario de base de datos |
| `DB_ROOT_PASSWORD` | Contraseña root de MySQL |
| `HEALTH_CHECK_TOKEN` | Token de autenticación para el endpoint `/salud` |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASS` | Configuración SMTP para notificaciones (host/puerto/cifrado pueden autodetectarse por dominio de `SMTP_USER` si quedan vacíos) |
| `SMTP_FROM_EMAIL` / `SMTP_FROM_NAME` | Remitente de correos del sistema |
| `EMAIL_QUEUE_BATCH` | Cantidad de correos por ciclo de worker (default: `25`) |
| `APP_ENV` | Entorno de ejecución (`production` / `development`) |

## Actualización de Versiones

El proyecto centraliza todas las versiones de librerías CDN y de infraestructura Docker para facilitar las actualizaciones.

### Librerías CDN (Bootstrap, GSAP, Chart.js, etc.)

Las versiones están declaradas en un único archivo por portal:

```
config/versions.php                       ← Portal Ley Karin
denuncias-generales/config/versions.php   ← Portal Ciudadano
```

Para actualizar una librería, cambia solo el número de versión en esos archivos. El cambio se propaga automáticamente a todo el portal sin modificar ningún otro archivo.

### Script de actualización automática

```bash
# Ver qué cambiaría sin aplicar nada
./scripts/update.sh --dry-run

# Actualizar versiones CDN en versions.php
./scripts/update.sh

# Actualizar versiones CDN y reconstruir contenedores (con el sistema levantado)
./scripts/update.sh --rebuild

# También verificar disponibilidad de nuevas versiones de PHP y MySQL en Docker Hub
./scripts/update.sh --rebuild --infra
```

El script consulta el registro de npm, detecta nuevas versiones y aplica los cambios. Al usar `--rebuild`, reconstruye solo los contenedores de aplicación sin bajar Nginx, por lo que el sistema se mantiene disponible durante la actualización.

> **Nota:** jsPDF y jsPDF-AutoTable se bloquean en su major actual — si sale una versión mayor, el script no la aplica automáticamente hasta que se verifique compatibilidad.

### Versiones de infraestructura Docker

Editar el archivo `.env`:

```env
PHP_VERSION=8.5      # Actualizar PHP
MYSQL_VERSION=8.4    # Actualizar MySQL (hacer backup antes)
```

Luego aplicar:

```bash
docker compose build --no-cache && docker compose up -d
```

## Backup de Bases de Datos

```bash
# Backup de ambas bases de datos
./scripts/backup-db.sh

# Solo Portal Ley Karin
./scripts/backup-db.sh --karin

# Solo Portal Ciudadano
./scripts/backup-db.sh --generales
```

Los backups se guardan como `.sql.gz` en `backups/` con marca de tiempo y se eliminan automáticamente después de 30 días. **Realizar siempre un backup antes de actualizar MySQL.**

## Cola de Correos Asíncrona

Las notificaciones por email se encolan en base de datos (`email_queue`) y se procesan en segundo plano. Esto evita que la respuesta web quede bloqueada por SMTP.

Ejecución manual:

```bash
php scripts/process-email-queue-karin.php
php scripts/process-email-queue-generales.php
```

Con límite personalizado por corrida:

```bash
php scripts/process-email-queue-karin.php 50
php scripts/process-email-queue-generales.php 50
```

Cron recomendado (cada minuto):

```cron
* * * * * cd /ruta/al/proyecto && php scripts/process-email-queue-karin.php >> logs/email-queue-karin.log 2>&1
* * * * * cd /ruta/al/proyecto && php scripts/process-email-queue-generales.php >> logs/email-queue-generales.log 2>&1
```

En Docker, ejecútalo dentro del contenedor correspondiente:

```bash
docker compose exec app php scripts/process-email-queue-karin.php
docker compose exec app-generales php scripts/process-email-queue-generales.php
```

- Las claves de encriptación y contraseñas de base de datos son generadas automáticamente con entropía suficiente.
- El archivo `.env` no debe ser versionado ni compartido.
- En producción, configurar HTTPS en el proxy y ajustar `SMTP_VERIFY_SSL=true`.
- Deshabilitar o eliminar el servicio `phpmyadmin` del `docker-compose.yml` en entornos productivos.

## Licencia

Uso interno — Empresa Portuaria Coquimbo.
