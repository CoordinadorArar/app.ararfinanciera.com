# Listado de suspensión de intereses — qué necesita el módulo

Acompaña a `plantilla-suspensiones-intereses.csv`, que ya trae las **281 operaciones** del archivo `terceros a excluir agosto 2026.xlsx` con su cédula, nombre y número de operación. Sólo hay que llenar las cuatro columnas de la derecha.

## Las columnas

| Columna | Obligatoria | Qué va |
|---|---|---|
| `cedula` | ya viene | No tocar. |
| `nombre` | ya viene | No tocar. |
| `operacion` | ya viene | No tocar. Es la llave contra la base. |
| `causal` | **sí** | Uno de tres valores exactos: `FALLECIMIENTO`, `INSOLVENCIA`, `COBRO_JURIDICO`. |
| `fecha_evento` | **sí** | Fecha en que ocurrió el hecho, en formato `AAAA-MM-DD`. |
| `observacion` | **sí** | Texto breve. Máximo 500 caracteres. |
| `soporte` | no | Referencia del documento de respaldo. Máximo 255 caracteres. |

Las tres causales son las que definió Gerencia en la política D-05: fallecimiento del deudor sin que la aseguradora pague, admisión a un proceso de insolvencia, y paso a cobro jurídico.

## Por qué la fecha del evento no es un dato administrativo

Es la pieza que más falta. La política D-06 dice que al suspender, **el interés ya causado se congela por el valor que tenía a la fecha del evento** y deja de crecer, sin reversarse contra el ingreso.

El módulo necesita esa fecha para saber a qué valor congelar: toma el interés que la operación tenía en el último corte anterior al evento. Sin fecha no hay valor, y sin valor la operación queda marcada pero **sin congelar** — el sistema la registra igual y la señala, pero no produce el efecto contable que se busca.

La fecha del evento sirve además para una segunda cosa. De las 281 operaciones, **56 ya no están en la base de factoring** y 15 no aparecen en ninguno de los tres cortes calculados. Para esas, la fecha del evento es la única referencia disponible para determinar la antigüedad de la mora, que es lo que define el porcentaje de deterioro. Es la salida más limpia a esa pregunta, porque no presume nada.

## Cuatro puntos que hay que definir antes de cargar

1. **Las que ya no están en factoring.** 56 de las 281. ¿Con qué antigüedad de mora se deterioran? Las opciones son presumir el rango de más de 720 días al 100 %, conservar la última antigüedad conocida del último corte en que aparecieron, o contarla desde la fecha del evento de este archivo. Dan cifras distintas.

2. **Qué saldo de SIESA es la base.** La instrucción es tomar la base del saldo de SIESA y no del de factoring. La base contable son capital vencido más intereses vencidos. Si el saldo de SIESA trae capital e interés juntos, hay que saber cómo se separan para no duplicar ni perder el interés congelado; si trae sólo capital, si el interés congelado se le suma.

3. **Las 19 que hoy están al día.** De las 225 que sí están en la base, 8 están en rango A y 11 corrientes, y su porcentaje de deterioro es cero. Con cualquier base, cero por cero sigue siendo cero. ¿La suspensión las reclasifica a un rango deteriorable, o se deterioran sólo las que ya tienen mora?

4. **Por qué desaparecieron de factoring.** La política dice que una operación castigada desaparece de la base. Si estas se castigaron, deteriorarlas contradice el castigo. Si están en SIESA y no en factoring, es la diferencia que la conciliación ya está prevista para explicar.

## Cómo se carga

Por comando, con validación completa antes de escribir una sola fila, upsert idempotente y registro en bitácora — el mismo tratamiento que recibió el libro de validación en la fase 4. Recargar el archivo corregido deja el resultado igual, no duplica.

Volver a entregar el archivo en Excel también sirve: lo que importa son las columnas, no el formato.
