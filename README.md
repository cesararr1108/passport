# Passport - Rifas / Dinámicas del evento

PHP 5.4 + SQL Server (extensión `mssql_*`).

## Instalación
1. Ejecutar `sql/rifas_dinamicas.sql` en `passport_roma_v1` (crea las tablas de ganadores y espejo).
2. Copiar `models/`, `controllers/`, `views/`, `resources/pdf/` sobre el proyecto
   (`models/funciones.php` y `resources/fpdf/` no se versionan: se usan los existentes).
3. Ajustar montos/horas en `models/RifasConfig.php`.

## Dinámicas (models/RifasConfig.php)
| Dinámica | Horario | Grupo 100 (GRUPO1) | Demás grupos |
|---|---|---|---|
| #1 Ruleta | hasta 12:00 | ≥ $100.000 en 5 labs | igual |
| #2 iPhone 18 Pro | hasta 12:00 | total ≥ $2.000.000 y ≥ $100.000 en 10 labs; +1 boleta por cada $1.000.000 sobre $3.000.000 | total ≥ $8.000.000 y ≥ $1.000.000 en 5 labs |
| #3 iPhone 18 / bono $3M | desde 18:00 | total ≥ $6.000.000 y ≥ $100.000 en 20 labs | total ≥ $15.000.000 y ≥ $1.000.000 en 10 labs |
| Electrodomésticos | desde 18:00 | total ≥ $1.000.000 | igual |

- Las dinámicas de la mañana evalúan sólo pedidos con `FECHA_HORA < 12:00`.
  Pasado el corte se genera automáticamente el **espejo** (`T_RIFA_ESPEJO*`) y desde ese momento
  la mañana se evalúa contra esa foto.
- Cada cliente gana una sola vez cada dinámica (`UNIQUE (DINAMICA, PASSPORT)`). Quien ganó en la
  mañana sí participa en las de la tarde. Si antes del corte compra más y gana boletas adicionales
  (#2 grupo 100), el botón "Sumar boletas" las actualiza.
- `T_RIFA_DINAMICAS_GANADORES` guarda el ganador, la compra al momento de ganar, las boletas
  ganadas, las redimidas (`BOLETAS_REDIMIDAS`, `REDIMIDO`, fecha y usuario de redención).
- Boletas: `resources/pdf/pdf_boleta_dinamica.php?id=<ID>` (sólo lectura; reimprimir no altera datos).
