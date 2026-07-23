# Gestión de reservas de experiencias — Nalanda / Once For All

API backend en Symfony (PHP 8.2+) para una plataforma de experiencias con
proveedores externos: registro de experiencias, sesiones con aforo y precio, y
reservas de plazas con cancelación. DDD y arquitectura hexagonal reales
(puertos/adaptadores explícitos), persistencia en MySQL vía Docker.

**Estado actual:** entorno base + dominio modelado + casos de uso de
Application (registrar experiencia, crear sesión, reservar plazas, cancelar
reserva), todo probado con dobles en memoria (36 tests en verde, sin BD real
todavía). Aún sin persistencia real ni endpoints (Infrastructure) — se irá
ampliando en próximas sesiones. Este README crecerá con endpoints y más
detalle a medida que avance la implementación.

## Stack

- PHP 8.2+ (probado con 8.5), Symfony 7.4 (`symfony/skeleton`, sin frontend).
- Persistencia: MySQL 8 vía Docker, acceso con Doctrine DBAL (repositorios con
  SQL a mano, sin ORM ni atributos de entidad — ver `DECISIONES.md`).
- Servidor de aplicación: servidor embebido de PHP (`php -S`), no dockerizado.
  Solo se dockeriza MySQL.
- Tests: PHPUnit.

## Requisitos

- PHP >= 8.2 con `pdo_mysql`.
- Composer.
- Docker + Docker Compose (solo para el contenedor de MySQL).

## Puesta en marcha

```bash
make setup   # docker compose up (mysql) + composer install + crea BD dev y test
make serve   # arranca el servidor embebido de PHP en http://127.0.0.1:8000
```

`make setup` levanta el contenedor de MySQL, espera a que esté healthy, instala
las dependencias y crea las bases de datos `reservas_experiencias` (dev) y
`reservas_experiencias_test` (test). Es seguro volver a ejecutarlo.

Para parar el servidor arrancado con `make serve`: `make stop`.

### Otros comandos (`make help` los lista todos)

| Comando | Qué hace |
|---|---|
| `make test` | Corre la suite de PHPUnit |
| `make reset` | Destruye el contenedor/volumen de MySQL y lo recrea desde cero |
| `make down` | Para el contenedor de MySQL (conserva el volumen de datos) |

Si no tienes `make` disponible, los pasos equivalentes son:

```bash
docker compose up -d
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:database:create --if-not-exists --env=test
php -S 127.0.0.1:8000 -t public public/index.php
```

## Estructura del proyecto

```
src/
  Domain/          Entidades, value objects, servicios de dominio y puertos
    Repository/    Interfaces de persistencia (implementadas en Infrastructure)
    Notification/  Puerto de envío de notificaciones (email)
  Application/      Casos de uso que orquestan Domain + puertos
  Infrastructure/
    Persistence/    Repositorios Doctrine DBAL (SQL a mano)
    Notification/   Adaptador del puerto de notificaciones (p.ej. log)
    Controller/     Controladores REST
tests/
  Domain/               Tests de dominio puro (sin Symfony ni BD)
  Application/InMemory/ Casos de uso con dobles de test en memoria
  Functional/           Tests de API contra BD real
  Concurrency/          Tests de condiciones de carrera (overbooking)
```

## Decisiones de arquitectura

`DECISIONES.md` recoge, para cada decisión no trivial, la alternativa
descartada y el porqué. Se va actualizando conforme avanza la implementación.
