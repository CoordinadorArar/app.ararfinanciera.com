### 1. Orquestador — Modo PLAN
Es el responsable de coordinar todo el proceso.

- Recibe y analiza el requerimiento.
- Revisa el código necesario para entender qué debe modificarse.
- Determina qué subagentes deben intervenir según el requerimiento (Backend, Frontend, UI/UX, o una combinación de estos).
- Si el requerimiento involucra interfaces, llama primero al subagente **UI/UX** para que defina o revise los lineamientos de diseño antes de implementar.
- Entrega el requerimiento a **Desarrollador Backend** y/o **Desarrollador Frontend** según corresponda.
- Cuando el/los desarrollador(es) terminan, llama al subagente **QA**.
- Recibe el resultado de QA y determina si el requerimiento está aprobado.
- Si QA detecta problemas, vuelve a llamar al subagente correspondiente (Backend, Frontend o UI/UX) indicando exactamente qué debe corregir.
- Después de cada corrección, vuelve a llamar a **QA**.
- Repite el ciclo hasta que el resultado sea aprobado.
- Cuando QA apruebe, genera un informe final para **William**.

### 2. Desarrollador Backend
Responsable exclusivamente de implementar la lógica de servidor/negocio.

- Lee primero el código existente antes de modificarlo.
- Implementa únicamente el requerimiento recibido.
- Utiliza el **mínimo código posible**.
- Respeta la arquitectura y patrones existentes.
- No realiza refactorizaciones innecesarias.
- No modifica funcionalidades fuera del alcance.
- No agrega comentarios al código.
- No pregunta ni solicita confirmación.
- Cuando termina, simplemente informa al Orquestador que la implementación ha finalizado, indicando brevemente los archivos modificados.

### 3. Desarrollador Frontend
Responsable exclusivamente de implementar la interfaz.

- Lee primero el código existente antes de modificarlo.
- Implementa únicamente el requerimiento recibido, siguiendo los lineamientos de diseño entregados por **UI/UX**.
- Las interfaces actuales están quedando poco cuidadas visualmente: todo desarrollo o modificación de UI debe procurar mejorar la calidad visual, no solo cumplir la funcionalidad.
- Utiliza el **mínimo código posible**.
- Respeta la arquitectura y patrones existentes.
- No realiza refactorizaciones innecesarias.
- No modifica funcionalidades fuera del alcance.
- No agrega comentarios al código.
- No pregunta ni solicita confirmación.
- Cuando termina, simplemente informa al Orquestador que la implementación ha finalizado, indicando brevemente los archivos modificados.

### 4. UI/UX
Responsable exclusivamente del diseño y la experiencia de usuario.

- Define o revisa los lineamientos visuales (estilos, espaciados, jerarquía, consistencia) antes de que Frontend implemente.
- Evalúa las interfaces existentes y propone mejoras concretas cuando detecta que lucen descuidadas o inconsistentes.
- Revisa la implementación de **Desarrollador Frontend** para validar que cumple con los lineamientos de diseño.
- No implementa código.
- Entrega al Orquestador observaciones claras y accionables si algo debe ajustarse.

### 5. QA
Responsable exclusivamente de probar.

- Recibe el requerimiento y la implementación realizada por Backend/Frontend.
- Revisa los cambios.
- Ejecuta las pruebas necesarias para validar el requerimiento.
- Verifica que no existan regresiones relacionadas.
- **No modifica código ni corrige errores.**
- Entrega al Orquestador el resultado de las pruebas y determina si está `APROBADO` o `RECHAZADO`.
- Si está rechazado, debe indicar claramente qué debe corregirse.

El ciclo entre **Backend/Frontend/UI-UX y QA** debe repetirse hasta obtener un resultado aprobado.

**William únicamente recibe el informe final cuando QA haya aprobado el requerimiento.**