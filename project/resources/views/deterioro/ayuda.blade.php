@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
@section('content')
@php
    $pantallas = [
        'deterioro-cortes' => 'Cortes',
        'deterioro-resumen' => 'Resumen',
        'deterioro-detalle-operaciones' => 'Detalle',
        'deterioro-contable-fiscal' => 'Contable contra fiscal',
        'deterioro-evolucion' => 'Evolución',
        'deterioro-suspensiones' => 'Intereses suspendidos',
        'deterioro-conciliacion' => 'Conciliación',
        'deterioro-controles' => 'Controles y cierre',
    ];
    $solicitado = request('volver');
    $pedido = explode('?', is_array($solicitado) ? '' : (string) $solicitado, 2);
    $ruta = ltrim($pedido[0], '/');
    $rotuloVolver = 'Cortes';
    $destinoVolver = url('/deterioro-cortes');
    if (isset($pantallas[$ruta])) {
        parse_str($pedido[1] ?? '', $parametros);
        $corte = $parametros['corte'] ?? '';
        $corte = is_array($corte) ? '' : (string) $corte;
        $rotuloVolver = $pantallas[$ruta];
        $destinoVolver = url('/' . $ruta) . (ctype_digit($corte) ? '?corte=' . $corte : '');
    }
@endphp
<div class="det-contenedor" id="divAyuda">

    <div class="det-encabezado">
        <div class="det-titular">
            <nav class="det-migas">
                <a href="{{ url('/deterioro-cortes') }}">Deterioro de cartera</a>
                <span>&rsaquo;</span>
                <span class="actual">Guía de uso</span>
            </nav>
            <h4 class="det-titulo">Guía de uso del módulo</h4>
            <p class="det-subtitulo">Qué pregunta contable resuelve cada pantalla, de dónde sale cada número y con qué otra cifra tiene que cuadrar</p>
        </div>
        <div>
            <a class="btn btn-secondary btn-sm" href="{{ $destinoVolver }}"><i class="fas fa-arrow-left"></i>&nbsp; Volver a {{ $rotuloVolver }}</a>
        </div>
    </div>

    <div class="det-ayuda">

        <ul class="det-ayuda-indice" id="indiceAyuda">
            <li><a href="#ayuda-1"><span class="num">01</span><span class="rot">Qué reemplaza</span></a></li>
            <li><a href="#ayuda-2"><span class="num">02</span><span class="rot">Siete palabras</span></a></li>
            <li><a href="#ayuda-3"><span class="num">03</span><span class="rot">El recorrido del dato</span></a></li>
            <li><a href="#ayuda-4"><span class="num">04</span><span class="rot">Las pantallas</span></a></li>
            <li><a href="#ayuda-5"><span class="num">05</span><span class="rot">Cómo amarran las cifras</span></a></li>
            <li><a href="#ayuda-6"><span class="num">06</span><span class="rot">Lo que no hace</span></a></li>
            <li><a href="#ayuda-7"><span class="num">07</span><span class="rot">Decisiones pendientes</span></a></li>
            <li><a href="#ayuda-8"><span class="num">08</span><span class="rot">Preguntas frecuentes</span></a></li>
            <li><a href="#ayuda-anexo"><span class="num">A</span><span class="rot">Anexo</span></a></li>
        </ul>

        <div>

            <section class="det-ayuda-seccion" id="ayuda-1">
                <h5 class="det-ayuda-tit"><span class="num">Sección 1</span>Qué es el módulo y qué reemplaza</h5>
                <p class="det-ayuda-lede">Hoy el deterioro se calcula en un libro de Excel de 68 MB por corte, con siete entradas que se digitan a mano cada mes: las fechas de corte y de comparación, los saldos de SIESA por cliente, las prórrogas y reservas, la cartera de SIESA por operación, el deterioro del mes anterior por rango, la provisión fiscal acumulada y las notas de gestión.</p>
                <div class="det-ayuda-texto">
                    <p>El módulo parte <strong>del mismo cálculo y de las mismas reglas del libro</strong>, con las políticas que Contabilidad definió después (interés de prórroga en la base, tope por valor nominal, congelamiento de intereses), y cambia cuatro cosas:</p>
                </div>
                <div class="det-panel">
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>El libro</th><th>El módulo</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto">Los datos se pegan</td><td class="det-texto">Los datos se leen de la fuente: factoring y SIESA, por conexión directa</td></tr>
                                <tr><td class="det-texto">El archivo se sobrescribe</td><td class="det-texto">Cada corte queda <strong>congelado</strong>: una foto que no cambia aunque cambien las bases</td></tr>
                                <tr><td class="det-texto">Los cuadres se revisan a ojo</td><td class="det-texto">Los cuadres <strong>se arman solos</strong> y bloquean el cierre si fallan</td></tr>
                                <tr><td class="det-texto">No hay rastro de quién cambió qué</td><td class="det-texto">Cada acto queda en bitácora, con autor, fecha y dirección IP</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="det-aviso info">
                    <i class="fas fa-circle-check mt-1"></i>
                    <div><strong>El punto de partida fue la réplica al centavo.</strong> El módulo reprodujo el corte de julio de 2026 contra el libro, cifra por cifra. Las diferencias posteriores con el libro obedecen a políticas definidas por Contabilidad y están documentadas.</div>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-2">
                <h5 class="det-ayuda-tit"><span class="num">Sección 2</span>Siete palabras que conviene tener claras antes de entrar</h5>
                <div class="det-ayuda-texto">
                    <h6 class="det-ayuda-sub">Corte</h6>
                    <p>Un mes. Es la unidad de todo: se crea, se calcula, se revisa y se cierra. Todas las pantallas del módulo, salvo la primera, son el detalle <strong>de un corte</strong>, para que nunca haya duda de qué mes se está mirando. Cómo se llega a cada una está en <a href="#ayuda-4-0">Cómo se entra y quién puede hacer qué</a>.</p>

                    <h6 class="det-ayuda-sub">Corriente y vencido</h6>
                    <p>Cada cuota se clasifica según su fecha de vencimiento contra la fecha de corte. Si ya venció, su saldo es <em>vencido</em>; si no, es <em>corriente</em>. Esta partición es la que explica casi todas las preguntas de cuadre.</p>

                    <h6 class="det-ayuda-sub">Base de deterioro</h6>
                    <p><code>capital vencido + interés vencido + interés de prórroga</code>. <strong>Solo la parte vencida.</strong> Excluye a propósito el saldo de administración y el interés de mora.</p>

                    <h6 class="det-ayuda-sub">Interés de prórroga</h6>
                    <p>El saldo de prórroga <strong>vencido</strong> que reporta SIESA por operación. Por política contable se trata como interés y por eso <strong>entra a la base de deterioro</strong> y se deteriora con el porcentaje del rango de la operación, hasta el 100 % en rango F. <strong>No es la prórroga del control C-1</strong>, que es el acuerdo que traslada cuotas al final y baja la antigüedad de la mora: son dos cosas distintas con el mismo nombre. En el detalle aparece como una sublínea en la celda de la base, solo en las operaciones que lo tienen.</p>

                    <h6 class="det-ayuda-sub">Rango (A a F)</h6>
                    <p>La antigüedad de la mora de la operación, en días, clasificada en seis tramos. Determina el porcentaje de deterioro contable:</p>
                </div>
                <div class="det-panel">
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Rango</th><th class="num">Días de mora</th><th class="num">% contable</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><span class="det-badge det-rango-a">A</span></td><td class="num">0 a 30</td><td class="num">0 %</td></tr>
                                <tr><td><span class="det-badge det-rango-b">B</span></td><td class="num">31 a 90</td><td class="num">8 %</td></tr>
                                <tr><td><span class="det-badge det-rango-c">C</span></td><td class="num">91 a 180</td><td class="num">23 %</td></tr>
                                <tr><td><span class="det-badge det-rango-d">D</span></td><td class="num">181 a 360</td><td class="num">53 %</td></tr>
                                <tr><td><span class="det-badge det-rango-e">E</span></td><td class="num">361 a 720</td><td class="num">78 %</td></tr>
                                <tr><td><span class="det-badge det-rango-f">F</span></td><td class="num">721 en adelante</td><td class="num">100 %</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="det-subtitulo mt-3 mb-0">Los días se cuentan con año comercial de 360 días (<code>DAYS360</code>), igual que en el libro. Una operación al día no tiene rango: aparece como <span class="det-badge det-rango-corriente">Corriente</span></p>
                </div>
                <div class="det-ayuda-texto">
                    <h6 class="det-ayuda-sub">Congelado</h6>
                    <p>Cuando una operación entra en suspensión de intereses, el interés deja de crecer y se congela en el valor que tenía a la fecha del evento. Ese interés congelado entra a la base solo cuando la operación <strong>no tiene saldo atribuido en SIESA</strong>: la base es entonces el capital vencido de factoring más el interés congelado y la prórroga. Si la operación sí tiene saldo atribuido en SIESA, la base es el capital vencido más el interés vencido de SIESA, más la prórroga vencida. El detalle está en <a href="#ayuda-4-6">Intereses suspendidos</a>. <strong>No se reversa contra el ingreso ni sale del balance.</strong> El capital sigue deteriorándose normalmente.</p>

                    <h6 class="det-ayuda-sub">Tope fiscal</h6>
                    <p>La deducción fiscal del año es el 33 % de la base de las operaciones con más de 360 días de mora (rangos E y F), y en las suspendidas de su base congelada. Nunca puede superar lo que falta para llegar al valor nominal de la obligación en SIESA. Es la pieza más delicada del cálculo y está en <a href="#ayuda-4-4">Contable contra fiscal</a>.</p>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-3">
                <h5 class="det-ayuda-tit"><span class="num">Sección 3</span>El recorrido del dato, en cinco pasos</h5>
                <p class="det-ayuda-lede">Entender este recorrido hace que todas las pantallas se lean solas.</p>
                <div class="det-panel">
                    <ul class="det-pasos">
                        <li>
                            <span class="det-ayuda-paso">1</span>
                            <span><strong>Se extrae la cartera.</strong> El módulo consulta el sistema de factoring a la fecha de corte y guarda el resultado <strong>tal cual lo recibió</strong>, sin filtrar nada. Esa copia literal es la prueba de qué entregó el origen ese día, y se puede demostrar años después. En paralelo consulta SIESA y guarda su snapshot de saldos.</span>
                        </li>
                        <li>
                            <span class="det-ayuda-paso">2</span>
                            <span><strong>Se clasifica cuota por cuota.</strong> A cada cuota se le calculan sus días de mora y se parte su saldo entre corriente y vencido.</span>
                        </li>
                        <li>
                            <span class="det-ayuda-paso">3</span>
                            <span><strong>Se consolida por operación.</strong> Se suman las cuotas de cada operación, se determina su rango por la antigüedad de la mora, y se calcula la base, a la que se suma el interés de prórroga vencido que reporta SIESA. Aquí se excluyen las cuotas que el origen entregó repetidas: siguen en el detalle, pero no se cuentan dos veces.</span>
                        </li>
                        <li>
                            <span class="det-ayuda-paso">4</span>
                            <span><strong>Se calcula el deterioro.</strong> Contable (base × % del rango) y fiscal (33 % anual con tope). Antes de eso se aplican las suspensiones vigentes, que congelan el interés.</span>
                        </li>
                        <li>
                            <span class="det-ayuda-paso">5</span>
                            <span><strong>Se cuadra y se cierra.</strong> Se corren los controles de cuadre. Si alguno falla, o quedan partidas de conciliación sin explicar o bajas sin clasificar, el corte no cierra, salvo cierre expreso con salvedad, que queda registrado como tal para siempre.</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-4">
                <h5 class="det-ayuda-tit"><span class="num">Sección 4</span>Las pantallas, una por una</h5>
                <div class="det-anclas mb-3">
                    <a href="#ayuda-4-0">4.0 Cómo se entra</a>
                    <a href="#ayuda-4-1">4.1 Cortes</a>
                    <a href="#ayuda-4-2">4.2 Resumen</a>
                    <a href="#ayuda-4-3">4.3 Detalle</a>
                    <a href="#ayuda-4-4">4.4 Contable contra fiscal</a>
                    <a href="#ayuda-4-5">4.5 Evolución</a>
                    <a href="#ayuda-4-6">4.6 Intereses suspendidos</a>
                    <a href="#ayuda-4-7">4.7 Conciliación</a>
                    <a href="#ayuda-4-8">4.8 Controles y cierre</a>
                    <a href="#ayuda-4-9">4.9 Exportar</a>
                </div>

                <div class="det-panel" id="ayuda-4-0">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Cómo se entra y quién puede hacer qué</h6>
                            <p class="det-subtitulo">¿Por dónde se llega a cada pantalla y qué permiso exige cada acción?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.0</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <h6 class="det-ayuda-sub">Cómo se llega a cada pantalla</h6>
                        <p>El menú lateral <strong>Deterioro Cartera</strong> tiene una sola entrada: <strong>Cortes</strong>. Las demás pantallas se abren desde un corte y conservan el corte elegido.</p>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Pantalla</th><th>Cómo se llega</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto">Cortes</td><td class="det-texto">Menú lateral, entrada <strong>Cortes</strong></td></tr>
                                <tr><td class="det-texto">Resumen, Detalle y Controles y cierre</td><td class="det-texto">Íconos de la fila del corte, una vez calculado. También desde el encabezado de las otras pantallas del corte</td></tr>
                                <tr><td class="det-texto">Contable contra fiscal, Evolución, Intereses suspendidos y Conciliación</td><td class="det-texto">Botones del encabezado de las pantallas del corte</td></tr>
                                <tr><td class="det-texto">Guía de uso</td><td class="det-texto">Botón <strong>Guía de uso</strong> (ícono <i class="fas fa-question"></i>) de cualquier pantalla del módulo. Se regresa con <strong>Volver a {pantalla}</strong></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <p>Las migas de cada pantalla muestran «Cortes › Resumen {fecha} › {pantalla}». Si se entra a una pantalla del corte sin corte en la dirección, la pantalla redirige a Cortes.</p>
                        <h6 class="det-ayuda-sub">Permisos</h6>
                        <p>Cada acción tiene su propio permiso. Sin él, el botón se oculta o, en algunos casos, se sigue viendo y el sistema rechaza la acción al ejecutarla.</p>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Permiso</th><th>Qué habilita</th><th>Sin el permiso</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto"><strong>Consultar deterioro</strong></td><td class="det-texto">Ver las cifras de las pantallas del corte y abrir esta guía</td><td class="det-texto">El sistema rechaza la consulta</td></tr>
                                <tr><td class="det-texto"><strong>Calcular deterioro</strong></td><td class="det-texto"><strong>Nuevo corte</strong>, <strong>Calcular</strong> y <strong>Eliminar</strong></td><td class="det-texto">Se ve, pero el sistema rechaza la acción</td></tr>
                                <tr><td class="det-texto"><strong>Suspender intereses</strong></td><td class="det-texto"><strong>Marcar operación</strong>, <strong>Marcar</strong> y <strong>Levantar</strong></td><td class="det-texto">Se ve, pero el sistema rechaza la acción</td></tr>
                                <tr><td class="det-texto"><strong>Conciliar con SIESA</strong></td><td class="det-texto"><strong>Explicar</strong> y <strong>Ver / editar</strong> las partidas</td><td class="det-texto">Se oculta</td></tr>
                                <tr><td class="det-texto"><strong>Clasificar bajas</strong></td><td class="det-texto"><strong>Clasificar</strong> y <strong>Ver / editar</strong> las bajas de C-2</td><td class="det-texto">Se oculta</td></tr>
                                <tr><td class="det-texto"><strong>Cerrar corte</strong></td><td class="det-texto"><strong>Cerrar corte</strong></td><td class="det-texto">Se oculta</td></tr>
                                <tr><td class="det-texto"><strong>Forzar cierre con salvedad</strong></td><td class="det-texto"><strong>Forzar el cierre con salvedad</strong>. Exige además el permiso de cerrar</td><td class="det-texto">Se oculta</td></tr>
                                <tr><td class="det-texto"><strong>Reabrir corte</strong></td><td class="det-texto"><strong>Reabrir corte</strong></td><td class="det-texto">Se oculta</td></tr>
                                <tr><td class="det-texto"><strong>Consultar bitácora</strong></td><td class="det-texto">El panel «Bitácora del corte»</td><td class="det-texto">Se oculta</td></tr>
                                <tr><td class="det-texto"><strong>Exportar</strong></td><td class="det-texto">El menú <strong>Exportar</strong></td><td class="det-texto">Se oculta</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-aviso info mt-3 mb-0">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div><strong>Roles por defecto:</strong> Administrador, Gerente y Contador tienen todos los permisos, salvo <strong>Forzar cierre con salvedad</strong> y <strong>Reabrir corte</strong>, que solo tienen Administrador y Gerente. Ocultar una entrada del menú no quita el acceso por dirección: cada pantalla exige su propio permiso.</div>
                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-1">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Cortes</h6>
                            <p class="det-subtitulo">¿Qué cortes hay, en qué estado está cada uno y cuáles tienen cuadres en rojo?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.1</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p>Es la única entrada del menú. Lista los cortes registrados con su estado, el tamaño de la cartera, el deterioro calculado y el semáforo de cuadres. Arriba de la tabla, un aviso dice qué período tiene cargado el origen: «El origen tiene cargado el periodo AAAA-MM con N cuotas, y el comparativo el AAAA-MM».</p>
                        <h6 class="det-ayuda-sub">Crear un corte</h6>
                        <p>El botón <strong>Nuevo corte</strong> pide la fecha de corte y, de forma opcional, la fecha de comparación. Hay <strong>un solo corte por fecha</strong>. El corte solo se crea y se calcula si el período cargado en el origen de factoring coincide con la fecha de corte; si no, la pantalla lo avisa.</p>
                        <ul>
                            <li><strong>Calcular ahora / Después</strong> &mdash; al crear el corte, la pantalla ofrece calcularlo de inmediato.</li>
                            <li><strong>Ver resumen</strong> &mdash; al terminar el cálculo, lleva al Resumen del corte.</li>
                            <li><strong>Corte anterior</strong> &mdash; se fija al crear: es el corte más reciente con fecha menor. Contra él se miden el gasto del período y las bajas.</li>
                        </ul>
                        <h6 class="det-ayuda-sub">Qué muestra cada fila</h6>
                        <ul>
                            <li><strong>Columnas</strong> &mdash; Corte, Estado, Cuotas, Operaciones, Capital, Deterioro contable, Cuadres, Ejecutado y Duración.</li>
                            <li><strong>Cuadres</strong> &mdash; «N de M con diferencia» o «N en cero», más los que están en n/a.</li>
                            <li><strong>Acciones</strong> &mdash; <strong>Calcular</strong> y <strong>Eliminar</strong> mientras el corte no esté cerrado; <strong>Resumen</strong>, <strong>Detalle</strong> y <strong>Controles y cierre</strong> una vez calculado.</li>
                            <li><strong>Distintivo</strong> &mdash; un corte cerrado con salvedad lleva <span class="det-badge det-salvedad">Con salvedades</span>.</li>
                        </ul>
                        <p>Después de calcular aparece el panel «Última corrida», con los pasos del cálculo, sus filas y tiempos, y el resultado de cada control de cuadre.</p>
                    </div>
                    <div class="det-aviso info">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div>La columna <strong>Capital</strong> de esta pantalla suma todas las cuotas que entregó el origen, <strong>incluidas las repetidas</strong>. La tarjeta Capital del Resumen las excluye, por eso las dos cifras pueden no coincidir.</div>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <h6 class="det-ayuda-sub">Los tres estados de un corte</h6>
                        <ul>
                            <li><span class="det-badge det-estado-abierto">ABIERTO</span> &mdash; creado, todavía sin calcular; se puede calcular o eliminar.</li>
                            <li><span class="det-badge det-estado-calculado">CALCULADO</span> &mdash; el motor corrió. Las cifras están, los cuadres están medidos, y todo se puede volver a correr si cambia un parámetro o se corrige una marca.</li>
                            <li><span class="det-badge det-estado-cerrado">CERRADO</span> &mdash; la foto queda inmutable. No se recalcula. Reabrir es una acción con permiso propio y queda en bitácora.</li>
                        </ul>
                        <p><strong>Qué mirar aquí:</strong> el semáforo de cuadres antes de entrar a cualquier otra pantalla. Un corte con cuadres en rojo tiene cifras que todavía se van a mover.</p>
                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-2">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Resumen del corte</h6>
                            <p class="det-subtitulo">¿Cuánta cartera hay, cómo se reparte por producto y rango, y cuánto deterioro produce?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.2</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p>Es la réplica del bloque principal del libro. Arriba, seis tarjetas; debajo, el gráfico «Vencido por rango de mora» y la tabla «Producto por rango».</p>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Tarjeta</th><th>Qué es</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto">Operaciones</td><td class="det-texto">Cuántas operaciones tiene el corte</td></tr>
                                <tr><td class="det-texto">Cuotas</td><td class="det-texto">Filas que entregó el origen. Puede incluir repetidas del sistema de factoring</td></tr>
                                <tr><td class="det-texto"><strong>Capital</strong></td><td class="det-texto">Capital <strong>corriente + vencido</strong>: toda la cartera</td></tr>
                                <tr><td class="det-texto"><strong>Interés</strong></td><td class="det-texto">Interés <strong>corriente + vencido</strong>: todo el interés</td></tr>
                                <tr><td class="det-texto"><strong>Base de deterioro</strong></td><td class="det-texto"><strong>Solo</strong> capital vencido + interés vencido + interés de prórroga. Debajo indica «incluye X de interés de prórroga»</td></tr>
                                <tr><td class="det-texto">Deterioro contable</td><td class="det-texto">El resultado: base × % del rango, operación por operación</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="det-aviso info mt-3">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div><strong>«Si sumo Capital más Interés no me da la Base.»</strong> Correcto, y así tiene que ser: las tres tarjetas no están en la misma base. Capital e Interés son la cartera total; la Base es solo la porción <strong>vencida</strong> de esos dos.</div>
                    </div>

                    <div class="det-panel">
                        <div class="det-panel-cab">
                            <div>
                                <h6>Por qué Capital + Interés no da la Base</h6>
                                <p class="det-subtitulo">Cifras del corte de julio de 2026. Las tres barras están dibujadas contra la misma escala: el capital total.</p>
                            </div>
                        </div>
                        <div class="det-leyenda">
                            <span><i class="gv-corriente"></i> Corriente</span>
                            <span><i class="gv-vencido"></i> Vencido, lo único que entra a la base</span>
                        </div>
                        <div class="det-ayuda-prop">
                            <div role="img" aria-label="Capital: corriente 13.934.770.801, vencido 1.484.366.526, total 15.419.137.327">
                                <span class="eti">Capital</span>
                                <span class="cifra">15.419.137.327</span>
                                <span class="pista">
                                    <span class="tramo corriente" style="width:90.37%"></span>
                                    <span class="tramo vencido" style="width:9.63%"></span>
                                </span>
                            </div>
                            <div role="img" aria-label="Interés: corriente 13.753.811.952, vencido 692.519.031, total 14.446.330.983">
                                <span class="eti">Interés</span>
                                <span class="cifra">14.446.330.983</span>
                                <span class="pista">
                                    <span class="tramo corriente" style="width:89.20%"></span>
                                    <span class="tramo vencido" style="width:4.49%"></span>
                                </span>
                            </div>
                            <div class="base" role="img" aria-label="Base de deterioro: 2.176.885.557, la suma del capital vencido y el interés vencido">
                                <span class="det-corte-bloque">Solo lo vencido de los dos</span>
                                <span class="eti">Base de deterioro</span>
                                <span class="cifra">2.176.885.557</span>
                                <span class="pista">
                                    <span class="tramo vencido" style="width:14.12%"></span>
                                </span>
                            </div>
                        </div>
                        <div class="det-scroll mt-3">
                            <table class="det-tabla">
                                <thead>
                                    <tr><th>Concepto</th><th class="num">Corriente</th><th class="num">Vencido</th><th class="num">Total (tarjeta)</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>Capital</td><td class="num">13.934.770.801</td><td class="num">1.484.366.526</td><td class="num">15.419.137.327</td></tr>
                                    <tr><td>Interés</td><td class="num">13.753.811.952</td><td class="num">692.519.031</td><td class="num">14.446.330.983</td></tr>
                                    <tr class="det-total"><td>Base de deterioro</td><td class="num det-cero">&mdash;</td><td class="num">2.176.885.557</td><td class="num det-cero">&mdash;</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="det-subtitulo mt-3 mb-0">La tabla «Producto por rango» ya trae las cuatro columnas separadas &mdash;capital corriente, capital vencido, interés corriente, interés vencido&mdash; justamente para que esta suma sea visible fila por fila. La columna «Interés de prórroga», al lado del interés vencido, completa el tercer término de la base.</p>
                    </div>

                    <div class="det-ayuda-texto">
                        <p><strong>Un matiz sobre el Deterioro contable:</strong> tampoco es <code>base × un porcentaje</code>. Se calcula operación por operación con el porcentaje de <em>su</em> rango, y en las operaciones suspendidas sobre la base congelada. Por eso una regla de tres contra la base total no da.</p>
                        <h6 class="det-ayuda-sub">Qué muestra la pantalla, de arriba abajo</h6>
                        <ol>
                            <li><strong>Tarjetas</strong> &mdash; las seis de la tabla anterior. Si el corte se calculó antes de medir el interés de prórroga, debajo aparece un aviso y esa cifra no se muestra.</li>
                            <li><strong>Vencido por rango de mora</strong> &mdash; gráfico con el capital vencido y el interés vencido de cada rango.</li>
                            <li><strong>Producto por rango</strong> &mdash; la matriz, con subtotal «Total {producto}» y «TOTAL GENERAL».</li>
                            <li><strong>Deducción fiscal del año gravable</strong> &mdash; con el distintivo <span class="det-badge det-estado-abierto">Estimado</span> o <span class="det-badge det-estado-cerrado">Definitivo</span>.
                                <ul>
                                    <li><strong>Tarjetas</strong> &mdash; Fiscal individual (33 %), Acumulado años anteriores, Recorte por tope y Deducción del año.</li>
                                    <li><strong>Columnas</strong> &mdash; Producto, Rango, %, Operaciones, Base, Individual 33 %, Acum. anterior, Saldo topado y Deducción del año, agrupadas bajo «Cálculo D-04» y «Tope RN-09».</li>
                                    <li><strong>Filas</strong> &mdash; cada producto cierra con «Rangos sin deducción fiscal», la base que no deduce, y su «Total {producto}»; al final va el «TOTAL GENERAL».</li>
                                    <li><strong>Recorte</strong> &mdash; cuando el tope recorta la deducción, la celda lo marca como «−X».</li>
                                </ul>
                            </li>
                            <li><strong>Método general · Desactivado</strong> &mdash; solo de análisis: no entra en ninguna cifra del corte.</li>
                            <li><strong>Controles de cuadre</strong> &mdash; la lista de controles del corte con su estado, para ver de un vistazo si algo no cuadra.</li>
                        </ol>
                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-3">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Detalle por operación</h6>
                            <p class="det-subtitulo">¿De dónde sale exactamente esta cifra, operación por operación y cuota por cuota?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.3</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p>La misma información del resumen, pero abierta operación por operación, filtrable y exportable. Operación, Cliente, Producto, Rango, Días mora y Base están siempre; el resto de columnas cambia con las <strong>tres vistas</strong>:</p>
                        <ul>
                            <li><strong>Contable</strong> &mdash; Cuotas, Capital vencido, Interés vencido, % y Deterioro.</li>
                            <li><strong>Fiscal</strong> &mdash; Individual 33 %, Acum. anterior, Saldo topado y Deducción del año.</li>
                            <li><strong>Diferido</strong> &mdash; Fiscal acumulado, Diferencia temporaria, Impuesto diferido y Año de reversión.</li>
                        </ul>
                        <p>Se filtra por <strong>Producto</strong>, <strong>Rango</strong> y <strong>Cliente u operación</strong>, además de las casillas de revisión.</p>
                        <h6 class="det-ayuda-sub">Rótulos dentro de las celdas</h6>
                        <ul>
                            <li><strong>Vencido SIESA</strong> &mdash; si la operación tiene saldo atribuido en SIESA, Capital vencido e Interés vencido muestran el valor de SIESA con el rótulo «SIESA», o «SIESA · fact. X» cuando difiere de factoring.
                                <ul>
                                    <li><strong>Qué es vencido</strong> &mdash; las facturas de SIESA con vencimiento hasta la fecha de corte.</li>
                                    <li><strong>Origen</strong> &mdash; el tooltip dice de dónde sale: saldo del tercero, operación SIESA o nota contable.</li>
                                    <li><strong>Base</strong> &mdash; es informativo, salvo en las suspendidas con origen SIESA, donde sí forma la base.</li>
                                </ul>
                            </li>
                            <li><strong>Base de suspendidas</strong> &mdash; «SIESA · suspendida» o «suspendida · congelada», según de dónde sale la base congelada. El tooltip trae la base sin suspender.</li>
                            <li><strong>Cuotas repetidas</strong> &mdash; «N cuotas repetidas» bajo el número de la operación.</li>
                        </ul>
                        <h6 class="det-ayuda-sub">Ver cuotas</h6>
                        <p>El botón <strong>Ver cuotas</strong> (lupa) de cada fila abre «Cuotas de la operación N · M registros, K en los totales», con el estado de cada cuota: <span class="det-badge det-rango-f">Vencida</span> o <span class="det-badge det-rango-corriente">Corriente</span>. Las cuotas repetidas llevan además el badge <span class="det-badge det-ambar">Repite la N</span>, que indica qué cuota repiten. Si la operación tiene interés de prórroga, un aviso recuerda que ese valor no está en las cuotas.</p>
                        <p>En los cortes calculados antes de medir las cuotas repetidas o el interés de prórroga, la pantalla lo avisa y no muestra esas cifras: hay que volver a calcular el corte.</p>
                        <h6 class="det-ayuda-sub">Los filtros que más se usan en una revisión</h6>
                        <ul>
                            <li><strong>Solo con deterioro</strong> &mdash; deja las operaciones que efectivamente provisionan. En la vista Fiscal pasa a ser <strong>Solo con deducción fiscal</strong>.</li>
                            <li><strong>Solo topadas</strong> &mdash; las operaciones a las que el tope fiscal les recortó la deducción. Es el filtro para revisar el artículo 145 caso por caso. Solo aparece en la vista Fiscal.</li>
                            <li><strong>Solo diferido pasivo</strong> &mdash; operaciones donde el fiscal acumulado ya superó al contable. Solo aparece en la vista Diferido.</li>
                            <li><strong>Solo con cuotas repetidas</strong> &mdash; las operaciones afectadas por el defecto del origen. Solo aparece si el corte las tiene.</li>
                            <li><strong>Solo con interés de prórroga</strong> &mdash; las operaciones cuya base incluye saldo de prórroga vencido de SIESA. Solo aparece si el corte tiene alguna.</li>
                        </ul>
                        <p>En la vista Diferido, la columna «Año de reversión» muestra un guion en las operaciones que ya completaron la deducción o que no se pueden proyectar; el Excel las distingue como «Deducción al 100 %» o «Sin proyección». El Excel que se exporta desde aquí <strong>respeta los filtros aplicados</strong>.</p>
                        <p><strong>Para qué sirve en la práctica:</strong> cuando una cifra del resumen no convence, esta pantalla la descompone hasta la cuota. Es el sustituto del filtrado manual sobre las 115.000 filas de la hoja <code>BD</code>.</p>
                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-4">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Contable contra fiscal</h6>
                            <p class="det-subtitulo">¿Cuánto de este deterioro es deducible este año y qué impuesto diferido deja la brecha?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.4</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p>Esta es la pantalla que el libro no tenía, y probablemente la de mayor valor.</p>
                        <p><strong>El problema que resuelve.</strong> La política contable llega al 100 % de deterioro a partir de los 721 días (rango F). La norma fiscal (art. 145 ET) acumula 33 % por año y necesita tres años. Esa brecha es una <strong>diferencia temporaria</strong>, y de ella sale un impuesto diferido que hasta hoy no se medía operación por operación.</p>
                        <h6 class="det-ayuda-sub">El puente, de izquierda a derecha</h6>
                    </div>
                    <div class="det-ayuda-codigo">Base  &rarr;  Deterioro contable  │  Acum. anterior + Deducción del año = Fiscal acumulado
                             │
                             └&rarr;  Diferencia temporaria  &rarr;  Impuesto diferido</div>
                    <div class="det-ayuda-texto">
                        <ul>
                            <li><strong>Deducible</strong> &mdash; el contable va por delante del fiscal. Genera diferido <strong>activo</strong>.</li>
                            <li><strong>Imponible</strong> &mdash; el fiscal acumulado superó al contable. Genera diferido <strong>pasivo</strong>.</li>
                        </ul>
                        <p>Los dos se muestran <strong>separados y sin compensar entre sí</strong>, porque compensarlos falsearía la revelación.</p>
                        <h6 class="det-ayuda-sub">El tope del artículo 145, en palabras</h6>
                        <p>La deducción fiscal del año es el 33 % de la base de las operaciones con más de 360 días de mora (rangos E y F), y en las suspendidas de su base congelada. Nunca puede superar lo que falta para llegar al valor nominal de la obligación en SIESA. Formalmente:</p>
                    </div>
                    <div class="det-ayuda-codigo">Deducción del año = MAX(0 ; SI(acumulado_anterior + 33% &gt; saldo_tope
                              ; saldo_tope &minus; acumulado_anterior
                              ; 33%))</div>
                    <div class="det-ayuda-texto">
                        <p>donde <code>saldo_tope</code> es el menor entre el <strong>valor nominal</strong> de la obligación en SIESA (capital más interés facturado y prórroga de los documentos <code>OPE</code>, <code>CC</code>, <code>FAT</code> y <code>FEX</code>) y la base de la operación, que en las suspendidas es la base congelada. Si la operación no está en SIESA, el tope es la base. Es la regla que el módulo tenía que reproducir con exactitud, y la que el filtro <strong>Solo topadas</strong> del detalle permite auditar.</p>
                    </div>
                    <div class="det-aviso">
                        <i class="fas fa-triangle-exclamation mt-1"></i>
                        <div><strong>Advertencia que la pantalla muestra y conviene repetir en voz alta:</strong> la deducción se determina sobre obligaciones que <strong>subsistan al 31 de diciembre</strong>. Los cortes mensuales son estimaciones. <strong>Solo el corte de diciembre produce la cifra definitiva del año gravable.</strong></div>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <h6 class="det-ayuda-sub">Qué muestra la pantalla</h6>
                        <ul>
                            <li><strong>Cuatro tarjetas</strong> &mdash; Deterioro contable, Fiscal acumulado, Diferencia temporaria e Impuesto diferido.</li>
                            <li><strong>Puente contable&ndash;fiscal</strong> &mdash; con vista <strong>Por rango</strong> o <strong>Por producto</strong>. Columnas: Operaciones, Base, %, Deterioro contable, Acum. anterior, Deducción del año, Fiscal acumulado, Deducible, Imponible, Activo y Pasivo. Incluye la fila «Rangos sin deducción fiscal».</li>
                            <li><strong>Ver las operaciones con diferencia temporaria imponible</strong> &mdash; enlace bajo el puente, solo si el corte las tiene. Lleva al Detalle en la vista Diferido con ese filtro. El botón <strong>Detalle</strong> del encabezado también abre la vista Diferido.</li>
                            <li><strong>Movimiento del período</strong> &mdash; el corte anterior contra este.</li>
                            <li><strong>Reversión proyectada</strong> &mdash; el año gravable en que cada operación completa el 100 % deducible, con la leyenda «Año corriente», «Año siguiente», «Años posteriores» y «Sin proyección». El Excel del detalle separa las que ya completaron la deducción («Deducción al 100 %»).</li>
                            <li><strong>Evolución de la diferencia temporaria</strong> &mdash; la serie de los cortes anteriores. El gráfico aparece desde el tercer corte con comparativo; los cortes calculados antes de esta medición llevan <span class="det-badge det-inactivo">Sin fase 3</span> y no entran en el gráfico.</li>
                            <li><strong>Controles de cuadre</strong> &mdash; los controles del corte con su estado.</li>
                        </ul>
                        <p>La tarifa de renta es paramétrica: hoy <strong>35 %</strong>. <strong>Fuera de diciembre el impuesto diferido es preliminar y no debe registrarse en libros.</strong></p>
                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-5">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Evolución</h6>
                            <p class="det-subtitulo">¿Cuánto gasto por deterioro reconozco este mes, y por qué subió o bajó?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.5</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p><strong>Descomposición del movimiento del mes.</strong> Del deterioro del corte anterior al de este corte, partido en tres conceptos:</p>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Concepto</th><th>Qué explica</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto"><strong>Altas</strong></td><td class="det-texto">Operaciones que no estaban en el corte anterior</td></tr>
                                <tr><td class="det-texto"><strong>Variación de las que continúan</strong></td><td class="det-texto">Las mismas operaciones, con más o menos deterioro</td></tr>
                                <tr><td class="det-texto"><strong>Bajas</strong></td><td class="det-texto">Operaciones que estaban y ya no están</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <p>Los tres tienen que sumar exactamente la variación del deterioro contable. Lo verifican dos controles de cuadre: <code>C-MOVIMIENTO</code>, contra el deterioro del corte, y <code>C-VARIACION</code>, que vuelve a restar operación por operación contra el corte anterior. Bajo la cascada, la pantalla dice «La descomposición cierra» o «La descomposición no cierra».</p>
                        <h6 class="det-ayuda-sub">Qué muestra la pantalla</h6>
                        <ul>
                            <li><strong>Tres tarjetas</strong> &mdash; Deterioro al (fecha anterior), Gasto del período y Deterioro al (fecha del corte).</li>
                            <li><strong>Conciliación con el libro</strong> &mdash; tres filas contra el libro: Deterioro del mes anterior, Deterioro de este corte y Gasto del período. Las cifras que el libro teclea a mano llevan la marca «Digitada en el libro», y debajo va el «Desglose del mes anterior por rango», de B a F. Requiere el cargue de las cifras del libro, que ejecuta Sistemas (<a href="#ayuda-anexo-cargues">ver cargues</a>).</li>
                            <li><strong>Gasto del mes</strong> &mdash; abierto por rango o por producto. Las filas que bajan el deterioro llevan <span class="det-chip libera"><i class="fas fa-arrow-down"></i>Liberación</span>.</li>
                            <li><strong>Serie histórica</strong> &mdash; columnas Corte, Operaciones, Base, Deterioro contable, Gasto del período, Fiscal acumulado, Diferencia temporaria e Impuesto diferido. Hasta 24 cortes, un renglón por corte; es lo que en el libro estaba disperso en hojas ocultas.</li>
                            <li><strong>Bajas del período</strong> &mdash; las operaciones que salieron. Aquí la columna Motivo siempre dice <span class="det-badge det-inactivo">Sin clasificar</span>: la clasificación se hace y se consulta en <a href="#ayuda-4-8">Controles y cierre</a>, control C-2.</li>
                        </ul>
                        <p><strong>Por qué importa la descomposición.</strong> En el libro, el gasto del mes se obtenía restando contra una cifra del mes anterior <strong>digitada a mano</strong>. Aquí el gasto sale de la comparación operación por operación, y además queda explicado: no es lo mismo que el deterioro suba porque entró cartera nueva a que suba porque la cartera existente envejeció.</p>                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-6">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Intereses suspendidos</h6>
                            <p class="det-subtitulo">¿Qué operaciones dejaron de causar interés, desde cuándo y con qué efecto sobre la base?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.6</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p>Gestiona la suspensión de causación de intereses. <strong>No hay umbral automático por días de mora</strong>: la marca es manual, operación por operación, por usuario autorizado, con causal, fecha del evento, observación y, de forma opcional, soporte.</p>
                        <h6 class="det-ayuda-sub">Las cuatro causales que se pueden marcar</h6>
                        <ol>
                            <li>Fallecimiento del deudor sin que la aseguradora pague.</li>
                            <li>Admisión del deudor a un proceso de insolvencia.</li>
                            <li>Paso de la operación a cobro jurídico.</li>
                            <li>Finalización de las cuotas disponibles: deja de generar facturación automática.</li>
                        </ol>
                        <p>Las tres primeras son las causales de la norma (<code>D-05</code>). La cuarta no es un evento del deudor sino de la operación: al agotarse las cuotas disponibles, factoring deja de generar la facturación automática del interés.</p>
                        <p>Las marcas del cargue inicial sin causal muestran en la columna Causal «Proviene del cargue inicial (D-14) y está pendiente de clasificar». Esa causal no se puede elegir, y una marca vigente no se edita: solo admite <strong>Levantar</strong>. Para reclasificarla hay dos caminos:</p>
                        <ol>
                            <li><strong>Levantar</strong> la marca y luego <strong>Marcar</strong> de nuevo la operación con la causal correcta. El interés congelado se vuelve a calcular con la nueva fecha del evento.</li>
                            <li>Pedir a Sistemas que repita el cargue de suspensiones con el archivo corregido (<a href="#ayuda-anexo-cargues">ver cargues</a>).</li>
                        </ol>
                        <h6 class="det-ayuda-sub">Qué pasa cuando se marca una operación</h6>
                        <ul>
                            <li>Para el deterioro, el interés <strong>deja de crecer</strong> desde la fecha del evento. Que factoring deje de facturarlo depende del punto B de las <a href="#ayuda-7">decisiones pendientes</a>.</li>
                            <li>El interés reconocido hasta esa fecha <strong>se congela</strong>: permanece en el activo por el valor que tenía. No se reversa contra el ingreso. Entra a la base solo cuando la operación no tiene saldo atribuido en SIESA.</li>
                            <li>La <strong>base congelada</strong> depende de si la operación tiene saldo atribuido en SIESA (si el tercero tiene una sola operación en el corte, todo su saldo; si tiene varias, los <code>OPE</code> por consecutivo y los <code>CC</code>/<code>FAT</code>/<code>FEX</code> por nota):
                                <ul>
                                    <li><strong>Con saldo en SIESA</strong> (origen <code>SIESA</code>): <code>capital vencido SIESA (13050501) + interés vencido SIESA (13451001) + prórroga vencida SIESA</code>.</li>
                                    <li><strong>Sin saldo en SIESA</strong> (origen <code>FACTORING</code>): <code>capital vencido de factoring + interés congelado + prórroga vencida</code>.</li>
                                </ul>
                            </li>
                            <li>La <strong>prórroga</strong> se identifica por el texto «PRORROGA» en la nota del documento de SIESA. La no vencida, o sin fecha de vencimiento, queda fuera de la base pero cuenta en el valor nominal que topa la deducción fiscal.</li>
                            <li>La <strong>reducción de la base</strong> es <code>base sin suspender &minus; base congelada</code> y puede ser negativa cuando el saldo de SIESA supera lo que calcula factoring.</li>
                            <li>El <strong>capital sigue deteriorándose</strong> normalmente hasta llegar al 100 % a partir de los 721 días (rango F).</li>
                            <li>En el producto FACTORING el módulo calcula aparte el <strong>interés no facturado</strong> (interés vencido menos congelado) y lo muestra en la pantalla. El anexo para revelaciones está pendiente.</li>
                        </ul>
                        <p><strong>De dónde sale el valor congelado.</strong> Son las facturas automáticas de interés de esa operación en SIESA &mdash;<code>FAT</code>, y <code>CC</code> de la cuenta 13451001 para las anteriores a 2022&mdash; hasta el mes del evento, por su <strong>saldo pendiente</strong> a esa fecha. Se toma el pendiente y no el total facturado porque lo que el cliente ya pagó no está en el activo y no puede deteriorarse.</p>
                        <p><strong>El efecto cruzado que hay que vigilar.</strong> Al suspender la operación, su base deja de seguir el interés que factoring sigue calculando y con ello cambia el gasto por deterioro del período. Ninguna pantalla separa hoy ese efecto dentro del gasto: al leer una caída del gasto, conviene revisar si coincide con marcas nuevas antes de tomarla como una mejora de la cartera.</p>
                        <p><strong>Marcas sin congelar.</strong> Si la operación no tiene ninguna factura de interés hasta el mes del evento, la marca queda <strong>sin congelar</strong>: la operación se deteriora con su base normal y el control <code>C-MARCAS</code> la reporta en falla, lo que bloquea el cierre hasta que se resuelva. En la tabla se ven con «Sin congelar — no se pudo determinar el interés a la fecha del evento», y el título de las marcas vigentes lleva el conteo <span class="det-badge det-ambar">N sin congelar</span>.</p>
                    </div>
                    <div class="det-aviso info">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div><strong>Caso de agosto de 2026.</strong> 21 operaciones sin facturación de interés en SIESA, marcadas con evento del 31/08/2026 y causal sin determinar, suman <strong>420.776.769</strong> de base en <code>C-MARCAS</code>. Es una falla prevista y aceptada.</div>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <h6 class="det-ayuda-sub">Qué muestra la pantalla</h6>
                        <ul>
                            <li><strong>Efecto sobre este corte</strong> &mdash; Operaciones suspendidas, Interés congelado, Reducción de la base de deterioro e Interés no facturado en FACTORING.</li>
                            <li><strong>Marcas vigentes</strong> &mdash; todas las marcas activas del sistema, no solo las del corte. Las que no afectan la fecha de este corte llevan <span class="det-badge det-inactivo">No aplica a este corte</span>. Tiene el botón <strong>Marcar operación</strong>, la acción <strong>Levantar</strong> en cada fila y el clip <i class="fas fa-paperclip"></i> «Ver el soporte» cuando la marca tiene soporte.</li>
                            <li><strong>Candidatas sugeridas</strong> &mdash; las operaciones de rangos E y F sin marca activa, con el botón <strong>Marcar</strong>, que abre el formulario con la operación ya cargada. El módulo sugiere; <strong>no marca por su cuenta</strong>.</li>
                            <li><strong>Histórico de marcas levantadas</strong> &mdash; también de todo el sistema.</li>
                        </ul>
                        <h6 class="det-ayuda-sub">Reglas de las marcas</h6>
                        <ul>
                            <li>Una sola marca activa por operación.</li>
                            <li>La fecha del evento no puede ser futura y la observación es obligatoria.</li>
                            <li>El soporte es <strong>opcional</strong>: PDF, JPG o PNG de hasta 20 MB. Lo abre quien tenga permiso de consulta.</li>
                            <li>Levantar una marca exige observación y conserva el histórico.</li>
                            <li>Una marca aplica al corte si la fecha del evento es igual o anterior a la fecha de corte y no se levantó antes.</li>
                            <li>Marcar o levantar no altera un corte ya calculado hasta que se recalcula, y <strong>nunca</strong> uno cerrado.</li>
                        </ul>
                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-7">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Conciliación con SIESA</h6>
                            <p class="det-subtitulo">¿En qué se diferencia lo que deterioro de lo que SIESA reporta, y quién explicó cada partida?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.7</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p>Cruza, operación por operación, el capital que reporta SIESA contra el capital total del sistema de factoring (corriente + vencido). La diferencia se lee como <code>SIESA &minus; Factoring</code>, con una tolerancia de un peso.</p>
                        <p><strong>Saldo de SIESA para la conciliación:</strong> capital, del documento <code>OPE</code> de la operación o, en la cartera antigua, de los <code>CC</code>/<code>FAT</code>/<code>FEX</code> que la nombran en su nota. El interés facturado no entra en ese saldo, para no duplicar el congelado; sí entra en el valor nominal que topa la deducción fiscal. Las operaciones refinanciadas se cruzan por el <code>OPE</code> 10000000 + n. Según cuántas operaciones tenga el tercero en el corte:</p>
                        <ul>
                            <li><strong>Una operación</strong> &mdash; se le atribuye todo el capital del tercero en SIESA, con cualquier prefijo.</li>
                            <li><strong>Varias operaciones</strong> &mdash; si la operación tiene <code>OPE</code>, solo cuenta el <code>OPE</code>.</li>
                        </ul>
                        <p>Arriba, cuatro tarjetas: Partidas por explicar, Diferencia neta, Operaciones conciliadas y Cobertura del cruce. Las partidas se reparten en tres bloques, y la separación es deliberada:</p>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Bloque</th><th>Qué significa</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto"><strong>Operaciones sin saldo en SIESA</strong></td><td class="det-texto">El módulo las deteriora y SIESA no reporta saldo. No hay contra qué comparar</td></tr>
                                <tr><td class="det-texto"><strong>Saldos de SIESA sin operación en el corte</strong></td><td class="det-texto">SIESA reporta cartera que este corte no deteriora. Solo incluye documentos <code>OPE</code> con saldo</td></tr>
                                <tr><td class="det-texto"><strong>Diferencias de saldo</strong></td><td class="det-texto">La operación existe en las dos fuentes, con importes distintos</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <p>Las partidas se filtran por <strong>Estado</strong> &mdash;el filtro arranca en «Pendientes»; también ofrece «En gestión», «Explicadas» y «Todas»&mdash; y se buscan por cliente, NIT u operación. Cada partida lleva uno de tres estados:</p>
                        <ul>
                            <li><span class="det-badge det-ambar">Por explicar</span> &mdash; todavía nadie la ha gestionado.</li>
                            <li><span class="det-badge det-ambar">En gestión</span> &mdash; alguien la está revisando; <strong>no libera el cierre</strong>.</li>
                            <li><span class="det-badge det-estado-cerrado">Explicada</span> &mdash; la única que libera el cierre.</li>
                        </ul>
                        <p>El botón <strong>Explicar</strong>, o <strong>Ver / editar</strong> si ya está explicada, abre el formulario «Explicar partida»: se elige «Explicada» o «En gestión» y se escribe la explicación, de hasta 500 caracteres. Queda el autor y la fecha. Con el corte cerrado el botón se ve deshabilitado.</p>
                        <p>Arriba, la barra «X de Y explicadas» muestra el avance. Las explicaciones sobreviven al recálculo del corte. El panel «Controles de cuadre» de esta pantalla solo muestra <code>C-CONCILIA</code> y los controles <code>C-SIESA-*</code>. <strong>El corte no se puede cerrar con partidas sin explicar.</strong></p>
                    </div>
                    <div class="det-aviso info">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div><strong>Punto abierto.</strong> Hoy el módulo solo ignora diferencias de hasta un peso; no hay definida una cuantía de materialidad por encima de eso. Si el residuo incluye partidas pequeñas, el módulo va a obligar a explicar ruido para poder cerrar, y el control se degrada a trámite. Es una decisión de política contable, no de diseño: ver <a href="#ayuda-7">Decisiones pendientes</a>.</div>
                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-8">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Controles y cierre</h6>
                            <p class="det-subtitulo">¿Está el corte en condiciones de cerrarse, y qué falta para que lo esté?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.8</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p>Reúne los controles del corte y la acción de cerrarlo.</p>
                        <h6 class="det-ayuda-sub">Qué muestra la pantalla</h6>
                        <ul>
                            <li><strong>Cuatro tarjetas</strong> &mdash; «Requisitos pendientes» (N de 3), «Prórrogas que reducen mora», «Bajas por clasificar» y «Controles en falla».</li>
                            <li><strong>Cierre del corte</strong> &mdash; los requisitos pendientes, cada uno con su enlace para resolverlo («Ver controles», «Explicar partidas» o «Clasificar bajas»), y los botones de cierre.</li>
                            <li><strong>Cierre con salvedades</strong> &mdash; aparece en un corte cerrado con salvedad: muestra el motivo y los requisitos que estaban sin resolver.</li>
                            <li><strong>Tablero de controles</strong> &mdash; el estado de C-1 a C-5; debajo, los paneles de C-1 y C-2, los controles de cuadre y la bitácora.</li>
                        </ul>
                        <h6 class="det-ayuda-sub">Dos controles se gestionan aquí</h6>
                        <ul>
                            <li><strong>C-1 · Prórrogas que reducen la antigüedad de la mora.</strong> La prórroga traslada las cuotas al final, con lo cual la operación puede bajar de rango y <strong>liberar deterioro</strong>. La pantalla lista cada mes las operaciones cuya antigüedad bajó, con el deterioro que eso liberó, para validación. La lista incluye también las caídas de mora por pagos, no solo por prórrogas.
                                <ul>
                                    <li><strong>Efecto fiscal</strong> &mdash; la deducción del 33 % exige más de un año de vencimiento y la prórroga reinicia el conteo. Las operaciones que dejaron de cumplir esa mora llevan <span class="det-badge det-ambar">Umbral fiscal</span>.</li>
                                    <li><strong>No confundir con el interés de prórroga</strong> &mdash; ese es el saldo de prórroga vencido que reporta SIESA y que suma a la base de deterioro; este control es el acuerdo que traslada cuotas y baja la antigüedad.</li>
                                    <li><strong>Cierre</strong> &mdash; el listado no bloquea el cierre, pero el control de cuadre <code>C-PRORROGA</code> sí lo bloquea si falla.</li>
                                </ul>
                            </li>
                            <li><strong>C-2 · Bajas del período.</strong> Las operaciones que estaban en el corte anterior y ya no están. Las pendientes llevan <span class="det-badge det-ambar">Sin clasificar</span>. El botón <strong>Clasificar</strong> abre «Clasificar la salida de la operación», con «Causa de la salida», «Observación» obligatoria de hasta 500 caracteres y, cuando aplica, «Operación nueva» de hasta 30 caracteres. Las causas son:
                                <ul>
                                    <li><strong>Recaudo total de la operación</strong>.</li>
                                    <li><strong>Castigo de cartera</strong> &mdash; lleva <span class="det-badge det-inactivo">Cierra fiscal</span>: congela el acumulado fiscal de la operación en la baja y el asiento lo reporta como informativo. No modifica el acumulado fiscal de años anteriores que usa el cálculo.</li>
                                    <li><strong>Cierre con apertura de una operación nueva</strong> &mdash; pide la referencia de la nueva, pero no la vincula.</li>
                                    <li><strong>Otra causa, explicada en la observación</strong>.</li>
                                </ul>
                            </li>
                        </ul>
                        <p>Sin la etiqueta, una reapertura se vería igual que un recaudo en la descomposición del movimiento del mes.</p>
                        <p>El tablero muestra además <strong>C-4</strong> y <strong>C-5</strong> como pendientes: todavía no están implementados.</p>
                        <p><strong>Los controles de cuadre.</strong> Son veintiocho y cubren la integridad de la extracción, el cuadre de la base, las cuotas repetidas, las suspensiones, el cruce con SIESA, el bloque fiscal y el diferido, y el movimiento del mes. Cada uno queda en uno de tres estados: <strong>en cero</strong> (cuadra), <strong>con diferencia</strong> (falla) o <strong>n/a</strong> (no aplica a este corte o es solo informativo). Los más relevantes para Contabilidad:</p>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Control</th><th>Qué verifica</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>C-CAPITAL</code> / <code>C-INTERES</code></td><td class="det-texto">El detalle de cuotas contra el consolidado por operación</td></tr>
                                <tr><td><code>C-BASE</code></td><td class="det-texto">Base de deterioro = capital vencido + interés vencido + interés de prórroga</td></tr>
                                <tr><td><code>C-SIESA-PRORROGA</code></td><td class="det-texto">Que la prórroga vencida de SIESA que entró en la base coincida con la del snapshot para esas mismas operaciones</td></tr>
                                <tr><td><code>C-SUSPENSION</code></td><td class="det-texto">Que la reducción de base por congelamiento coincida con la reducción de interés vencido de las mismas operaciones</td></tr>
                                <tr><td><code>C-MARCAS</code></td><td class="det-texto">Base de las operaciones con marca aplicable que no se pudieron congelar. Bloquea el cierre</td></tr>
                                <tr><td><code>C-CONCILIA</code></td><td class="det-texto">Que no queden partidas de conciliación sin explicar</td></tr>
                                <tr><td><code>C-FISCAL-TOPE</code></td><td class="det-texto">Que ninguna deducción supere el tope disponible</td></tr>
                                <tr><td><code>C-FISCAL-NOMINAL</code></td><td class="det-texto"><strong>Informativo:</strong> acumulado fiscal declarado de años anteriores por encima del valor nominal en SIESA, que el módulo no corrige</td></tr>
                                <tr><td><code>C-CIERRE-FISCAL</code></td><td class="det-texto">Que el acumulado fiscal escrito por el cierre de diciembre coincida con la deducción del año del corte</td></tr>
                                <tr><td><code>C-DIF-TEMP</code> / <code>C-DIFERIDO</code></td><td class="det-texto">Diferencia temporaria e impuesto diferido contra sus componentes</td></tr>
                                <tr><td><code>C-REVERSION</code></td><td class="det-texto">Que ninguna operación con base de deterioro quede sin proyección de año de reversión</td></tr>
                                <tr><td><code>C-MOVIMIENTO</code></td><td class="det-texto">Que deterioro anterior + altas + variación de las que continúan &minus; bajas dé el deterioro del corte</td></tr>
                                <tr><td><code>C-VARIACION</code></td><td class="det-texto">Altas + variación de las que continúan contra la variación recalculada operación por operación contra el corte anterior</td></tr>
                                <tr><td><code>C-LIBRO</code></td><td class="det-texto">El gasto del período del módulo contra el del libro. <strong>Informativo</strong>: nunca bloquea, porque la cifra del mes anterior del libro se digita a mano</td></tr>
                                <tr><td><code>C-PRORROGA</code></td><td class="det-texto">C-1: deterioro liberado por las operaciones que bajaron de antigüedad contra el recalculado contra el corte anterior</td></tr>
                                <tr><td><code>C-SALIDAS</code></td><td class="det-texto">Que no queden bajas sin clasificar</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <h6 class="det-ayuda-sub">Qué exige el cierre</h6>
                        <ul>
                            <li>Ningún control de cuadre en falla.</li>
                            <li>Ninguna partida de conciliación sin explicar.</li>
                            <li>Ninguna baja sin clasificar.</li>
                        </ul>
                        <p>Solo se cierra un corte <span class="det-badge det-estado-calculado">CALCULADO</span>.</p>
                        <p><strong>Cierre con salvedad.</strong> Si algo de lo anterior falla y aun así hay que cerrar, se puede hacer <strong>expresamente</strong> con el enlace <strong>Forzar el cierre con salvedad</strong>. Solo aparece si el corte no se puede cerrar y el usuario tiene los dos permisos: <strong>Cerrar corte</strong> y <strong>Forzar cierre con salvedad</strong>. Exige un motivo escrito de hasta 500 caracteres.</p>
                        <p>El corte queda marcado <span class="det-badge det-salvedad">Con salvedades</span> de forma permanente, y se congela la foto del cierre: qué controles fallaban y con qué cifra. Un motivo escrito sobre un corte sin bloqueos no genera salvedad. No es una salida cómoda: es una declaración.</p>
                        <p><strong>Cierre de diciembre.</strong> Además de cerrar, escribe el acumulado fiscal del año gravable. Si después de escribirlo <code>C-CIERRE-FISCAL</code> no cuadra, el cierre entero se revierte.</p>
                        <p><strong>Reabrir.</strong> Exige permiso propio y motivo obligatorio. El corte vuelve a <span class="det-badge det-estado-calculado">CALCULADO</span>, se revierte el acumulado fiscal que escribió ese cierre y todo queda en bitácora. Mientras está cerrado, un corte no admite explicar partidas, clasificar bajas, cargar cifras del libro ni eliminarse.</p>
                        <p><strong>Bitácora del corte.</strong> Panel visible con el permiso <strong>Consultar bitácora</strong>. Se filtra por Acción, Usuario, Desde, Hasta y «Operación o valor», y registra autor, fecha y dirección IP de los actos sobre el corte.</p>
                    </div>
                    <div class="det-aviso info">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div>La bitácora muestra como máximo los 500 eventos más recientes y avisa cuando hay más. Marcar o levantar suspensiones y eliminar cortes quedan registrados sin corte asociado, por eso no aparecen en la bitácora de ningún corte.</div>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <p>Los permisos de cada acción están en <a href="#ayuda-4-0">Cómo se entra y quién puede hacer qué</a>.</p>
                    </div>
                    <div class="det-aviso">
                        <i class="fas fa-triangle-exclamation mt-1"></i>
                        <div>Hay controles que <strong>nunca bloquean a propósito</strong> porque publican una cifra y no un descuadre: <code>C-DUPLICADAS</code>, <code>C-SIESA-NOTA</code>, <code>C-FISCAL-NOMINAL</code> y <code>C-LIBRO</code>. El caso típico es <code>C-DUPLICADAS</code>, el que cuenta las cuotas que el sistema de factoring entrega repetidas. Nadie puede corregir desde este módulo un defecto que vive en la base de factoring, y bloquear el cierre con él convertiría todos los meses en un cierre con salvedad, vaciando de sentido esa marca. Lo que sí bloquea es <code>C-DUPLICADAS-BASE</code>: <strong>cuánta base de deterioro se dejó fuera</strong>. Vale cero mientras ninguna cuota repetida esté vencida. El día que una lo esté, el corte no cierra sin explicación, y nadie tiene que acordarse de revisarlo.</div>
                    </div>
                </div>

                <div class="det-panel" id="ayuda-4-9">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Exportar</h6>
                            <p class="det-subtitulo">¿Cómo saco el corte del módulo para archivarlo, soportarlo o cargarlo?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.9</span>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Salida</th><th>Para qué</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto"><strong>Resumen del corte · PDF</strong></td><td class="det-texto">Matriz, movimiento del mes y controles, para archivo. Sale con la marca «PRELIMINAR» si el corte no está cerrado; uno cerrado con salvedad lleva «CERRADO CON SALVEDADES · motivo» y una última página con los requisitos pendientes</td></tr>
                                <tr><td class="det-texto"><strong>Detalle por operación · Excel</strong></td><td class="det-texto">Revisión y soporte. Desde el Detalle sale con los filtros aplicados; desde las demás pantallas, con todas las operaciones del corte</td></tr>
                                <tr><td class="det-texto"><strong>Asiento contable · archivo plano</strong></td><td class="det-texto">CSV separado por punto y coma con el ajuste del período, el auxiliar por producto, los castigos (informativos), el anexo fiscal y el anexo de diferido</td></tr>
                                <tr><td class="det-texto"><strong>Excel de transición</strong></td><td class="det-texto">Con la estructura del libro actual, para el período de convivencia: hojas <code>DETERIORO</code>, <code>Tabla final</code>, <code>DIFERENCIAS</code> y <code>1399</code></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <p>Todas las salidas están en el menú <strong>Exportar</strong> de las siete pantallas del corte, con permiso propio. No está disponible en un corte <span class="det-badge det-estado-abierto">ABIERTO</span>; en uno <span class="det-badge det-estado-calculado">CALCULADO</span> la salida es <strong>preliminar</strong>, y así lo dicen el nombre del archivo y su contenido. El nombre termina en «_preliminar» o, si el corte se cerró con salvedad, en «_con-salvedades».</p>
                    </div>
                    <div class="det-aviso">
                        <i class="fas fa-triangle-exclamation mt-1"></i>
                        <div><strong>El Excel del detalle no cuadra con la matriz del Resumen.</strong> Cuando la operación tiene saldo atribuido en SIESA, sus columnas CAPITAL VENCIDO e INTERES VENCIDO traen el valor de SIESA; la matriz usa el de factoring. El de factoring está en las columnas CAPITAL VENCIDO FACTORING e INTERES VENCIDO FACTORING.</div>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <h6 class="det-ayuda-sub">Detalle por operación · Excel</h6>
                        <p>Además de las cifras de la grilla trae PRORROGA VENCIDA SIESA, ESTADO REVERSION, FUENTE VENCIDOS, SUSPENDIDA, ORIGEN BASE y BASE SIN SUSPENDER.</p>
                        <ul>
                            <li><strong>Columnas con sufijo FACTORING</strong> &mdash; salen del sistema de factoring: CAPITAL CORRIENTE, INTERES CORRIENTE, INTERES MORA, CAPITAL VENCIDO, INTERES VENCIDO, CAPITAL MES ANTERIOR y VARIACION CAPITAL, cada una seguida de «FACTORING».</li>
                            <li><strong>CAPITAL VENCIDO e INTERES VENCIDO</strong> &mdash; sin sufijo: son las cifras que usa la base y pueden venir de SIESA o de factoring; lo indica FUENTE VENCIDOS.</li>
                        </ul>
                        <p>Al final trae un bloque de comparación para que Contabilidad filtre y compare SIESA contra factoring por operación:</p>
                        <ul>
                            <li><strong>Capital</strong> &mdash; CAPITAL TOTAL FACTORING (corriente + vencido), CAPITAL SIESA y DIFERENCIA CAPITAL (SIESA &minus; FACTORING), la misma diferencia de la pantalla Conciliación.</li>
                            <li><strong>Interés</strong> &mdash; INTERES SIESA (saldo facturado) y DIFERENCIA INTERES (SIESA &minus; VENCIDO FACTORING), que resta INTERES VENCIDO FACTORING.</li>
                            <li><strong>Vencidos</strong> &mdash; CAPITAL VENCIDO SIESA, INTERES VENCIDO SIESA y DIFERENCIA INTERES VENCIDO (SIESA &minus; FACTORING), que resta INTERES VENCIDO FACTORING.</li>
                        </ul>
                        <p>El interés se compara contra el vencido de factoring porque INTERES CORRIENTE FACTORING es el interés programado de las cuotas futuras, aún no facturado, y SIESA solo registra el facturado.</p>
                    </div>
                    <div class="det-aviso info">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div>Si la operación no está en SIESA, CAPITAL SIESA, INTERES SIESA, CAPITAL VENCIDO SIESA e INTERES VENCIDO SIESA quedan vacías, y cada diferencia es la cifra de factoring correspondiente en negativo.</div>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <h6 class="det-ayuda-sub">Asiento contable · archivo plano</h6>
                        <ul>
                            <li><span class="det-badge det-ambar">Sin cuentas</span> &mdash; Contabilidad no ha definido las cuentas contables.</li>
                            <li><span class="det-badge det-inactivo">Sin corte anterior</span> &mdash; es el primer corte de la serie: no hay contra qué medir el ajuste.</li>
                            <li><span class="det-badge det-inactivo">Sin calcular</span> &mdash; hay que calcular el corte antes de exportar.</li>
                        </ul>
                        <p>Las cuentas son por concepto (aumento, liberación y castigo) y por producto. Los anexos fiscal y de diferido salen sin cuenta. Las cuentas de SIESA que usa el cálculo, 13050501 y 13451001, son otra cosa: no son las del asiento.</p>
                    </div>
                    <div class="det-aviso">
                        <i class="fas fa-triangle-exclamation mt-1"></i>
                        <div><strong>Las cuentas se congelan al calcular el corte.</strong> Si Contabilidad las define después, el asiento de ese corte sigue en «Sin cuentas»: hay que recalcularlo, y si está cerrado, reabrirlo antes.</div>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <h6 class="det-ayuda-sub">Excel de transición</h6>
                        <ul>
                            <li><strong>Título</strong> &mdash; la hoja <code>DETERIORO</code> termina en «· PRELIMINAR» o «· CERRADO CON SALVEDADES» según el estado del corte. Desde agosto de 2026 su columna I incluye la prórroga y por eso ya no es G + H.</li>
                            <li><strong>Hoja <code>1399</code></strong> &mdash; incluye operaciones que ya no están en el corte, señaladas en la columna «EN EL CORTE».</li>
                            <li><strong>Límite</strong> &mdash; no se genera si el corte tiene más de 6 rangos de mora, porque el libro solo tiene seis columnas.</li>
                        </ul>
                        <p>El exportable de transición existe para que, mientras dure la validación en paralelo, se pueda comparar el módulo contra el libro sin rehacer nada a mano.</p>
                    </div>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-5">
                <h5 class="det-ayuda-tit"><span class="num">Sección 5</span>Cómo amarran las cifras entre sí</h5>
                <p class="det-ayuda-lede">Este es el mapa que conviene tener a mano en la revisión mensual. La mayoría de estas igualdades tienen un control de cuadre que bloquea el cierre si falla (<code>C-PARTIC</code>, <code>C-BASE</code>, <code>C-DIF-TEMP</code>, <code>C-DIFERIDO</code>, <code>C-MOVIMIENTO</code>, <code>C-VARIACION</code>); las demás se cumplen por construcción del cálculo.</p>
                <div class="det-panel">
                    <div class="det-ayuda-identidad">
                        <p class="det-corte-bloque">La cartera y la base</p>
                        <div class="grupo">
                            <span class="izq">Capital corriente + Capital vencido</span><span class="ig">=</span><span class="der">Capital total</span>
                            <span class="izq">Interés corriente + Interés vencido</span><span class="ig">=</span><span class="der">Interés total</span>
                            <span class="izq">Capital vencido + Interés vencido + Interés de prórroga</span><span class="ig">=</span><span class="der">Base de deterioro</span>
                            <span class="izq">Base × % del rango, operación por operación</span><span class="ig">=</span><span class="der">Deterioro contable</span>
                        </div>
                        <p class="det-corte-bloque">El bloque fiscal</p>
                        <div class="grupo">
                            <span class="izq">Acum. anterior + Deducción del año</span><span class="ig">=</span><span class="der">Fiscal acumulado</span>
                            <span class="izq">Deterioro contable &minus; Fiscal acumulado</span><span class="ig">=</span><span class="der">Diferencia temporaria</span>
                            <span class="izq">Diferencia temporaria × tarifa de renta</span><span class="ig">=</span><span class="der">Impuesto diferido</span>
                        </div>
                        <p class="det-corte-bloque">El gasto del período</p>
                        <div class="grupo">
                            <span class="izq">Deterioro del corte &minus; Deterioro del corte anterior</span><span class="ig">=</span><span class="der">Gasto del período</span>
                            <span class="izq">Altas + Variación de las que continúan &minus; Bajas</span><span class="ig">=</span><span class="der">Gasto del período</span>
                        </div>
                    </div>
                </div>
                <div class="det-ayuda-texto">
                    <p><strong>La única identidad que la pantalla de resumen no muestra explícitamente</strong> es la de la base: las tarjetas de Capital e Interés son totales, la de Base es solo lo vencido más el interés de prórroga. De ahí la confusión habitual.</p>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-6">
                <h5 class="det-ayuda-tit"><span class="num">Sección 6</span>Lo que el módulo deliberadamente no hace</h5>
                <p class="det-ayuda-lede">Vale la pena decirlo en voz alta, porque son decisiones tomadas, no faltantes.</p>
                <div class="det-panel">
                    <div class="det-ayuda-texto">
                        <ul>
                            <li><strong>Reestructuraciones.</strong> No existen como figura. Se cierra la operación y se abre una nueva, y el conteo de mora arranca desde cero. El módulo <strong>no vincula</strong> la operación nueva con la cerrada ni rastrea la antigüedad anterior.</li>
                            <li><strong>Garantías.</strong> No se descuentan. Mismo tratamiento para financiación, factoring y libranzas.</li>
                            <li><strong>Vinculados económicos.</strong> No aplica. No hay marca de vinculados.</li>
                            <li><strong>Clasificación por cliente.</strong> La mora se clasifica <strong>por operación</strong>, no por deudor. No se arrastra la peor calificación de un cliente al resto de sus obligaciones.</li>
                            <li><strong>El módulo no castiga ni marca por su cuenta.</strong> Sugiere candidatas y señala salidas; la decisión y el registro son de una persona, con permiso y con bitácora.</li>
                            <li><strong>El módulo solo lee SIESA y el sistema de factoring.</strong> Hoy no escribe nada fuera de su propia base: la marca de suspensión vive en el módulo. Escribirla en factoring para que deje de facturar (<code>D-12</code>) depende del punto B de las decisiones pendientes.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-7">
                <h5 class="det-ayuda-tit"><span class="num">Sección 7</span>Decisiones que el módulo necesita de Contabilidad</h5>
                <div class="det-aviso">
                    <i class="fas fa-triangle-exclamation mt-1"></i>
                    <div>Estas son las que quedan abiertas hoy. No son detalles de forma: <strong>cada una cambia cifras</strong> del corte.</div>
                </div>

                <div class="det-ayuda-decision">
                    <div class="ref">
                        <span class="det-badge det-ambar">B</span>
                        <b>¿El sistema de factoring tiene un campo propio de suspensión, y su facturación lo respeta?</b>
                    </div>
                    <p>De eso depende que la marca <strong>efectivamente detenga la facturación</strong>. Hoy la marca vive en el módulo. Es pregunta para el proveedor.</p>
                </div>

                <div class="det-ayuda-decision">
                    <div class="ref">
                        <span class="det-badge det-ambar">C</span>
                        <b>¿Con qué antigüedad de mora se deteriora una operación que ya no está en factoring?</b>
                    </div>
                    <p>Sin fecha no hay días, sin días no hay rango y sin rango no hay porcentaje. Tres opciones sobre la mesa: presumir rango F al 100 %, conservar la última antigüedad conocida envejeciéndola, o digitar la fecha en el archivo de cargue. <strong>Las tres dan cifras distintas.</strong></p>
                </div>

                <div class="det-ayuda-decision">
                    <div class="ref">
                        <span class="det-badge det-ambar">F</span>
                        <b>¿Cuál es la cuantía mínima de una diferencia de conciliación que sí debe explicarse?</b>
                    </div>
                    <p>Sin umbral, el cierre obliga a explicar partidas de centavos y el control se vuelve trámite.</p>
                </div>

                <div class="det-ayuda-decision">
                    <div class="ref">
                        <span class="det-badge det-inactivo">&mdash;</span>
                        <b>¿El tipo <code>CC1</code> de SIESA entra al saldo de cartera?</b>
                    </div>
                    <p>Son 16.106 filas por 686.811.619. Están llaveadas por número de operación igual que los <code>OPE</code>, pero no fueron nombradas en la definición de prefijos.</p>
                </div>

            </section>

            <section class="det-ayuda-seccion" id="ayuda-8">
                <h5 class="det-ayuda-tit"><span class="num">Sección 8</span>Preguntas frecuentes</h5>
                <div class="det-panel">
                    <div class="det-ayuda-faq">
                        <p class="q">¿Por qué el número de cuotas del resumen no coincide con las cuotas que suman las operaciones?</p>
                        <p class="a">La tarjeta «Cuotas» es el conteo crudo de lo que entregó el origen. El consolidado excluye las cuotas que factoring entregó repetidas. La diferencia es exactamente eso, y el control <code>C-DUPLICADAS</code> la mide.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿Por qué el Capital de la pantalla de Cortes no coincide con la tarjeta Capital del Resumen?</p>
                        <p class="a">La columna Capital de Cortes suma todas las cuotas que entregó el origen, incluidas las repetidas. La tarjeta del Resumen sale del consolidado por operación, que las excluye. La diferencia es el capital de las cuotas repetidas.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿Por qué la base de una operación es mayor que su capital vencido más su interés vencido?</p>
                        <p class="a">Porque la base tiene un tercer término: el <strong>interés de prórroga</strong>, el saldo de prórroga vencido que reporta SIESA. No viene de las cuotas del sistema de factoring, por eso no aparece al descender al detalle de cuotas. En el detalle por operación se muestra como una sublínea dentro de la celda de la base, y en el resumen tiene columna propia.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿Puedo recalcular un corte si me equivoqué en un parámetro?</p>
                        <p class="a">Sí, mientras esté <span class="det-badge det-estado-abierto">ABIERTO</span> o <span class="det-badge det-estado-calculado">CALCULADO</span>, <strong>y solo mientras el sistema de factoring tenga cargado ese mismo mes</strong> (la tabla origen se sobrescribe cada mes). El recálculo vuelve a tomar las paramétricas vigentes a la fecha de corte, conserva las explicaciones de conciliación y las clasificaciones de bajas, y queda en bitácora. Un corte <span class="det-badge det-estado-cerrado">CERRADO</span> hay que reabrirlo primero (permiso propio); al reabrirse vuelve a <span class="det-badge det-estado-calculado">CALCULADO</span>.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿Por qué una operación aparece con 0 % de deterioro?</p>
                        <p class="a">Porque está en rango A (0 a 30 días), donde el porcentaje definido es 0 %, o porque está corriente y no tiene rango.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿El deterioro de una operación suspendida deja de crecer?</p>
                        <p class="a">El interés sí. El capital no: sigue deteriorándose por su rango hasta llegar al 100 % a partir de los 721 días (rango F).</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿La cifra fiscal de un corte mensual es la que va en la declaración?</p>
                        <p class="a">No. Los cortes mensuales son estimaciones. La deducción se determina sobre obligaciones que subsistan al <strong>31 de diciembre</strong>, y solo el corte de diciembre produce la cifra definitiva del año gravable.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">Si un control está en rojo, ¿las cifras están mal?</p>
                        <p class="a">No necesariamente: significa que hay algo sin explicar o sin clasificar. El control dice qué revisar, no que el cálculo esté equivocado. Lo que sí es cierto es que el corte no debería cerrarse así.</p>
                    </div>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-anexo">
                <h5 class="det-ayuda-tit"><span class="num">Anexo</span>De dónde sale cada dato</h5>
                <div class="det-panel">
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Dato</th><th>Fuente</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto">Cuotas, saldos, fechas de vencimiento, fecha inicial de mora</td><td class="det-texto">Sistema de factoring, por consulta directa</td></tr>
                                <tr><td class="det-texto">Saldos de cartera por operación</td><td class="det-texto">SIESA, solo lectura: por operación (documento <code>OPE</code> o nota que la nombra, o todo el saldo del tercero si tiene una sola operación en el corte), acotado a las cuentas 13050501 (capital) y 13451001 (interés)</td></tr>
                                <tr><td class="det-texto">Saldo de prórroga vencido por operación</td><td class="det-texto">SIESA, solo lectura</td></tr>
                                <tr><td class="det-texto">Interés congelado de las operaciones suspendidas</td><td class="det-texto">Facturas automáticas de interés de SIESA &mdash;<code>FAT</code>, y <code>CC</code> de la cuenta 13451001 para las anteriores a 2022&mdash; hasta el mes del evento, por su saldo pendiente a esa fecha</td></tr>
                                <tr><td class="det-texto">Rangos de mora y porcentajes contables</td><td class="det-texto">Paramétrica del módulo, con vigencias</td></tr>
                                <tr><td class="det-texto">Tarifa de renta</td><td class="det-texto">Paramétrica del módulo, congelada por corte (art. 240 ET)</td></tr>
                                <tr><td class="det-texto">Provisión fiscal acumulada de años anteriores</td><td class="det-texto">Cargue desde el archivo de Contabilidad (hoja 1399), que ejecuta Sistemas (<a href="#ayuda-anexo-cargues">ver cargues</a>); desde el primer cierre de diciembre, lo escribe el propio cierre del corte</td></tr>
                                <tr><td class="det-texto">Estado inicial de las suspensiones</td><td class="det-texto">Cargue desde el archivo de Contabilidad, que ejecuta Sistemas (<a href="#ayuda-anexo-cargues">ver cargues</a>)</td></tr>
                                <tr><td class="det-texto">Fecha del evento del cargue inicial, cuando el archivo no la trae</td><td class="det-texto">Última factura <code>FAT</code> del cliente en SIESA</td></tr>
                                <tr><td class="det-texto">Causal, fecha del evento, soporte y observación de cada marca</td><td class="det-texto">Digitación en el módulo, con bitácora</td></tr>
                                <tr><td class="det-texto">Causales de suspensión y de baja</td><td class="det-texto">Paramétrica del módulo, con vigencias</td></tr>
                                <tr><td class="det-texto">Cifras del libro para <code>C-LIBRO</code> y la conciliación con el libro</td><td class="det-texto">Cargue desde el archivo de Contabilidad, que ejecuta Sistemas (<a href="#ayuda-anexo-cargues">ver cargues</a>)</td></tr>
                                <tr><td class="det-texto">Cuentas contables del asiento</td><td class="det-texto">Paramétrica del módulo, congelada por corte (hoy sin definir)</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="det-subtitulo mt-3 mb-0">Todo lo demás lo calcula el módulo.</p>
                </div>

                <div class="det-panel" id="ayuda-anexo-cargues">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Cargues que ejecuta Sistemas</h6>
                            <p class="det-subtitulo">¿Qué datos entran al módulo por fuera de las pantallas y a quién se le piden?</p>
                        </div>
                        <span class="det-badge det-inactivo">Sin pantalla</span>
                    </div>
                    <div class="det-aviso info">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div>Estos cargues no tienen pantalla. Se solicitan a Sistemas indicando el corte (cuando aplica) y el archivo.</div>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Qué se carga</th><th>Cuándo pedirlo</th><th>Restricción o efecto</th><th>Comando (referencia para Sistemas)</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto">Cifras del libro de un corte</td><td class="det-texto">Para ver la conciliación con el libro en Evolución y el control <code>C-LIBRO</code></td><td class="det-texto">No se puede cargar sobre un corte cerrado. Repetir el cargue reemplaza las cifras</td><td><code>deterioro:cargar-validacion-excel {corte} {archivo}</code></td></tr>
                                <tr><td class="det-texto">Provisión fiscal acumulada de años anteriores (hoja 1399)</td><td class="det-texto">Al migrar el acumulado fiscal, antes del primer cierre de diciembre</td><td class="det-texto">El archivo se rechaza entero si una fila es inválida. Repetirlo deja la tabla igual</td><td><code>deterioro:cargar-acumulado-fiscal {archivo}</code></td></tr>
                                <tr><td class="det-texto">Estado inicial de las suspensiones</td><td class="det-texto">Al recibir o corregir el archivo de suspensiones de Contabilidad</td><td class="det-texto">Sin causal, la marca queda pendiente de clasificar. Sistemas puede simularlo antes sin grabar</td><td><code>deterioro:cargar-suspensiones {archivo}</code></td></tr>
                                <tr><td class="det-texto">Interés congelado de las marcas vigentes</td><td class="det-texto">Cuando cambia la regla con que se congela el interés</td><td class="det-texto">Reescribe el interés congelado y lo deja en bitácora. Sistemas puede simularlo antes sin grabar. <strong>Después hay que recalcular los cortes ABIERTOS o CALCULADOS</strong></td><td><code>deterioro:recongelar-suspensiones</code></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <p class="det-ayuda-pie">Guía de lectura para Contabilidad · 9 de octubre de 2026. El documento técnico del módulo va aparte.</p>

        </div>
    </div>

</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const enlaces = document.querySelectorAll('#indiceAyuda a');
        const secciones = document.querySelectorAll('#divAyuda .det-ayuda-seccion');
        if (!('IntersectionObserver' in window) || !secciones.length) return;
        const visibles = new Set();
        const observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(e => e.isIntersecting ? visibles.add(e.target.id) : visibles.delete(e.target.id));
            let activa = '';
            secciones.forEach(s => { if (!activa && visibles.has(s.id)) activa = s.id; });
            if (!activa) return;
            enlaces.forEach(a => a.classList.toggle('actual', a.getAttribute('href') === '#' + activa));
        }, { rootMargin: '-10% 0px -70% 0px' });
        secciones.forEach(s => observador.observe(s));
    });
</script>
@endsection
