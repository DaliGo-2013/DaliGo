# DaliGo

Aplicación web desarrollada con Laravel 12 sobre PHP 8.3, preparada para desplegarse en un hosting compartido (HostGator). Incluye autenticación de usuarios (registro, inicio de sesión y recuperación de contraseña) mediante Laravel Breeze.

> 📋 **Antes de trabajar en el proyecto:** lee y mantén [`CLAUDE.md`](CLAUDE.md) — la guía viva con las *formas correctas de hacer las cosas* y la **bitácora de errores y soluciones**. Si tienes una dificultad o un error y lo resuelves, **regístralo ahí antes de cerrar tu tarea** (aplica a todos los colaboradores, humanos e IA).

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
