# Pruebas de Carga con k6

Este directorio contiene scripts de pruebas de carga con k6 para la aplicación.

## Prerequisitos

k6 está instalado en el contenedor Docker. No se requiere instalación local.

## Ejecutar Pruebas

Ejecutar pruebas dentro del contenedor Docker:
```bash
docker compose exec app k6 run tests/k6/health-load-test.js
docker compose exec app k6 run tests/k6/login-load-test.js
docker compose exec app k6 run tests/k6/audit-index-load-test.js
docker compose exec app k6 run tests/k6/audit-filter-users-load-test.js
docker compose exec app k6 run tests/k6/tenant-switch-load-test.js
```

Con variables de entorno:
```bash
docker compose exec app k6 run -e BASE_URL=http://localhost:8000 tests/k6/health-load-test.js
```

## Pruebas Disponibles

- `health-load-test.js` - Prueba el endpoint /health
- `login-load-test.js` - Prueba la autenticación de usuarios
- `audit-index-load-test.js` - Prueba el acceso a la página de auditoría
- `audit-filter-users-load-test.js` - Prueba el filtrado de auditoría por entidad Usuarios
- `tenant-switch-load-test.js` - Prueba la funcionalidad de cambio de tenant

## Escribir Nuevas Pruebas

- Crear nuevos archivos `.js` en este directorio
- Usar la API de JavaScript de k6 para peticiones HTTP y aserciones
- Configurar patrones de carga usando `options.stages`
- Definir umbrales de rendimiento usando `options.thresholds`
- Usar etiquetas para medir peticiones específicas: `tags: { name: 'mi_peticion' }`
