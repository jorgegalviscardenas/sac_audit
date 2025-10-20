# SAC Audit

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
