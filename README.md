# SAC Audit

Sistema de registro de auditoría de alto rendimiento para aplicaciones multi-tenant construido con Laravel. Este sistema está diseñado para manejar datos de auditoría a gran escala con soporte para bases de datos particionadas, seeding masivo optimizado y capacidades integrales de monitoreo.

## Stack

### Backend
- **PHP**: 8.2+
- **Laravel**: 12.0
- **PostgreSQL**: 16+ (Arquitectura de base de datos dual)
  - BD Operacional: Datos principales de la aplicación
  - BD de Auditoría: Logs de auditoría particionados (por mes)

### Frontend
- **Tailwind CSS**: 4.0
- **Vite**: 7.0
- **Alpine.js**: (vía componentes Laravel Blade)

### DevOps & Herramientas
- **Docker & Docker Compose**: Entorno de desarrollo containerizado
- **Supervisor**: Gestión de procesos para colas y workers
- **k6**: Pruebas de carga y benchmarking de rendimiento
- **Laravel Telescope**: Depuración y monitoreo de la aplicación
- **Laravel Pail**: Visualización de logs en tiempo real
- **PHPStan/Larastan**: Análisis estático

### Testing & Calidad
- **PHPUnit**: 11.5+
- **Laravel Pint**: Estilo de código
- **k6**: Pruebas de rendimiento y carga

## Requisitos Previos

- **Docker**: 20.10+
- **Docker Compose**: 2.0+
- **Git**: 2.30+

## Instalación

### 1. Clonar el repositorio
```bash
git clone <repository-url>
cd sac_audit
```

### 2. Construir e iniciar los contenedores Docker
```bash
docker compose up -d --build
```

Esto iniciará:
- **Contenedor App** (`buk_app`): PHP 8.2 + Composer + k6 (puerto 7400)
- **BD Operacional** (`buk_operational_db`): PostgreSQL 16 (puerto 7500)
- **BD de Auditoría** (`buk_audit_db`): PostgreSQL 16 (puerto 7501)

### 3. Instalar dependencias de PHP
```bash
docker compose exec app composer install
```

### 4. Instalar dependencias de Node
```bash
docker compose exec app npm install
docker compose exec app npm run build
```

### 5. Configurar el entorno
```bash
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
```

### 6. Ejecutar migraciones de base de datos para el estado inicial (todo en una misma base de datos)
```bash
docker compose exec app php artisan migrate --path=database/migrations/01_initial
```

### 8. Cargar datos en las tablas de operaciones y en las de auditoria. Actualizar los tenant ids por los que corresponden
#### Agregar 10 tenants
```bash
docker compose exec app php artisan seed:tenants-csv 10 --start-date=2024-10-01 --end-date=2025-10-13
```

#### Visualizar tenants creados
```bash
docker compose exec app php artisan tinker --execute="dump(App\Models\Tenant::all()->pluck('id', 'name'));"
```
#### Agregar 10.000 usuarios por tenant. O correr solo el primero.
```bash
docker compose exec app php artisan seed:users-csv 10000 --tenant=69f77ab5-3b0c-4280-af92-2e6969e8ec9c --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=e726b14e-dcb8-4340-aadb-aa06b271271e --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=47e6b03c-70fa-436a-b2a8-5e6675d59f1b --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=495dd781-5f28-44c8-808b-ff60ddf9f858 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=c4f1cc09-c507-451b-8d88-f2abc565c93a --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=49d27cd7-41a5-4e81-8352-7a6a4d37d8a6 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=a0ebbd91-65ad-4c7b-9430-13bfa1ac04c4 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=4f4924fd-9f77-46b0-b331-36d3e81ea2f6 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=eb1f1b2e-8a4a-4cb5-ae29-5173a721fc81 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:users-csv 10000 --tenant=85787ba8-2859-45fa-86b8-dbca3ffba125 --start-date=2024-10-01 --end-date=2025-10-13
```

#### Agregar 100 cursos por tenant. O correr solo el primero.
```bash
docker compose exec app php artisan seed:courses-csv 100 --tenant=69f77ab5-3b0c-4280-af92-2e6969e8ec9c --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=e726b14e-dcb8-4340-aadb-aa06b271271e --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=47e6b03c-70fa-436a-b2a8-5e6675d59f1b --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=495dd781-5f28-44c8-808b-ff60ddf9f858 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=c4f1cc09-c507-451b-8d88-f2abc565c93a --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=49d27cd7-41a5-4e81-8352-7a6a4d37d8a6 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=a0ebbd91-65ad-4c7b-9430-13bfa1ac04c4 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=4f4924fd-9f77-46b0-b331-36d3e81ea2f6 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=eb1f1b2e-8a4a-4cb5-ae29-5173a721fc81 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:courses-csv 100 --tenant=85787ba8-2859-45fa-86b8-dbca3ffba125 --start-date=2024-10-01 --end-date=2025-10-13
```
#### Agregar 10.000 inscripciones a cursos por tenant. O correr solo el primero.
```bash
docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=69f77ab5-3b0c-4280-af92-2e6969e8ec9c --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=e726b14e-dcb8-4340-aadb-aa06b271271e --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=47e6b03c-70fa-436a-b2a8-5e6675d59f1b --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=495dd781-5f28-44c8-808b-ff60ddf9f858 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=c4f1cc09-c507-451b-8d88-f2abc565c93a --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=49d27cd7-41a5-4e81-8352-7a6a4d37d8a6 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=a0ebbd91-65ad-4c7b-9430-13bfa1ac04c4 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=4f4924fd-9f77-46b0-b331-36d3e81ea2f6 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=eb1f1b2e-8a4a-4cb5-ae29-5173a721fc81 --start-date=2024-10-01 --end-date=2025-10-13

docker compose exec app php artisan seed:course-enrollments-csv 10000 --tenant=85787ba8-2859-45fa-86b8-dbca3ffba125 --start-date=2024-10-01 --end-date=2025-10-13
```

### 9. Ejecutar migraciones para arquitectura dual-database

Migración a la nueva arquitectura (1 base de datos para operaciones y otra para auditoría):
```bash
docker compose exec app php artisan migrate --path=database/migrations/02_incoming_changes
```

### 10. Ejecutar seeders para usuarios del sistema y grupos de trabajo
```bash
# Seed de usuarios del sistema
docker compose exec app php artisan db:seed --class=UserSystemSeeder

# Seed de grupos de trabajo
docker compose exec app php artisan db:seed --class=WorkGroupSeeder
```

### 11. Acceder a la aplicación
- **Aplicación**: http://localhost:7400
- **Laravel Telescope**: http://localhost:7400/telescope
- **BD Operacional**: localhost:7500
- **BD de Auditoría**: localhost:7501

#### Usuarios del sistema disponibles
Después de ejecutar el seeder de usuarios del sistema, puedes acceder con las siguientes credenciales:

| Email | Contraseña | Nombre Completo |
|-------|-----------|-----------------|
| user_sac1@example.com | `password` | User SAC 1 |
| user_sac2@example.com | `password` | User SAC 2 |
| user_sac3@example.com | `password` | User SAC 3 |
| user_sac4@example.com | `password` | User SAC 4 |
| user_sac5@example.com | `password` | User SAC 5 |

### 12.Checklist de Validación
Después de la instalación, verifica que:

- [ ] Ambas bases de datos están corriendo: `docker compose ps`
- [ ] La aplicación carga en http://localhost:7400
- [ ] Login funciona con credenciales de prueba
- [ ] Telescope muestra requests en `/telescope`
- [ ] Tests pasan: `docker compose exec app php artisan test`
- [ ] k6 health check funciona: `docker compose exec app k6 run tests/k6/health-load-test.js`

#### Validar arquitectura dual-database
```bash
# Verificar tablas operacionales
docker compose exec operational_db psql -U postgres -d operational -c "\dt"

# Verificar tablas de auditoría particionadas
docker compose exec audit_db psql -U postgres -d audit -c "\d+ tenant_audits"
```

#### Validar particionamiento
```bash
# Ver particiones mensuales creadas
docker compose exec buk_audit_db psql -U postgres -d buk_audit -c "SELECT tablename FROM pg_tables WHERE tablename LIKE 'tenant_audits_%' ORDER BY tablename;"
```

### 13. (Opcional) Ejecutar pruebas

#### Pruebas unitarias y de integración
```bash
docker compose exec app php artisan test
```

#### Pruebas de carga con k6
```bash
# Prueba del endpoint /health
docker compose exec app k6 run tests/k6/health-load-test.js

# Prueba de autenticación
docker compose exec app k6 run tests/k6/login-load-test.js

# Prueba del índice de auditoría
docker compose exec app k6 run tests/k6/audit-index-load-test.js

# Prueba de filtrado por entidad
docker compose exec app k6 run tests/k6/audit-filter-users-load-test.js

# Prueba de cambio de tenant
docker compose exec app k6 run tests/k6/tenant-switch-load-test.js
```

### 14. (Opcional) Análisis de código

#### Ejecutar Laravel Pint (formateo de código)
```bash
docker compose exec app ./vendor/bin/pint
```

#### Ejecutar PHPStan (análisis estático)
```bash
docker compose exec app ./vendor/bin/phpstan analyse
```

---

## Comandos de Seeding de Base de Datos

Todos los comandos de seeding usan `COPY FROM STDIN` de PostgreSQL para un rendimiento óptimo. Cada entidad genera automáticamente 7 registros de auditoría (1 CREATE + 6 UPDATE).

### Opciones Comunes

Todos los comandos soportan las siguientes opciones:

- `--start-date=YYYY-MM-DD` - Fecha de inicio para las marcas de tiempo de creación de registros
- `--end-date=YYYY-MM-DD` - Fecha de fin para las marcas de tiempo de creación de registros
- `--keep-csv` - Mantener archivos CSV después de importar (útil para depuración)
- `--audit-connection=operational|audit` - Conexión de base de datos para tablas de auditoría (predeterminado: operational)

Al usar `--start-date` y `--end-date`, los registros se distribuyen uniformemente entre los meses. Por ejemplo, 120 registros desde el 1 de septiembre hasta el 31 de octubre crearán 60 registros en septiembre y 60 en octubre, cada uno con marcas de tiempo aleatorias dentro de sus respectivos meses.

### Opciones de Conexión de Auditoría

La opción `--audit-connection` permite elegir dónde se almacenan los registros de auditoría:

- `operational` (predeterminado): Almacena registros de auditoría en la base de datos operacional (tablas no particionadas)
- `audit`: Almacena registros de auditoría en la base de datos de auditoría (tablas particionadas por mes)

Al usar `--audit-connection=audit`, el comando automáticamente usa un enfoque de tabla temporal para manejar tablas particionadas correctamente.

### 1. Seed de Tenants

```bash
# Seed de 100 tenants con 700 registros de auditoría
php artisan seed:tenants-csv 100

# Distribuir 120 tenants entre septiembre y octubre (60 por mes)
php artisan seed:tenants-csv 120 --start-date=2024-09-01 --end-date=2024-10-31

# Seed a base de datos de auditoría con tablas particionadas
php artisan seed:tenants-csv 100 --audit-connection=audit

# Mantener archivos CSV para inspección
php artisan seed:tenants-csv 100 --keep-csv
```

**Crea**:
- Tenants en tabla `tenants` (DB operacional)
- 7 registros de auditoría por tenant en tabla `tenant_audits` (1 CREATE + 6 UPDATE)

---

### 2. Seed de Usuarios

```bash
# Seed de 3.6 millones de usuarios con 25.2 millones de registros de auditoría
php artisan seed:users-csv 3600000 --tenant=YOUR-TENANT-UUID

# Distribuir usuarios en un rango de fechas
php artisan seed:users-csv 1000 --tenant=YOUR-TENANT-UUID --start-date=2024-01-01 --end-date=2024-12-31

# Seed a base de datos de auditoría con tablas particionadas
php artisan seed:users-csv 1000 --tenant=YOUR-TENANT-UUID --audit-connection=audit
```

**Crea**:
- Usuarios en tabla `users` (DB operacional)
- 7 registros de auditoría por usuario en tabla `user_audits` (1 CREATE + 6 UPDATE)

**Requisitos**: UUID de tenant válido

---

### 3. Seed de Cursos

```bash
# Seed de 50,000 cursos con 350,000 registros de auditoría
php artisan seed:courses-csv 50000 --tenant=YOUR-TENANT-UUID

# Distribuir cursos en un rango de fechas
php artisan seed:courses-csv 500 --tenant=YOUR-TENANT-UUID --start-date=2024-01-01 --end-date=2024-06-30

# Seed a base de datos de auditoría con tablas particionadas
php artisan seed:courses-csv 500 --tenant=YOUR-TENANT-UUID --audit-connection=audit
```

**Crea**:
- Cursos en tabla `courses` (DB operacional)
- 7 registros de auditoría por curso en tabla `course_audits` (1 CREATE + 6 UPDATE)

**Requisitos**: UUID de tenant válido

---

### 4. Seed de Inscripciones a Cursos

```bash
# Seed de 10 millones de inscripciones con 70 millones de registros de auditoría
php artisan seed:course-enrollments-csv 10000000 --tenant=YOUR-TENANT-UUID

# Distribuir inscripciones en un rango de fechas
php artisan seed:course-enrollments-csv 5000 --tenant=YOUR-TENANT-UUID --start-date=2024-09-01 --end-date=2024-12-31

# Seed a base de datos de auditoría con tablas particionadas
php artisan seed:course-enrollments-csv 5000 --tenant=YOUR-TENANT-UUID --audit-connection=audit
```

**Crea**:
- Inscripciones en tabla `course_enrollments` (asigna aleatoriamente usuarios existentes a cursos) (DB operacional)
- 7 registros de auditoría por inscripción en tabla `course_enrollment_audits` (1 CREATE + 6 UPDATE)

**Requisitos**:
- UUID de tenant válido
- Usuarios y cursos existentes para el tenant (hacer seed de usuarios y cursos primero)

---

## Orden Recomendado de Seeding

1. **Tenants** - Crear tenants primero
2. **Usuarios** - Crear usuarios para cada tenant
3. **Cursos** - Crear cursos para cada tenant
4. **Inscripciones** - Crear inscripciones (vincula usuarios con cursos)

## Ejemplos de Distribución de Fechas

### Ejemplo 1: Simular 6 meses de crecimiento de usuarios
```bash
# Crear 6,000 usuarios de julio a diciembre (1,000 por mes)
php artisan seed:users-csv 6000 --tenant=YOUR-TENANT-UUID \
  --start-date=2024-07-01 --end-date=2024-12-31
```

### Ejemplo 2: Datos históricos con inscripciones
```bash
# Paso 1: Crear usuarios durante el año
php artisan seed:users-csv 12000 --tenant=YOUR-TENANT-UUID \
  --start-date=2024-01-01 --end-date=2024-12-31

# Paso 2: Crear cursos
php artisan seed:courses-csv 100 --tenant=YOUR-TENANT-UUID \
  --start-date=2024-01-01 --end-date=2024-03-31

# Paso 3: Crear inscripciones durante el año
php artisan seed:course-enrollments-csv 50000 --tenant=YOUR-TENANT-UUID \
  --start-date=2024-01-01 --end-date=2024-12-31
```

## Funcionalidad Común (Trait)

Todos los comandos usan el `CsvSeedCommandTrait` que provee:

- **Validación de tenant** - Asegura que el tenant existe antes del seeding
- **COPY FROM STDIN** - Usa el método de importación masiva más rápido de PostgreSQL
- **Soporte de tablas particionadas** - Enfoque automático de tabla temporal para tablas de auditoría particionadas
- **Distribución mensual** - Distribuye registros uniformemente entre meses
- **Marcas de tiempo aleatorias** - Genera marcas de tiempo realistas dentro de rangos de fechas
- **Seguimiento de progreso** - Barras de progreso visuales para conjuntos de datos grandes
- **Generadores de datos aleatorios** - Nombres, nombres de empresas, títulos de cursos
- **Gestión de archivos CSV** - Genera archivos CSV delimitados por tabuladores, limpiados automáticamente a menos que se use `--keep-csv`

## Notas de Rendimiento

- **Mejor para**: Conjuntos de datos grandes (10K+ registros)
- **Velocidad**: 2-5x más rápido que declaraciones INSERT masivas usando el comando COPY de PostgreSQL
- **Memoria**: Genera datos en lotes por período mensual para gestionar la memoria eficientemente
- **Almacenamiento**: Archivos CSV almacenados temporalmente en `storage/app/seeder_data/`
- **Conexiones de base de datos**:
  - Tablas principales (tenants, users, courses, enrollments): Siempre usan conexión `operational`
  - Tablas de auditoría: Usan conexión `operational` o `audit` según la opción `--audit-connection`
- **Métodos de importación**:
  - Tablas no particionadas: COPY FROM STDIN directo (más rápido)
  - Tablas particionadas (DB de auditoría): Tabla temporal + INSERT SELECT (requerido para tablas particionadas)

## Arquitectura de Base de Datos

### Base de Datos Operacional
- Contiene tablas de entidades principales (tenants, users, courses, enrollments)
- Opcionalmente contiene tablas de auditoría (no particionadas)

### Base de Datos de Auditoría
- Contiene tablas de auditoría particionadas (particionadas por mes de `created_at`)
- Enruta automáticamente datos a la partición correcta
- Optimizada para consultas de datos de auditoría de series temporales

### Comandos de Migración

```bash
# Ejecutar migraciones para crear tablas
php artisan migrate

# Migrar datos de auditoría de DB operacional a DB de auditoría con particionamiento
php artisan migrate --path=database/migrations/2025_10_16_110609_migrate_tenant_audit_table_to_audit_db.php
```

La migración automáticamente:
- Crea tabla particionada en DB de auditoría
- Crea particiones mensuales basadas en el rango de datos + 3 particiones futuras
- Exporta datos de DB operacional usando chunking (eficiente en memoria)
- Importa usando enfoque de tabla temporal (funciona con tablas particionadas)
- Valida integridad de datos mediante comparación de checksum de IDs
