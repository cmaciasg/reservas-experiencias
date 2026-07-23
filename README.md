# Gestión de reservas de experiencias — Nalanda / Once For All

API backend en Symfony (PHP 8.2+) para una plataforma de experiencias con
proveedores externos: registro de experiencias, sesiones con aforo y precio, y
reservas de plazas con cancelación. DDD y arquitectura hexagonal reales
(puertos/adaptadores explícitos), persistencia en MySQL vía Docker.

**Estado actual:** API funcional de extremo a extremo contra MySQL real —
dominio, casos de uso, persistencia (Doctrine DBAL con UPDATE atómico
anti-overbooking), controladores REST y notificación por log. 53 tests en
verde (dominio puro, casos de uso con dobles en memoria, funcionales contra
BD real, y un test de concurrencia con procesos del sistema operativo reales
que demuestra que no hay overbooking).

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
php bin/console app:db:init
php bin/console app:db:init --env=test
php -S 127.0.0.1:8000 -t public public/index.php
```

## Endpoints

Sin autenticación (ids de proveedor/usuario inventados en el payload, tal
como pide el enunciado). Payloads y respuestas en JSON, snake_case.

| Acción | Endpoint | Body |
|---|---|---|
| Registrar experiencia | `POST /api/experiences` | `{"provider_id", "title", "description"}` |
| Consultar experiencia | `GET /api/experiences/{id}` | — |
| Crear sesión | `POST /api/experiences/{id}/sessions` | `{"date" (ISO 8601), "capacity", "price_cents"}` |
| Consultar sesión | `GET /api/sessions/{id}` | — |
| Reservar plazas | `POST /api/sessions/{id}/bookings` | `{"user_id", "seats"}` |
| Cancelar reserva | `POST /api/bookings/{id}/cancel` | — |
| Consultar reserva | `GET /api/bookings/{id}` | — |
| Reservas de una sesión | `GET /api/sessions/{id}/bookings` | — |

Códigos de error: `404` (recurso no encontrado), `409` (conflicto de
negocio: sesión duplicada, sin plazas, sesión ya empezada, reserva ya
cancelada, fuera de ventana de cancelación), `400` (payload inválido, p.ej.
fecha de sesión en el pasado).

Ejemplo de flujo completo:

```bash
curl -X POST http://127.0.0.1:8000/api/experiences \
  -H 'Content-Type: application/json' \
  -d '{"provider_id":"provider-1","title":"City Bike Tour","description":"A guided bike tour."}'
# => {"id":"...","provider_id":"provider-1","title":"City Bike Tour",...}

curl -X POST http://127.0.0.1:8000/api/experiences/{id}/sessions \
  -H 'Content-Type: application/json' \
  -d '{"date":"2026-08-10T18:00:00+00:00","capacity":5,"price_cents":2000}'
# => {"id":"...","available_seats":5,...}

curl -X POST http://127.0.0.1:8000/api/sessions/{id}/bookings \
  -H 'Content-Type: application/json' \
  -d '{"user_id":"user-1","seats":2}'
# => {"id":"...","status":"confirmed","total_price_cents":4000,...}

curl -X POST http://127.0.0.1:8000/api/bookings/{id}/cancel
# => {"id":"...","status":"cancelled",...}
```

### Colección de Postman

`postman/reservas-experiencias.postman_collection.json` cubre el flujo
completo (registrar → crear sesión → reservar → listar reservas de la
sesión → cancelar) más los casos de error (409 duplicado, 409 ya
cancelada, 404). Cada petición guarda el id que necesita la siguiente en
variables de colección (`experience_id`, `session_id`, `booking_id`), así
que se puede ejecutar tal cual, en orden, sin copiar/pegar nada a mano —
con el "Collection Runner" de Postman o con
`npx newman run postman/reservas-experiencias.postman_collection.json`
(con `make serve` arrancado). Solo hace falta importarla en Postman
(File → Import) y tener el servidor levantado en `http://127.0.0.1:8000`
(variable `base_url` de la colección, editable si usas otro puerto/host).

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
