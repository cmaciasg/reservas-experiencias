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

## Fase 1 — Modelado del dominio

### `Session` no contiene la lista de `Booking`, `Booking` referencia `sessionId`

**Decisión:** `Booking` es su propio aggregate root, con `sessionId` como
simple referencia (string), no una colección `Session::bookings`.

**Alternativa descartada:** `Session` con una colección de `Booking` como
parte de su propio aggregate.

**Por qué:** una sesión con miles de reservas cargando toda la colección en
memoria para reservar una plaza más sería absurdo, y además rompería el
punto central del ejercicio: la concurrencia se resuelve con un UPDATE
atómico sobre `available_seats`, no recorriendo/recalculando una lista de
reservas en memoria. Mantener `availableSeats` como contador explícito en
`Session` (en vez de derivarlo de `capacity - count(bookings confirmados)`)
es lo que hace posible ese UPDATE atómico de una sola sentencia.

### `Session::decreaseAvailableSeats`/`increaseAvailableSeats` existen, pero no son la solución de concurrencia real

**Decisión:** el aggregate `Session` sí tiene métodos que mutan
`availableSeats` en memoria (con sus invariantes: no bajar de 0, no superar
`capacity`). Se usan en los tests de dominio y los usará el repositorio en
memoria (`tests/Application/InMemory`, próximo paso).

**Por qué no es contradictorio con "el UPDATE atómico es la solución real":**
el puerto `SessionRepository` (`src/Domain/Repository/SessionRepository.php`)
ya declara `reserveSeats()`/`releaseSeats()` como operaciones atómicas
independientes — el adaptador MySQL (Infrastructure, próximo paso) las
implementará con una única sentencia `UPDATE ... WHERE available_seats >= :n`
condicional, **sin** cargar el `Session` en memoria primero. Los métodos del
aggregate solo sirven para expresar la regla de negocio en el modelo y para
que el doble en memoria de los tests de Application se comporte igual sin
necesitar SQL de verdad. Es el mismo patrón dual ya aplicado a la regla de
sesión duplicada (regla expresada en dos sitios, por dos motivos distintos:
legibilidad del dominio vs. seguridad real de concurrencia).

### `SessionAlreadyStartedException` no se lanza desde `Session`, se comprueba con `hasStartedAt()`

**Decisión:** `Session` expone `hasStartedAt(DateTimeImmutable $now): bool`,
una simple consulta sin efectos secundarios. La excepción
`SessionAlreadyStartedException` la lanzará el caso de uso de Application
(próximo paso) tras comprobarlo, y el UPDATE atómico de `reserveSeats()`
repetirá la misma condición (`start_date > NOW()`) en el `WHERE` como defensa
en profundidad (la sesión pudo empezar entre que se lee y que se reserva).

**Por qué:** igual que con las plazas, la comprobación "en memoria" no puede
ser la única fuente de verdad bajo concurrencia — solo la condición dentro
del propio UPDATE atómico lo es. `hasStartedAt()` sirve para dar un error
rápido y claro (409) sin llegar a intentar el UPDATE, no para garantizar la
regla por sí sola.

### `DuplicateSessionDateException`, `NotEnoughSeatsAvailableException`, etc. viven en `Domain/Exception/`, no una por aggregate

**Decisión:** subcarpeta `src/Domain/Exception/` con un tipo por regla de
negocio violada, en vez de anidar cada excepción junto a su aggregate o
lanzar `\DomainException` genéricas.

**Por qué:** mismo patrón que `src/Application/Exception` en Visiotech.
Permite a Infrastructure (controladores, próximo paso) mapear cada tipo a un
código HTTP concreto (404/409) con un único `catch` por tipo, sin parsear
mensajes de texto.

### `BookingCancellationPolicy` sin inyección de la ventana de 24h configurable

**Decisión:** las 24 horas son una constante de clase
(`CANCELLATION_WINDOW_HOURS`), no un parámetro de constructor.

**Alternativa descartada:** inyectar el número de horas por constructor para
"flexibilidad".

**Por qué:** mismo criterio que se aplicó (y se revirtió) con
`EffectivenessProvider` en Visiotech — es una regla de negocio fija del
enunciado, no un valor que varíe por proveedor/entorno ni que necesite una
segunda implementación real. Parametrizarla sería configurabilidad que nadie
ha pedido. Los tests fijan fechas concretas alrededor de la frontera (24h/23h)
en vez de necesitar inyectar una ventana distinta.

### Precio en céntimos (`Money`), sin librería externa

**Decisión:** value object propio con un único `int` (céntimos), sin
`moneyphp/money` ni similar.

**Por qué:** solo hace falta sumar y multiplicar por un entero (nº de
plazas) para calcular `totalPrice`, sin conversión de divisas ni
redondeos complejos — una dependencia externa sería sobreingeniería para
esa necesidad.

## Fase 2 — Application (casos de uso)

### `IdGenerator` como puerto de Domain, en vez de IDs autoincrementales de MySQL

**Decisión:** interfaz `Domain\IdGenerator` (`generate(): string`), inyectada
en los servicios de Application, que la usan para dar identidad al agregado
**antes** de guardarlo.

**Alternativa descartada:** IDs autoincrementales de MySQL, como en
Visiotech (`INTEGER PRIMARY KEY AUTOINCREMENT` de SQLite).

**Por qué:** los factory methods del dominio (`Experience::register(id, ...)`,
`Session::schedule(id, ...)`, `Booking::confirm(id, ...)`) construyen el
agregado completo de una vez, con su id incluido — es el estilo que ya se
había decidido en el modelado del dominio (fase 1). Con autoincremental de
BD, el id solo existe *después* del `INSERT`, lo que obligaría a romper ese
estilo (crear el objeto sin id, guardarlo, y luego "rellenarle" el id que
devuelve la BD) solo para ahorrarse una interfaz. La implementación real
(adaptador con `Symfony\Component\Uid`) llegará en la fase de Infrastructure;
en esta fase los tests usan un `SequentialIdGenerator` en memoria
(`tests/Application/InMemory/SequentialIdGenerator.php`, ids
`"id-1"`, `"id-2"`...).

### `Psr\Clock\ClockInterface` (PSR-20) en vez de `new \DateTimeImmutable()` disperso

**Decisión:** los 4 servicios de Application reciben un
`Psr\Clock\ClockInterface` por constructor y le piden `now()` cuando lo
necesitan, en vez de instanciar la hora actual ellos mismos. Se añaden
`psr/clock` (la interfaz, estándar PHP-FIG) y `symfony/clock` (implementación
real + `MockClock` para tests).

**Alternativa descartada:** cada servicio llama a `new \DateTimeImmutable()`
directamente; o se inventa una interfaz `Domain\Clock` propia.

**Por qué:** este ejercicio tiene *tres* reglas que dependen de "ahora"
(sesión no en el pasado, sesión no empezada, ventana de cancelación de 24h) —
sin una fuente de tiempo inyectable, testear los bordes exactos (23h vs. 24h,
un segundo antes de que empiece la sesión) sería no determinista o obligaría
a manipular relojes del sistema. `psr/clock` es el estándar ya existente para
esto (PSR-20): usarlo evita inventar una interfaz propia para un problema ya
resuelto por PHP-FIG, y `symfony/clock` aporta `MockClock` listo para tests
sin escribir un doble a mano. El propio `Domain` (`Session`, `Booking`,
`BookingCancellationPolicy`) sigue sin depender de esto — sus métodos siguen
recibiendo `$now` como parámetro explícito; solo Application conoce el reloj.

### `reserveSeats()`/`releaseSeats()`: el `InMemorySessionRepository` de test NO demuestra la concurrencia

**Decisión:** el doble en memoria (`tests/Application/InMemory/InMemorySessionRepository.php`)
implementa el puerto `SessionRepository` mutando el agregado en memoria — es
monohilo, no hay dos peticiones reales compitiendo por la vez.

**Por qué se documenta explícitamente esta limitación:** para no dar una
falsa sensación de "la concurrencia ya está probada" con estos tests. Estos
tests de Application solo comprueban que el servicio llama al puerto
correctamente y reacciona bien a su resultado (reserva o rechaza). La prueba
real de que no hay overbooking bajo concurrencia de verdad vendrá con el
adaptador MySQL (`UPDATE ... WHERE available_seats >= :n`) y un test dedicado
en `tests/Concurrency` que lanza peticiones en paralelo de verdad.

### Excepciones "no encontrado" en `Application/Exception`, no en `Domain/Exception`

**Decisión:** `ExperienceNotFoundException`, `SessionNotFoundException`,
`BookingNotFoundException` vivan en `src/Application/Exception/`.

**Por qué:** son fallos de *búsqueda* (orquestación: "¿existe esto en el
repositorio?"), no violaciones de una regla de negocio del propio agregado —
a diferencia de `PastSessionDateException` o `BookingAlreadyCancelledException`,
que si viven en `Domain/Exception` porque las lanza el propio agregado al
proteger su invariante. Mismo patrón que `src/Application/Exception` en
Visiotech.

### Orden de comprobaciones en `CancelBookingService`

**Decisión:** primero se comprueba si la reserva ya está cancelada
(`BookingAlreadyCancelledException`), y solo después la ventana de 24h
(`CancellationWindowExpiredException`).

**Por qué:** es un chequeo de estado más fundamental que uno temporal — no
tiene sentido preguntar "¿todavía se puede cancelar a tiempo?" de algo que ya
está cancelado. El propio `Booking::cancel()` repite la comprobación de
"ya cancelada" como defensa en profundidad (el agregado protege su invariante
pase lo que pase, no solo cuando el servicio se acuerda de comprobarlo antes).

## Próximos pasos

Infrastructure: repositorios DBAL (con el UPDATE atómico real contra MySQL),
adaptador de notificación (log), adaptador `IdGenerator` con
`Symfony\Component\Uid`, controladores REST, y el test de concurrencia real
en `tests/Concurrency` — ver plan acordado en `../notas.md`.
