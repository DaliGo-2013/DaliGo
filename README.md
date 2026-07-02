# DaliGo

Aplicación web desarrollada con Laravel 12 sobre PHP 8.3, preparada para desplegarse en un hosting compartido (HostGator). Incluye autenticación de usuarios (registro, inicio de sesión y recuperación de contraseña) mediante Laravel Breeze.

> 📋 **Antes de trabajar en el proyecto, leer en este orden:**
> 1. [`PROYECTO_DALIGO.md`](PROYECTO_DALIGO.md) — la **biblia**: *qué* construir y *por qué* (16 módulos M01–M16, reglas de negocio, Gantt).
> 2. [`docs/RUTA-MAESTRA.md`](docs/RUTA-MAESTRA.md) — **dónde estamos y qué sigue**: el tablero vivo del proyecto (paso a paso E0–E13, avance, bloqueos). El estado vive SOLO aquí.
> 3. [`HANDOFF.md`](HANDOFF.md) — el manual técnico: *cómo está hecho* lo construido y *cómo se despliega*.
> 4. [`CLAUDE.md`](CLAUDE.md) — guía viva con las *formas correctas de hacer las cosas* y la **bitácora de errores y soluciones**. Si tienes una dificultad o un error y lo resuelves, **regístralo ahí antes de cerrar tu tarea** (aplica a todos los colaboradores, humanos e IA).
> 5. [`docs/GUIA-DALIGO.md`](docs/GUIA-DALIGO.md) — tutorial de las 4 capas (organización ruta→controlador→modelo→vista, roles/permisos, catálogo+sync Bsale, despliegue). Punto de entrada para quien recién llega al proyecto.
>
> 🔁 **Para trabajar día a día:** [`docs/PROTOCOLO-SESION.md`](docs/PROTOCOLO-SESION.md) — cómo retomar el proyecto en 10 minutos desde cualquier computador y la checklist de cierre de sesión.

## Mapa de la documentación

| Documento | Para qué sirve | Quién lo lee |
|---|---|---|
| [`PROYECTO_DALIGO.md`](PROYECTO_DALIGO.md) | La spec: módulos, reglas de negocio, Gantt | Todos |
| [`docs/RUTA-MAESTRA.md`](docs/RUTA-MAESTRA.md) | Estado vivo: qué está hecho, qué sigue, avance % | Dirección + devs/IAs |
| [`docs/DECISIONES.md`](docs/DECISIONES.md) | Decisiones abiertas/tomadas (D-xxx) con briefs para enviar | Dirección + devs/IAs |
| [`docs/PROTOCOLO-SESION.md`](docs/PROTOCOLO-SESION.md) | Cómo retomar y cerrar una sesión de trabajo | Devs/IAs |
| [`docs/BITACORA-SESIONES.md`](docs/BITACORA-SESIONES.md) | Crónica de cada sesión (proceso de creación de la app) | Todos |
| [`HANDOFF.md`](HANDOFF.md) | Manual técnico: stack, infra, deploy, cómo quedó implementado | Devs/IAs |
| [`CLAUDE.md`](CLAUDE.md) | Convenciones + bitácora de errores/soluciones | Devs/IAs |
| [`docs/delegacion/`](docs/delegacion/PROTOCOLO-DELEGACION.md) | Protocolo y plantillas para la IA de cPanel/QA | Dirección + IA de QA |
| [`docs/qa/`](docs/qa/README.md) | Evidencias de QA archivadas | Todos |
| [`docs/planes/`](docs/planes/README.md) | Planes finos por módulo (con sello de vigencia) | Devs/IAs |
| [`docs/GUIA-DALIGO.md`](docs/GUIA-DALIGO.md) · [`docs/BSALE_API.md`](docs/BSALE_API.md) | Tutorial de arquitectura · referencia API Bsale | Devs/IAs |

## Stack tecnológico

- PHP 8.3
- Laravel 12
- Laravel Breeze (autenticación con Blade)
- Vite para la compilación de assets
- Base de datos relacional (configurable mediante migraciones)

## Requisitos previos

- PHP 8.3 o superior
- Composer
- Node.js y npm
- Un motor de base de datos (MySQL / MariaDB)

## Instalación

Clonar el repositorio:

```bash
git clone https://github.com/DaliGo-2013/DaliGo.git
cd DaliGo
```

Instalar dependencias de PHP y de frontend:

```bash
composer install
npm install
```

Crear el archivo de entorno y generar la clave de la aplicación:

```bash
cp .env.example .env
php artisan key:generate
```

Configurar las credenciales de la base de datos en el archivo `.env` y ejecutar las migraciones:

```bash
php artisan migrate
```

Compilar los assets y levantar el entorno de desarrollo:

```bash
npm run dev
php artisan serve
```

## Despliegue

El repositorio incluye un script `deploy.sh` orientado al despliegue en hosting compartido (HostGator). Antes de desplegar, compila los assets para producción con `npm run build` y configura las variables de entorno de producción en el servidor.

## Estructura del proyecto

Sigue la estructura estándar de un proyecto Laravel: el código de la aplicación en `app/`, las rutas en `routes/`, las vistas Blade en `resources/views/`, la configuración en `config/` y las migraciones en `database/migrations/`.

## Licencia

Proyecto privado de uso interno. Todos los derechos reservados.
