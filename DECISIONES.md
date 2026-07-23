# Decisiones de arquitectura y diseño

Documento vivo, mismo formato que en la prueba técnica de Visiotech Security
(`Visiotech Security/prueba-pokemon/DECISIONES.md`): para cada decisión no
trivial, qué se eligió, qué alternativa se descartó y por qué. Se actualiza
conforme avanza la implementación.

## Fase 0 — Entorno base

### Hexagonal/DDD real, no la versión ligera de Visiotech

**Decisión:** puertos e interfaces explícitos en los boundaries (`Domain/Repository`,
`Domain/Notification`), separación estricta Domain → Application → Infrastructure,
sin dependencias de Symfony ni de la base de datos en `Domain`.

**Alternativa descartada:** arquitectura en 3 capas ligera sin puertos formales,
como en Visiotech.

**Por qué:** el enunciado de Nalanda exige explícitamente DDD y arquitectura
hexagonal ("no queremos una solución CRUD anémica"), a diferencia del de
Visiotech, que pedía sencillez y penalizaba la sobreingeniería. Mismo criterio
de fondo (no meter ceremonia sin beneficio), aplicado a un enunciado distinto.

### MySQL vía Docker, no SQLite

**Decisión:** MySQL 8 como único servicio dockerizado (`docker-compose.yml`); la
aplicación corre con el servidor embebido de PHP (`php -S`), no dockerizado.

**Alternativa descartada:** SQLite embebido (como en Visiotech), o dockerizar
también la aplicación PHP.

**Por qué:** el punto central del enunciado es la robustez ante reservas
concurrentes (overbooking). SQLite serializa las escrituras a nivel de archivo,
lo que ocultaría la condición de carrera en vez de demostrarla resuelta —
MySQL/InnoDB con locking real es necesario para que el test de concurrencia
signifique algo. MySQL sí necesita un servidor corriendo (no es embebido como
SQLite), de ahí Docker. La aplicación se queda fuera de Docker para que quien
evalúe la prueba solo necesite `docker compose up` + Composer, sin tener que
construir una imagen PHP.

### Doctrine DBAL, sin ORM ni atributos de entidad

**Decisión:** `doctrine/dbal` + `doctrine/doctrine-bundle` (para el wiring del
servicio `Connection` y `DATABASE_URL`), sin `doctrine/orm`. Los repositorios de
Infrastructure escribirán SQL a mano.

**Alternativa descartada:** `doctrine/orm` con entidades anotadas con atributos
(`#[ORM\Entity]`).

**Por qué:** mismo criterio que en Visiotech — el dominio debe quedar puro
(sin `Collection`, sin lazy-loading, sin acoplarse a cómo el ORM espera
hidratar relaciones). El repositorio con SQL a mano es el patrón básico para
mantener esa pureza, no sobreingeniería.

### `config/packages/doctrine.yaml` sin sección `orm`

**Decisión:** al no instalar `doctrine/orm`, se eliminó la sección `orm:` que
la receta de `doctrine-bundle` genera por defecto (fallaba `cache:clear` con
*"The doctrine/orm package is required when the doctrine.orm config is set"*).
Solo queda configurado `dbal` (con `server_version: '8.0'` fijado explícitamente,
ya que sin URL de producción real Doctrine no puede autodetectarlo).

### Base de datos de test separada, con permisos concedidos vía init script

**Decisión:** `docker/mysql/init.sql` (montado en
`/docker-entrypoint-initdb.d/`) crea `reservas_experiencias_test` y concede
privilegios al usuario `app` sobre ella, además de `reservas_experiencias`
(creada automáticamente por `MYSQL_DATABASE`).

**Por qué:** `MYSQL_DATABASE`/`MYSQL_USER` de la imagen oficial de MySQL solo
conceden privilegios sobre la base de datos indicada en `MYSQL_DATABASE`. La
suite de tests usa `dbname_suffix: '_test'` (convención de Symfony/Doctrine),
así que sin este script `doctrine:database:create --env=test` fallaría con
*"Access denied"*. Verificado recreando el contenedor desde cero
(`docker compose down -v && docker compose up -d`): ambas bases quedan creadas
y accesibles sin pasos manuales.

### Estructura de carpetas con subcarpetas de puertos explícitas en `Domain`

**Decisión:** `src/Domain/Repository/` y `src/Domain/Notification/` como
subcarpetas dedicadas a interfaces (puertos), separadas del resto de `Domain`
(entidades, value objects, servicios de dominio).

**Por qué:** a diferencia de Visiotech (dominio plano, "package by feature"),
aquí el enunciado exige hexagonal real y va a haber varios puertos con más de
un adaptador plausible (repositorios en memoria para tests vs. DBAL para
producción; notificación por log vs. SMTP real) — agrupar los puertos por rol
ayuda a ver de un vistazo el contrato entre Domain e Infrastructure, que es
precisamente lo que la arquitectura hexagonal quiere hacer explícito.

## Próximos pasos

Modelado del dominio (`Experience`, `Session`, `Booking`, `Money`,
`BookingCancellationPolicy`) y estrategia de concurrencia (UPDATE condicional
atómico) — ver plan acordado en `../notas.md`. Se documentará aquí con el
mismo formato conforme se implemente cada regla de negocio.
