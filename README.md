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
├── config/                    # Configuración de aplicación y base de datos
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
│   ├── database/
│   ├── docker/
│   ├── includes/
│   └── public/
├── landing/                   # Landing page — selector de portales
│   └── public/index.php
├── nginx/
│   └── nginx.conf             # Configuración del reverse proxy
├── logs/
├── Dockerfile
├── docker-compose.yml
└── start.sh
```

## Variables de Entorno

El archivo `.env` es generado automáticamente por `start.sh`. Las variables disponibles son:

| Variable | Descripción |
|----------|-------------|
| `NGINX_PORT` | Puerto externo del reverse proxy (default: `9090`) |
| `DB_PORT` | Puerto MySQL — Portal Ley Karin (default: `3307`) |
| `DB_GENERALES_PORT` | Puerto MySQL — Portal Ciudadano (default: `3308`) |
| `ENCRYPTION_KEY` | Clave de encriptación — Portal Ley Karin |
| `GENERALES_ENCRYPTION_KEY` | Clave de encriptación — Portal Ciudadano |
| `DB_PASS` | Contraseña del usuario de base de datos |
| `DB_ROOT_PASSWORD` | Contraseña root de MySQL |
| `HEALTH_CHECK_TOKEN` | Token de autenticación para el endpoint `/salud` |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASS` | Configuración SMTP para notificaciones |
| `SMTP_FROM_EMAIL` / `SMTP_FROM_NAME` | Remitente de correos del sistema |
| `APP_ENV` | Entorno de ejecución (`production` / `development`) |

## Seguridad

- Las claves de encriptación y contraseñas de base de datos son generadas automáticamente con entropía suficiente.
- El archivo `.env` no debe ser versionado ni compartido.
- En producción, configurar HTTPS en el proxy y ajustar `SMTP_VERIFY_SSL=true`.
- Deshabilitar o eliminar el servicio `phpmyadmin` del `docker-compose.yml` en entornos productivos.

## Licencia

Uso interno — Empresa Portuaria Coquimbo.
