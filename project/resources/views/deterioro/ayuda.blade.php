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
            <li><a href="#ayuda-2"><span class="num">02</span><span class="rot">Seis palabras</span></a></li>
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
                <p class="det-ayuda-lede">Hoy el deterioro se calcula en un libro de Excel de 68 MB por corte, con siete entradas que se digitan a mano cada mes: los saldos de SIESA por cliente, las prórrogas y reservas, la cartera de SIESA por operación, el deterioro del mes anterior por rango, la provisión fiscal acumulada y las notas de gestión.</p>
                <div class="det-ayuda-texto">
                    <p>El módulo hace <strong>exactamente el mismo cálculo, con las mismas reglas</strong>, y cambia cuatro cosas:</p>
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
                    <div><strong>El criterio de aceptación fue la réplica al centavo.</strong> El módulo reprodujo el corte de julio de 2026 contra el libro, cifra por cifra. No es un cálculo nuevo: es el mismo cálculo, auditable.</div>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-2">
                <h5 class="det-ayuda-tit"><span class="num">Sección 2</span>Seis palabras que conviene tener claras antes de entrar</h5>
                <div class="det-ayuda-texto">
                    <h6 class="det-ayuda-sub">Corte</h6>
                    <p>Un mes. Es la unidad de todo: se crea, se calcula, se revisa y se cierra. Todas las pantallas del módulo, salvo la primera, son el detalle <strong>de un corte</strong>; por eso se entra a ellas desde la fila del corte y nunca desde el menú. La intención es que nunca haya duda de qué mes se está mirando.</p>

                    <h6 class="det-ayuda-sub">Corriente y vencido</h6>
                    <p>Cada cuota se clasifica según su fecha de vencimiento contra la fecha de corte. Si ya venció, su saldo es <em>vencido</em>; si no, es <em>corriente</em>. Esta partición es la que explica casi todas las preguntas de cuadre.</p>

                    <h6 class="det-ayuda-sub">Base de deterioro</h6>
                    <p><code>capital vencido + interés vencido</code>. <strong>Solo la parte vencida.</strong> Excluye a propósito el saldo de administración y el interés de mora.</p>

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
                    <p>Cuando una operación entra en suspensión de intereses, el interés deja de crecer y se queda en la base por el valor que tenía a la fecha del evento. <strong>No se reversa contra el ingreso ni sale del balance.</strong> El capital sigue deteriorándose normalmente.</p>

                    <h6 class="det-ayuda-sub">Tope fiscal</h6>
                    <p>La deducción fiscal del año es el 33 % de la base, pero nunca por encima de lo que falta para completar el saldo real de la obligación. Es la pieza más delicada del cálculo y está en <a href="#ayuda-4-4">Contable contra fiscal</a>.</p>
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
                            <span><strong>Se consolida por operación.</strong> Se suman las cuotas de cada operación, se determina su rango por la antigüedad de la mora, y se calcula la base. Aquí se excluyen las cuotas que el origen entregó repetidas: siguen en el detalle, pero no se cuentan dos veces.</span>
                        </li>
                        <li>
                            <span class="det-ayuda-paso">4</span>
                            <span><strong>Se calcula el deterioro.</strong> Contable (base × % del rango) y fiscal (33 % anual con tope). Antes de eso se aplican las suspensiones vigentes, que congelan el interés.</span>
                        </li>
                        <li>
                            <span class="det-ayuda-paso">5</span>
                            <span><strong>Se cuadra y se cierra.</strong> Se corren los controles de cuadre. Si alguno falla, el corte no cierra, salvo cierre expreso con salvedad, que queda registrado como tal para siempre.</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-4">
                <h5 class="det-ayuda-tit"><span class="num">Sección 4</span>Las pantallas, una por una</h5>

                <div class="det-panel" id="ayuda-4-1">
                    <div class="det-panel-cab">
                        <div>
                            <h6>Cortes</h6>
                            <p class="det-subtitulo">¿Qué cortes hay, en qué estado está cada uno y cuáles tienen cuadres en rojo?</p>
                        </div>
                        <span class="det-badge det-estado-vigente">4.1</span>
                    </div>
                    <div class="det-ayuda-texto">
                        <p>Es la única entrada del menú. Lista los cortes registrados con su estado, el tamaño de la cartera, el deterioro calculado y el semáforo de cuadres.</p>
                        <h6 class="det-ayuda-sub">Los tres estados de un corte</h6>
                        <ul>
                            <li><span class="det-badge det-estado-abierto">ABIERTO</span> &mdash; creado, todavía sin calcular. Se puede recalcular cuantas veces se quiera.</li>
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
                        <p>Es la réplica del bloque principal del libro. Arriba, seis tarjetas; abajo, la matriz de producto por rango de mora.</p>
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
                                <tr><td class="det-texto"><strong>Base de deterioro</strong></td><td class="det-texto"><strong>Solo</strong> capital vencido + interés vencido</td></tr>
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
                        <p class="det-subtitulo mt-3 mb-0">La tabla que está debajo de las tarjetas ya trae las cuatro columnas separadas &mdash;capital corriente, capital vencido, interés corriente, interés vencido&mdash; justamente para que esta suma sea visible fila por fila.</p>
                    </div>

                    <div class="det-ayuda-texto">
                        <p><strong>Un matiz sobre el Deterioro contable:</strong> tampoco es <code>base × un porcentaje</code>. Se calcula operación por operación con el porcentaje de <em>su</em> rango, y en las operaciones suspendidas sobre la base congelada. Por eso una regla de tres contra la base total no da.</p>
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
                        <p>La misma información del resumen, pero abierta operación por operación, filtrable y exportable. Cada fila se puede abrir hasta ver las cuotas que la componen. Tiene <strong>tres vistas</strong> que cambian las columnas: <em>Contable</em>, <em>Fiscal</em> y <em>Diferido</em>.</p>
                        <h6 class="det-ayuda-sub">Los filtros que más se usan en una revisión</h6>
                        <ul>
                            <li><strong>Solo con deterioro</strong> &mdash; deja las operaciones que efectivamente provisionan.</li>
                            <li><strong>Solo topadas</strong> &mdash; las operaciones a las que el tope fiscal les recortó la deducción. Es el filtro para revisar el artículo 145 caso por caso.</li>
                            <li><strong>Solo diferido pasivo</strong> &mdash; operaciones donde el fiscal acumulado ya superó al contable.</li>
                            <li><strong>Solo con cuotas repetidas</strong> &mdash; las operaciones afectadas por el defecto del origen.</li>
                        </ul>
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
                        <p><strong>El problema que resuelve.</strong> La política contable llega al 100 % de deterioro a los 720 días. La norma fiscal (art. 145 ET) acumula 33 % por año y necesita tres años. Esa brecha es una <strong>diferencia temporaria</strong>, y de ella sale un impuesto diferido que hasta hoy no se medía operación por operación.</p>
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
                        <p>La deducción del año es el 33 % de la base, pero nunca puede llevar el acumulado por encima del saldo real de la obligación. Formalmente:</p>
                    </div>
                    <div class="det-ayuda-codigo">Deducción del año = MAX(0 ; SI(acumulado_anterior + 33% &gt; saldo_tope
                              ; saldo_tope &minus; acumulado_anterior
                              ; 33%))</div>
                    <div class="det-ayuda-texto">
                        <p>donde <code>saldo_tope</code> es el saldo de SIESA acotado contra la base en mora. Es la regla que el módulo tenía que reproducir con exactitud, y la que el filtro <em>Solo topadas</em> del detalle permite auditar.</p>
                    </div>
                    <div class="det-aviso">
                        <i class="fas fa-triangle-exclamation mt-1"></i>
                        <div><strong>Advertencia que la pantalla muestra y conviene repetir en voz alta:</strong> la deducción se determina sobre obligaciones que <strong>subsistan al 31 de diciembre</strong>. Los cortes mensuales son estimaciones. <strong>Solo el corte de diciembre produce la cifra definitiva del año gravable.</strong></div>
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
                        <p>Los tres tienen que sumar exactamente la variación del deterioro contable. Hay un control de cuadre que lo verifica (<code>C-MOVIMIENTO</code>).</p>
                        <p><strong>Por qué importa la descomposición.</strong> En el libro, el gasto del mes se obtenía restando contra una cifra del mes anterior <strong>digitada a mano</strong>. Aquí el gasto sale de la comparación operación por operación, y además queda explicado: no es lo mismo que el deterioro suba porque entró cartera nueva a que suba porque la cartera existente envejeció.</p>
                        <p><strong>Serie histórica.</strong> Un renglón por corte, con tamaño de cartera, deterioro contable, gasto del período y el bloque fiscal. Es lo que en el libro estaba disperso en hojas ocultas.</p>
                    </div>
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
                        <p>Gestiona la suspensión de causación de intereses. <strong>No hay umbral automático por días de mora</strong>: la marca es manual, operación por operación, por usuario autorizado, con causal, fecha del evento, observación y soporte.</p>
                        <h6 class="det-ayuda-sub">Las cuatro causales que se pueden marcar</h6>
                        <ol>
                            <li>Fallecimiento del deudor sin que la aseguradora pague.</li>
                            <li>Admisión a un proceso de insolvencia.</li>
                            <li>Paso a cobro jurídico.</li>
                            <li>Finalización de las cuotas disponibles: deja de generar facturación automática.</li>
                        </ol>
                        <p>Las tres primeras son las causales de la norma (<code>D-05</code>) y dependen del deudor. La cuarta no es un evento del deudor sino de la operación: al agotarse las cuotas disponibles, factoring deja de generar la facturación automática del interés.</p>
                        <p>Las marcas del cargue inicial que aún no se han clasificado aparecen sin causal determinada. Esa condición no se puede elegir: se resuelve reclasificándolas en una de las cuatro.</p>
                        <h6 class="det-ayuda-sub">Qué pasa cuando se marca una operación</h6>
                        <ul>
                            <li>El interés <strong>deja de causarse</strong> desde la fecha del evento.</li>
                            <li>El interés reconocido hasta esa fecha <strong>se congela</strong>: permanece en el activo y en la base de deterioro por el valor que tenía. No se reversa contra el ingreso.</li>
                            <li>El <strong>capital sigue deteriorándose</strong> normalmente hasta el 100 % a los 720 días.</li>
                            <li>En factoring el interés se sigue calculando internamente, sin facturarse, y se reporta aparte para revelaciones.</li>
                        </ul>
                        <p><strong>De dónde sale el valor congelado.</strong> Son las facturas de interés (<code>FAT</code>) de esa operación hasta el mes del evento, por su <strong>saldo pendiente</strong> a esa fecha. Se toma el pendiente y no el total facturado porque lo que el cliente ya pagó no está en el activo y no puede deteriorarse.</p>
                        <p><strong>El efecto cruzado que hay que vigilar.</strong> Al congelar el interés, la base deja de crecer y con ella el gasto por deterioro del período. Los dos efectos se presentan juntos en el comparativo mensual, para que la caída del gasto no se lea como una mejora de la cartera.</p>
                        <p>La pantalla muestra además <strong>candidatas sugeridas</strong>: operaciones en mora avanzada sin marca vigente. El módulo sugiere; <strong>no marca por su cuenta</strong>.</p>
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
                        <p>Cruza, operación por operación, el saldo que reporta SIESA contra el del sistema de factoring. Está partida en tres bloques, y la separación es deliberada:</p>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Bloque</th><th>Qué significa</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto"><strong>Operaciones sin saldo en SIESA</strong></td><td class="det-texto">El módulo las deteriora y SIESA no reporta saldo. No hay contra qué comparar</td></tr>
                                <tr><td class="det-texto"><strong>Saldos de SIESA sin operación en el corte</strong></td><td class="det-texto">SIESA reporta cartera que este corte no deteriora</td></tr>
                                <tr><td class="det-texto"><strong>Diferencias de saldo</strong></td><td class="det-texto">La operación existe en las dos fuentes, con importes distintos</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <p>Cada partida se explica desde la pantalla, con estado y autor. <strong>El corte no se puede cerrar con partidas sin explicar.</strong></p>
                    </div>
                    <div class="det-aviso info">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div><strong>Punto abierto.</strong> No hay definida ninguna cuantía por debajo de la cual una diferencia no deba explicarse. Las diferencias medidas son <strong>326 sobre 1.935 operaciones que cruzan</strong>. Si el residuo incluye partidas de centavos, el módulo va a obligar a explicar ruido para poder cerrar, y el control se degrada a trámite. Es una decisión de política contable, no de diseño: ver <a href="#ayuda-7">Decisiones pendientes</a>.</div>
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
                        <h6 class="det-ayuda-sub">Dos controles se gestionan aquí</h6>
                        <ul>
                            <li><strong>C-1 · Prórrogas que reducen la antigüedad de la mora.</strong> La prórroga traslada las cuotas al final, con lo cual la operación puede bajar de rango y <strong>liberar deterioro</strong>. La pantalla lista cada mes las operaciones cuya antigüedad bajó, con el deterioro que eso liberó, para validación. Tiene efecto fiscal: la deducción del 33 % exige más de un año de vencimiento y la prórroga reinicia el conteo.</li>
                            <li><strong>C-2 · Bajas del período.</strong> Las operaciones que estaban en el corte anterior y ya no están. Hay que clasificarlas: <strong>castigo</strong>, <strong>recaudo total</strong>, <strong>cierre con apertura de una nueva operación</strong>, u otra causa. Sin la etiqueta, una reapertura se vería igual que un recaudo en la descomposición del movimiento del mes.</li>
                        </ul>
                        <p><strong>Los controles de cuadre.</strong> Son una treintena y cubren cuatro familias: integridad de la extracción, cuadre de la base, bloque fiscal y conciliación. Los más relevantes para Contabilidad:</p>
                    </div>
                    <div class="det-scroll">
                        <table class="det-tabla">
                            <thead>
                                <tr><th>Control</th><th>Qué verifica</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>C-CAPITAL</code> / <code>C-INTERES</code></td><td class="det-texto">El detalle de cuotas contra el consolidado por operación</td></tr>
                                <tr><td><code>C-BASE</code></td><td class="det-texto">Base de deterioro = capital vencido + interés vencido</td></tr>
                                <tr><td><code>C-CONCILIA</code></td><td class="det-texto">Que no queden partidas de conciliación sin explicar</td></tr>
                                <tr><td><code>C-FISCAL-TOPE</code></td><td class="det-texto">Que ninguna deducción supere el tope disponible</td></tr>
                                <tr><td><code>C-DIF-TEMP</code> / <code>C-DIFERIDO</code></td><td class="det-texto">Diferencia temporaria e impuesto diferido contra sus componentes</td></tr>
                                <tr><td><code>C-MOVIMIENTO</code></td><td class="det-texto">Que altas + variación + bajas den el deterioro del corte</td></tr>
                                <tr><td><code>C-LIBRO</code></td><td class="det-texto">El gasto del período del módulo contra el del libro</td></tr>
                                <tr><td><code>C-SALIDAS</code></td><td class="det-texto">Que no queden bajas sin clasificar</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <p><strong>Cierre con salvedad.</strong> Si un control bloqueante falla y aun así hay que cerrar, se puede hacer <strong>expresamente</strong>, con motivo escrito. El corte queda marcado como <em>cerrado con salvedades</em> de forma permanente, y se congela la enumeración de qué controles fallaban y con qué cifra. No es una salida cómoda: es una declaración.</p>
                    </div>
                    <div class="det-aviso">
                        <i class="fas fa-triangle-exclamation mt-1"></i>
                        <div>Hay un control que <strong>nunca bloquea a propósito</strong>: <code>C-DUPLICADAS</code>, el que cuenta las cuotas que el sistema de factoring entrega repetidas. Nadie puede corregir desde este módulo un defecto que vive en la base de factoring, y bloquear el cierre con él convertiría todos los meses en un cierre con salvedad, vaciando de sentido esa marca. Lo que sí bloquea es <code>C-DUPLICADAS-BASE</code>: <strong>cuánta base de deterioro se dejó fuera</strong>. Hoy vale cero, porque ninguna cuota repetida está vencida. El día que una lo esté, el corte no cierra sin explicación, y nadie tiene que acordarse de revisarlo.</div>
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
                                <tr><th>Salida</th><th>Formato</th><th>Para qué</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="det-texto">Resumen del corte</td><td>PDF</td><td class="det-texto">El corte firmado, para archivo</td></tr>
                                <tr><td class="det-texto">Detalle por operación</td><td>Excel</td><td class="det-texto">Revisión y soporte</td></tr>
                                <tr><td class="det-texto"><strong>Asiento contable</strong></td><td>Archivo plano</td><td class="det-texto">El ajuste del período, para cargar</td></tr>
                                <tr><td class="det-texto">Transición</td><td>Excel</td><td class="det-texto">Con la estructura del libro actual, para el período de convivencia</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="det-ayuda-texto mt-3">
                        <p>El exportable de transición existe para que, mientras dure la validación en paralelo, se pueda comparar el módulo contra el libro sin rehacer nada a mano.</p>
                    </div>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-5">
                <h5 class="det-ayuda-tit"><span class="num">Sección 5</span>Cómo amarran las cifras entre sí</h5>
                <p class="det-ayuda-lede">Este es el mapa que conviene tener a mano en la revisión mensual. Todas estas igualdades las verifica el módulo y bloquean el cierre si fallan.</p>
                <div class="det-panel">
                    <div class="det-ayuda-identidad">
                        <p class="det-corte-bloque">La cartera y la base</p>
                        <div class="grupo">
                            <span class="izq">Capital corriente + Capital vencido</span><span class="ig">=</span><span class="der">Capital total</span>
                            <span class="izq">Interés corriente + Interés vencido</span><span class="ig">=</span><span class="der">Interés total</span>
                            <span class="izq">Capital vencido + Interés vencido</span><span class="ig">=</span><span class="der">Base de deterioro</span>
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
                    <p><strong>La única identidad que la pantalla de resumen no muestra explícitamente</strong> es la primera: las tarjetas de Capital e Interés son totales, la de Base es solo lo vencido. De ahí la confusión habitual.</p>
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
                            <li><strong>Sobre SIESA el módulo solo lee.</strong> La única escritura fuera de su propia base es la marca de suspensión en el sistema de factoring, operación por operación y con confirmación.</li>
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

                <div class="det-ayuda-decision">
                    <div class="ref">
                        <span class="det-badge det-inactivo">&mdash;</span>
                        <b>¿El saldo de una operación en SIESA debe sumar sus <code>FAT</code>, <code>FEX</code> y <code>CC</code>, o seguir siendo solo el <code>OPE</code>?</b>
                    </div>
                    <p>Hoy es solo el <code>OPE</code>, que es capital. Si se suman, el saldo deja de ser capital y pasa a incluir interés facturado, lo que <strong>duplicaría el interés congelado</strong> y rompería la comparación de la conciliación.</p>
                </div>
            </section>

            <section class="det-ayuda-seccion" id="ayuda-8">
                <h5 class="det-ayuda-tit"><span class="num">Sección 8</span>Preguntas frecuentes</h5>
                <div class="det-panel">
                    <div class="det-ayuda-faq">
                        <p class="q">¿Por qué el número de cuotas del resumen no coincide con las cuotas que suman las operaciones?</p>
                        <p class="a">La tarjeta <em>Cuotas</em> es el conteo crudo de lo que entregó el origen. El consolidado excluye las cuotas que factoring entregó repetidas. La diferencia es exactamente eso, y el control <code>C-DUPLICADAS</code> la mide.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿Puedo recalcular un corte si me equivoqué en un parámetro?</p>
                        <p class="a">Sí, mientras esté ABIERTO o CALCULADO. El recálculo es completo y queda en bitácora. Un corte CERRADO no se recalcula: hay que reabrirlo, y eso tiene permiso propio.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿Por qué una operación aparece con 0 % de deterioro?</p>
                        <p class="a">Porque está en rango A (0 a 30 días), donde el porcentaje definido es 0 %, o porque está corriente y no tiene rango.</p>
                    </div>
                    <div class="det-ayuda-faq">
                        <p class="q">¿El deterioro de una operación suspendida deja de crecer?</p>
                        <p class="a">El interés sí. El capital no: sigue deteriorándose por su rango hasta llegar al 100 % a los 720 días.</p>
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
                                <tr><td class="det-texto">Saldos de cartera por operación y por cliente</td><td class="det-texto">SIESA, solo lectura</td></tr>
                                <tr><td class="det-texto">Interés congelado de las operaciones suspendidas</td><td class="det-texto">Facturas de interés (<code>FAT</code>) de SIESA, hasta el mes del evento</td></tr>
                                <tr><td class="det-texto">Rangos de mora y porcentajes contables</td><td class="det-texto">Paramétrica del módulo, con vigencias</td></tr>
                                <tr><td class="det-texto">Tarifa de renta</td><td class="det-texto">Paramétrica del módulo, congelada por corte (art. 240 ET)</td></tr>
                                <tr><td class="det-texto">Provisión fiscal acumulada de años anteriores</td><td class="det-texto">Cargue desde el archivo de Contabilidad</td></tr>
                                <tr><td class="det-texto">Estado inicial de las suspensiones</td><td class="det-texto">Cargue desde el archivo de Contabilidad</td></tr>
                                <tr><td class="det-texto">Causal, fecha del evento, soporte y observación de cada marca</td><td class="det-texto">Digitación en el módulo, con bitácora</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="det-subtitulo mt-3 mb-0">Todo lo demás lo calcula el módulo.</p>
                </div>
            </section>

            <p class="det-ayuda-pie">Guía de lectura para Contabilidad · 18 de septiembre de 2026. El documento técnico del módulo va aparte.</p>

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
