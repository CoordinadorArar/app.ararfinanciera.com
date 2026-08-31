# Deterioro de Cartera — Fase 1, resultados de la validación

**Fecha:** 31 de agosto de 2026
**Corte replicado:** 31 de julio de 2026
**Libro de contraste:** `DETERIORO CARTERA A 31 DE JULIO DE 2026.xlsx`

---

## 1. Resultado

**El módulo reproduce el deterioro contable del libro con diferencia cero, celda por celda.**

| Concepto | Excel | Módulo | Diferencia |
|---|---:|---:|---:|
| Operaciones | 2.106 | 2.106 | 0 |
| Interés vencido | 692.519.031,00 | 692.519.031,00 | **0,00** |
| **Deterioro contable** | **1.753.977.960,01** | **1.753.977.960,01** | **0,00** |

Desglose por rango, contra la fila 8 de la hoja `DETERIORO`:

| Rango | % | Excel | Módulo | Diferencia |
|---|---:|---:|---:|---:|
| A | 0 % | 0,00 | 0,00 | 0,00 |
| B | 8 % | 15.720.730,08 | 15.720.730,08 | 0,00 |
| C | 23 % | 21.627.046,05 | 21.627.046,05 | 0,00 |
| D | 53 % | 89.305.478,06 | 89.305.478,06 | 0,00 |
| E | 78 % | 301.742.468,82 | 301.742.468,82 | 0,00 |
| F | 100 % | 1.325.582.237,00 | 1.325.582.237,00 | 0,00 |

Interés vencido por rango (fila 26) y capital vencido por producto y rango (columnas W a AA de las filas 16, 19 y 22): **las 21 celdas coinciden exactamente**.

### Distribución por rango

Coincide operación por operación, que es el control que delata cualquier error en `DAYS360`:

| Clasificación | Operaciones | Cuotas |
|---|---:|---:|
| Corriente | 1.647 | 96.447 |
| A · 0 a 30 | 35 | 2.058 |
| B · 31 a 90 | 47 | 2.110 |
| C · 91 a 180 | 41 | 1.952 |
| D · 181 a 360 | 80 | 2.921 |
| E · 361 a 720 | 115 | 4.531 |
| F · 721 en adelante | 141 | 5.008 |
| **Total** | **2.106** | **115.027** |

---

## 2. Las dos diferencias de capital, explicadas

El capital total del módulo es 15.419.137.327,00 y el del libro 15.750.605.909,50. La diferencia de **331.468.582,50** se descompone en dos partidas, ninguna de las cuales es un defecto:

### a) Prórrogas y reservas: 332.206.387,50

La hoja `DIFERENCIAS` cuadra el saldo de SIESA contra el del sistema de factoring:

| Celda | Concepto | Valor |
|---|---|---:|
| `D1739` | Saldo SIESA | 15.750.605.909,50 |
| `E1739` | Saldo del sistema de factoring | 15.418.399.522,00 |
| `F1739` | Diferencia | 332.206.387,50 |
| `G1739` | Prórrogas y reservas que la explican | 332.206.387,50 |
| `H1739` | Residuo | 0,00 |

RN-11 inyecta ese total como una fila adicional en `DETERIORO!F3`, clasificada Corriente/A, de modo que suma capital sin generar deterioro. **Esa fila no existe en la fase 1**: entra en la fase 6, junto con la conciliación. Se localiza íntegra en la columna V del producto FACTORING.

### b) Deriva del origen desde que se armó el libro: 737.805,00

El libro tomó 15.418.399.522,00 de capital; la tabla origen tiene hoy 15.419.137.327,00. La diferencia son **737.805,00**, coherente con que la tabla haya ganado tres filas desde entonces: el documento técnico reporta 115.024 y 115.608 filas, y hoy hay 115.027 y 115.611.

No es un error del cálculo: es que `ResumenVigentesClientes` se recargó después de generar el Excel. Al repetir la marcha en paralelo conviene refrescar libro y tabla en la misma corrida.

---

## 3. Controles de cuadre

Los cinco controles de la fase dan cero en los dos cortes calculados:

| Código | Control | Diferencia |
|---|---|---:|
| `C-CUOTAS` | Cuotas del detalle contra la suma consolidada por operación | 0,00 |
| `C-CAPITAL` | Capital del detalle contra el consolidado por operación | 0,00 |
| `C-INTERES` | Interés del detalle contra el consolidado por operación | 0,00 |
| `C-PARTIC` | Capital corriente más vencido contra el capital total | 0,00 |
| `C-BASE` | Base de deterioro contra capital vencido más interés vencido | 0,00 |

Los tres controles restantes de RN-12 dependen de SIESA y del cálculo fiscal: entran en las fases 2 y 6.

---

## 4. Desempeño

Objetivo del documento técnico: menos de 60 segundos para 115 mil filas.

| Paso | Julio 2026 |
|---|---:|
| Congelar paramétricas | 334 ms |
| Extracción de cartera | 2.618 ms |
| Derivadas por cuota | 2.947 ms |
| Enlace con el corte anterior | 1.210 ms |
| Consolidación por operación | 368 ms |
| Deterioro contable | 128 ms |
| **Total** | **8,0 s** |

Con margen de sobra. La extracción es una sola sentencia cross-database porque `Modulos_Faico` está en el mismo servidor.

---

## 5. Correcciones aplicadas frente al libro

| Defecto | Tratamiento |
|---|---|
| **D-A** · el primer `SI` compara contra `$V$14` (texto `"0 A 31"`) en vez de `$V$15` (código `"A"`) | Corregido: la unión compara el código de rango contra el código. Sin efecto numérico hoy, porque el rango A provisiona 0 %, y así se comprobó: la diferencia es exactamente cero. |
| **D-B** · `SI(Y(I3=$Y$15)…)` compara la base en pesos contra la letra `"D"` | Corregido por la misma vía. Sin efecto numérico. |
| **Hallazgo 7** · `Tabla final!Q` calcula la variación como `capital mes anterior − IdOperacion` | Corregido: `variacion_capital = capital_mes_anterior − saldo_capital`. La columna del libro es inservible, así que no hay contra qué contrastar. |

---

## 6. Hallazgo que corrige el documento técnico

El documento (§4.2 y RN-02) da por hecho que la clasificación cae en `"Corriente"` cuando falta `FecInicialMora`. **Ninguna fila la tiene nula.**

El procedimiento origen la deriva como el vencimiento de la cuota pendiente más antigua de la operación. Para una operación al día esa fecha está en el futuro, `DAYS360` sale **negativo**, el `BUSCARV` aproximado falla y el `SI.ERROR` devuelve `"Corriente"`. **El disparador es el valor negativo, no el nulo.**

Son 1.647 de las 2.106 operaciones: casi el 78 %. Implementarlo como el documento lo describe habría dejado esas operaciones sin clasificar.

---

## 7. Qué se probó

- **Motor**: pipeline completo sobre los cortes de junio y julio de 2026, con datos reales de producción.
- **Consultas de presentación**: listado de cortes, matriz producto × rango, cuadres, detalle por operación con sus cuatro filtros, y descenso a las cuotas de una operación.
- **Pantallas**: las tres responden 200 y renderizan.
- **Endpoints**: los ocho devuelven JSON correcto. Las validaciones responden bien ante corte duplicado, periodo de origen que no corresponde, campo obligatorio ausente y corte inexistente.
- **Permisos**: con un usuario de rol *Asesor Comercial*, las tres vistas responden **403** y los cinco endpoints POST también. Es una mejora sobre el resto del sitio, donde los POST solo verifican autenticación.

### Limitación del entorno de desarrollo

La verificación se hizo con dos parches locales, ninguno de los cuales toca el código del proyecto ni llega al servidor:

1. **Conector de SQL Server.** Laravel aplica siempre `PDO::ATTR_STRINGIFY_FETCHES`, que `pdo_sqlsrv` rechaza en PHP 8.2. Se sustituyó la construcción del PDO en un script de pruebas. En el servidor, con PHP más antiguo, no hace falta.
2. **Carbon.** La versión instalada (2.62.0) es incompatible con PHP 8.2: `Carbon::setLastErrors()` declara `array` y PHP 8.2 le pasa `false`. Se parchó **`vendor/`**, que está fuera del repositorio. Esto rompe `php artisan` en esta máquina, no en el servidor. El arreglo definitivo es `composer update nesbot/carbon` cuando se actualice PHP.

La máquina de desarrollo corre PHP 8.2.12 y `composer.json` declara `^7.3|^8.0`. **Conviene confirmar la versión del servidor** antes de dar por buena esta divergencia.

---

## 8. Pendientes

1. Confirmar la versión de PHP del servidor.
2. Solicitar `ArarFinanciera_Dev`: las 14 tablas se crearon directamente en producción por no haber alternativa. Son nuevas y aisladas, y `down()` las elimina, pero la fase 2 no debería trabajar así.
3. Reducir los permisos de `consultaweb`, que además de `CONTROL`, `ALTER`, `INSERT` y `DELETE` sobre la tabla origen, pertenece al rol de servidor `dbcreator`.
4. Rotar la contraseña: ya no está en el código, pero sí en el `.docx` de alcance y en el `.xlsx` que circulan por correo.
5. Preguntar a Cartera si los parches de las operaciones 2343, 3893 y 3894 embebidos en el procedimiento origen siguen vigentes.
6. Refrescar el libro y la tabla origen en la misma corrida para eliminar los 737.805 de deriva en la próxima validación.
