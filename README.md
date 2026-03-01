# Portal de Denuncias EPCO

Canal de denuncias seguro y confidencial para la Empresa Portuaria Coquimbo, en cumplimiento de la **Ley Karin (Nº 21.643)**.

## Características

- **Encriptación AES-256**: Todos los datos sensibles (descripciones, nombres, contactos) se almacenan encriptados con libsodium. Quien acceda a la base de datos directamente solo verá datos binarios ilegibles.
- **Denuncias anónimas**: Los denunciantes pueden reportar sin identificarse.
- **Sistema de usuarios separado**: Usuarios independientes del portal de Soporte TI (admin, investigador, viewer).
- **Panel de investigación**: Solo admin e investigadores pueden desencriptar y leer las denuncias.
- **Seguimiento público**: Los denunciantes pueden consultar el estado con un código único.
- **Registro de actividad completo**: Auditoría de todos los accesos y acciones.
- **Dockerizado**: Despliegue con un solo comando.

## Requisitos

- Docker y Docker Compose v2+
- Puerto 8091 disponible (configurable)

## Inicio Rápido

```bash
bash start.sh
```

Esto detectará puertos libres, generará las credenciales y levantará todos los servicios.

## URLs

| Servicio | URL | Descripción |
|----------|-----|-------------|
| Portal público | http://localhost:8091 | Página de inicio, formulario de denuncia, seguimiento |
| Panel admin | http://localhost:8091/iniciar_sesion | Acceso para admin/investigadores |
| phpMyAdmin | http://localhost:8092 | Solo en perfil `dev` |

## Usuarios Iniciales

| Usuario | Contraseña | Rol |
|---------|------------|-----|
| admin.denuncias | password | admin |
| comite.etica | password | admin |
| investigador1 | password | investigador |
| investigador2 | password | investigador |

> ⚠️ **Cambiar todas las contraseñas en producción.**

## Encriptación

Todos los campos sensibles se encriptan con **libsodium** (equivalente AES-256-GCM) antes de almacenarse en la base de datos:

- Descripción de los hechos
- Datos del denunciante (nombre, email, teléfono, departamento)
- Datos del denunciado (nombre, departamento, cargo)
- Testigos
- Lugar del incidente
- Resolución
- Notas de investigación
- Nombres de archivos adjuntos

La clave de encriptación se configura mediante la variable de entorno `ENCRYPTION_KEY`.

## Estructura

```
denuncias-docker/
├── config/          # Configuración PHP (app, database)
├── database/        # SQL de inicialización
├── docker/          # Entrypoint, php.ini
├── includes/        # PHP compartido (bootstrap, auth, encriptación)
├── logs/            # Logs de la aplicación
├── public/          # DocumentRoot Apache
│   ├── index.php          # Portal público
│   ├── nueva_denuncia.php # Formulario de denuncia
│   ├── seguimiento.php    # Consulta de estado
│   ├── panel.php          # Dashboard admin
│   ├── denuncias_admin.php# Listado de denuncias
│   ├── detalle_denuncia.php# Detalle desencriptado
│   └── ...
├── Dockerfile
├── docker-compose.yml
└── start.sh
```

## Licencia

Uso interno - Empresa Portuaria Coquimbo.
