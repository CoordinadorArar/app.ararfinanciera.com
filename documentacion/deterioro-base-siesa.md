# Deterioro de Cartera — Base de deterioro tomada de SIESA para todas las operaciones

**Fecha:** 16 de octubre de 2026
**Solicitado por:** Gerencia y Contabilidad
**Commit:** `e0c0c8b` en la rama `CoordDev`
**Estado:** implementado y aprobado por QA; pendiente de recalcular cortes y de validar contra datos reales

---

## 1. Qué se pidió

Hasta ahora, la base del deterioro salía de **factoring**. Era el capital vencido más el interés vencido de las cuotas, más la prórroga vencida de SIESA. La única excepción eran las operaciones con **marca de suspensión**, que ya tomaban su base de SIESA.

Gerencia y Contabilidad pidieron que la base de **todas las operaciones** se tome de lo vencido en **SIESA**. Las pantallas deben seguir mostrando el saldo de factoring y el de SIESA lado a lado, para que se puedan comparar.

---

## 2. La regla nueva

| Caso | Base de deterioro | `origen_base` |
|---|---|---|
| La operación tiene saldo atribuido en SIESA (por `OPE`, por tercero o por nota) | capital vencido SIESA + interés vencido SIESA + prórroga vencida SIESA | `SIESA` |
| La operación no está en SIESA por ningún camino | capital vencido + interés vencido de factoring + prórroga vencida SIESA (**respaldo**) | `FACTORING` |
| Suspendida con origen SIESA | la base congelada es la misma base SIESA | `SIESA` |
| Suspendida con origen FACTORING | capital vencido de factoring + interés congelado + prórroga (igual que antes) | `FACTORING` |

Una operación tiene saldo en SIESA cuando `capital_vencido_siesa` no es nulo. Es la misma condición con la que el enlace ya llenaba las columnas de SIESA.

Lo que **no** cambia:
- Los días de mora y el rango siguen saliendo de factoring.
- `capital_vencido`, `interes_vencido` y las columnas `*_siesa` se siguen calculando y guardando igual.
- `valor_nominal_siesa`, que es el tope fiscal R, sigue igual.
- La conciliación C-3 sigue igual.

---

## 3. Cambios por archivo

### `project/app/Models/Deterioro.php`
- **`sqlEnlazarSaldoSiesa()`**:
  - Calcula `base_deterioro` con la regla de la sección 2. Usa las mismas expresiones que `capital_vencido_siesa` (`OPE` frente a tercero o nota) y que `interes_vencido_siesa`, más la prórroga.
  - Escribe `origen_base` para **todas** las operaciones.
- **`sqlAplicarSuspensiones()`**:
  - Calcula `base_congelada` a partir de `origen_base`: con `SIESA` toma `base_deterioro`; con `FACTORING` usa la fórmula de siempre.
  - Este paso ya no escribe `origen_base`.
- **`verificarCuadres()`**:
  - **C-BASE**: compara solo las operaciones con `origen_base = 'FACTORING'` contra el vencido de sus cuotas (sin las repetidas) más su prórroga. Queda en N/A si todas las operaciones son SIESA.
  - **C-SIESA-BASE**: cubre todas las operaciones con origen SIESA, no solo las suspendidas. Compara `ISNULL(base_congelada, base_deterioro)` contra sus componentes de SIESA.
  - **C-DUPLICADAS-BASE**: suma solo las cuotas repetidas de las operaciones con origen FACTORING, porque en las SIESA esas cuotas no afectan la base.
  - **C-SUSPENSION**: se mantiene solo para FACTORING.

### `project/app/Support/DeterioroExportador.php`
- La columna **ORIGEN BASE** del Excel se llena para todas las operaciones. Antes solo se llenaba en las suspendidas.

### `js/deterioro-detalle.js` y `project/resources/views/deterioro/detalle-operaciones.blade.php`
- La celda **Base** siempre muestra la fuente: «SIESA», «factoring · respaldo», «SIESA · suspendida» o «factoring · suspendida».
- En **Capital vencido** e **Interés vencido**:
  - Se mantiene el valor SIESA con el de factoring debajo cuando difieren.
  - Si no hay valor SIESA, aparece «sin SIESA».
- Los tooltips y los encabezados explican la regla nueva.
- El modal de cuotas avisa que, si la base es SIESA, las cuotas de factoring son solo de referencia.

### `js/deterioro-suspensiones.js` y `project/resources/views/deterioro/ayuda.blade.php`
- Se actualizaron los textos de ayuda, de confirmación de marcas y de rótulos. Ahora dicen que la base sale de SIESA con o sin suspensión, y que factoring es solo el respaldo.

---

## 4. Verificación

- `php -l` de `Deterioro.php` y `DeterioroExportador.php`: sin errores.
- `node --check` de los dos JS: OK.
- PHPUnit: 85 tests y 793 assertions, todo OK. Ningún test cubre específicamente esta lógica.
- QA revisó el código y lo aprobó en la segunda ronda. La primera la rechazó solo por textos de interfaz, que ya se corrigieron.
- **Pendiente:** no se ha recalculado ningún corte con la regla nueva, así que todavía no se conoce el efecto en pesos.

---

## 5. Cómo aplicar a los cortes

- La regla solo afecta los cortes que se **recalculen** después de desplegar el código. Los cortes calculados antes conservan la base de factoring.
- Para el corte de septiembre, **recalcularlo, no eliminarlo**:
  - Recalcular limpia y rehace todo lo calculado, y conserva las explicaciones de las partidas de conciliación y la clasificación de las bajas.
  - Eliminar el corte borra esas dos cosas.
- Si el corte está cerrado, primero hay que reabrirlo con su motivo.
- Antes de recalcular, exportar el Excel para comparar el deterioro total antes y después.
- Después de recalcular, revisar que **C-BASE** y **C-SIESA-BASE** queden en OK.

---

## 6. Puntos a confirmar con Contabilidad

1. **Operaciones con documento `OPE` en saldo cero**:
   - Quedan con origen SIESA y base = interés vencido + prórroga, aunque factoring tenga capital vencido.
   - En cambio, por la vía de nota, un capital en cero hace que la operación pase a factoring.
   - Hay que confirmar si «no tiene saldo en SIESA» debe significar lo mismo por los dos caminos.
2. **Matriz del Resumen**: el capital vencido más el interés vencido de factoring ya no suman la Base, porque la Base ahora es SIESA. Es lo esperado.
3. **Cortes antiguos**: se calcularon antes del cambio. Ahí las operaciones sin suspender se ven como «factoring · respaldo» aunque tengan saldo en SIESA. Es un rótulo heredado; se corrige al recalcular.
4. **C-SIESA-BASE casi siempre va a cuadrar**:
   - Compara columnas que se escriben en el mismo paso, así que solo detectaría que otro paso posterior sobrescriba la base.
   - Si se quiere un control más fuerte, se puede rearmar la base directamente desde `det_corte_saldo_siesa`.
