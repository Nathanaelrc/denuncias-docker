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


## Portales

| Portal | Descripción |
|--------|-------------|
| **Ley Karin** | Canal de denuncias de acoso laboral y sexual en cumplimiento de la Ley Nº 21.643 |
| **Portal Ciudadano** | Canal de denuncias generales (irregularidades, corrupción, otros) |
| **Landing Page** | Página de selección de portal |

## Características

- **Sistema dual**: Dos portales independientes, cada uno con su propia base de datos y configuración
- **Landing page**: Selector de portal con diseño moderno
- **Encriptación AES-256**: Datos sensibles encriptados con libsodium (descripción, nombres, contactos, testigos, resolución, notas)
- **Denuncias anónimas**: Reportes sin identificación obligatoria
- **Seguimiento público**: Consulta de estado mediante código único (formato `DK-` / `DC-`)
- **Panel de administración**: Dashboard con estadísticas, gráficos y gestión de casos
- **Reportes exportables**: CSV y PDF con métricas detalladas
- **Navbar unificada**: Barra de navegación con cambio entre portales
- **Cambio de contraseña obligatorio**: Al primer inicio de sesión
- **Rate limiting por IP**: Protección contra fuerza bruta en login
- **Cabeceras CSP**: Content Security Policy configurada
- **Registro de actividad**: Auditoría completa de accesos y acciones
- **Dockerizado**: Despliegue con un solo comando

## Stack Tecnológico

- **Backend**: PHP 8.2 + Apache 2
- **Base de datos**: MySQL 8.0 (una instancia por portal)
- **Frontend**: Bootstrap 5.3.2, Bootstrap Icons 1.11.1, Chart.js, GSAP 3.12.2
- **PDF**: jsPDF 2.5.2 + jsPDF-AutoTable 3.8.4
- **Contenedores**: Docker + Docker Compose

## Requisitos

- Docker y Docker Compose v2+
- Puertos 8090-8093 y 3307-3308 disponibles (auto-configurables)

## Inicio Rápido

```bash
bash start.sh
```

El script detecta puertos libres, genera claves de encriptación, crea el archivo `.env` y levanta todos los servicios automáticamente.

## Servicios y Puertos

| Servicio | Puerto | Descripción |
|----------|--------|-------------|
| Landing Page | 8090 | Selector de portal |
| Portal Ley Karin | 8091 | Denuncias Ley Karin (público + admin) |
| phpMyAdmin | 8092 | Administración de BD (perfil `dev`) |
| Portal Ciudadano | 8093 | Denuncias generales (público + admin) |
| MySQL Ley Karin | 3307 | Base de datos `denuncias` |
| MySQL Ciudadano | 3308 | Base de datos `denuncias_generales` |

## Usuarios por Defecto

### Portal Ley Karin

| Usuario | Contraseña | Rol |
|---------|------------|-----|
| admin.denuncias | password | admin |
| comite.etica | password | admin |
| investigador1 | password | investigador |
| investigador2 | password | investigador |

### Portal Ciudadano

| Usuario | Contraseña | Rol |
|---------|------------|-----|
| admin.ciudadano | password | admin |
| coordinador | password | admin |
| revisor1 | password | investigador |
| revisor2 | password | investigador |

> ⚠️ **Todos los usuarios deben cambiar su contraseña en el primer inicio de sesión.**

## Encriptación

Campos encriptados con **libsodium** (AES-256-GCM):

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
├── config/                    # Configuración (app.php, database.php)
├── database/                  # SQL de inicialización Ley Karin
├── docker/                    # Entrypoint, php.ini
├── includes/                  # PHP compartido Ley Karin
│   ├── bootstrap.php          # Inicialización, funciones, constantes
│   ├── autenticacion.php      # Auth, sesiones, rate limiting
│   ├── encriptacion.php       # Libsodium encrypt/decrypt
│   ├── navbar_publica.php     # Navbar pública con portal switching
│   ├── barra_lateral.php      # Sidebar + topbar admin
│   └── ...
├── public/                    # DocumentRoot Ley Karin
│   ├── index.php              # Portal público
│   ├── nueva_denuncia.php     # Formulario de denuncia
│   ├── seguimiento.php        # Consulta de estado
│   ├── panel.php              # Dashboard admin
│   ├── denuncias_admin.php    # Listado de denuncias
│   ├── detalle_denuncia.php   # Detalle desencriptado
│   ├── reportes.php           # Reportes con CSV/PDF
│   ├── admin_usuarios.php     # Gestión de usuarios
│   ├── registro_actividad.php # Log de auditoría
│   └── ...
├── denuncias-generales/       # Portal Ciudadano (estructura espejo)
│   ├── config/
│   ├── database/
│   ├── docker/
│   ├── includes/
│   └── public/
├── landing/                   # Landing page selector
│   └── public/index.php
├── logs/
├── Dockerfile
├── docker-compose.yml
└── start.sh
```

## Variables de Entorno

Generadas automáticamente en `.env` por `start.sh`:

| Variable | Descripción |
|----------|-------------|
| `APP_PORT` | Puerto Portal Ley Karin (default: 8091) |
| `APP_GENERALES_PORT` | Puerto Portal Ciudadano (default: 8093) |
| `LANDING_PORT` | Puerto Landing Page (default: 8090) |
| `DB_PORT` | Puerto MySQL Ley Karin (default: 3307) |
| `DB_GENERALES_PORT` | Puerto MySQL Ciudadano (default: 3308) |
| `ENCRYPTION_KEY` | Clave encriptación Ley Karin |
| `GENERALES_ENCRYPTION_KEY` | Clave encriptación Portal Ciudadano |
| `DB_PASS` / `DB_ROOT_PASS` | Contraseñas MySQL |
| `HEALTH_CHECK_TOKEN` | Token para endpoint `/salud.php` |
| `SMTP_*` | Configuración de correo SMTP |

## Licencia

Uso interno — Empresa Portuaria Coquimbo.
