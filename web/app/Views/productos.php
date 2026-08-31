<?php echo $this->extend('template/plantilla'); ?>

<?php echo $this->section('css'); ?>
    <!-- Css -->
    <link href="<?= base_url('public/assets/css/productos.css') ?>" rel="stylesheet">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
    <input type="hidden" id="baseUrl" value="<?= base_url() ?>">
    <section>
        <div class="padre-seccion1">
            <div class="banner1">
                <img src="<?= base_url('public/assets/img/BANNER-PRODUCTO-LIBRANZA.jpg') ?>" alt="Banner Desktop" class="img-fluid d-none d-lg-block">
                <img src="<?= base_url('public/assets/img/BANNER-PRODUCTO-MOVIL.jpg') ?>" alt="Banner Mobile" class="w-100 d-lg-none img-banner-1">
            </div>
            <div class="tituloLibranza text-center text-white">
                ¡CRÉDITO DE LIBRANZA PARA PENSIONADOS!
            </div>
            <div class="parrafoLibranza text-white">
                <p>
                    Estamos para ayudarte a cumplir tus
                    sueños. Viaja, renueva tu hogar o haz
                    realidad ese proyecto que tienes en
                    mente, <label class="parrrafoN">
                        solicita tu crédito de libranza.
                    </label>
                </p>
            </div>
            <div class="btn-solicitalo">
                <a class="sin-link" href="https://api.whatsapp.com/send?phone=573229067508" target="_blank">Solicítalo aquí</a>
            </div>
        </div>
    </section>
    <!-- ---------------------------------Fin de la seccion de solicitalo--------------------------------------------------------- -->
    <section class="seccion2">
        <div class="requisitos">
            <h1 class="titulo-requisitos fw-bold">Requisitos</h1>
            <p class="parrafo-requisitos">
                Si eres pensionado conoce los requisitos para solicitar tu crédito:
            </p>
        </div>
        <div class="row text-center">
            <div class=" col-xl-4 col-lg-4 col-md-4 col-sm-12 col-12">
                <div class="card-testimonios">
                    <div class="contenedor-circle">
                        <div class="circle">
                            <img src="<?= base_url('public/assets/img/CEDULA-46.png') ?>" alt="Banner Desktop" class="img-fluid">
                        </div>
                    </div>
                    <div class="card-content">
                        <p>
                            Fotocopia de cédula de
                            ciudadanía ampliada al 150%
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-12">
                <div class="card-testimonios2 ">
                    <div class="contenedor-circle">
                        <div class="circle">
                            <img src="<?= base_url('public/assets/img/DESPRENDIBLE.png') ?>" alt="Banner Desktop" class="img-fluid">
                        </div>
                    </div>
                    <div class="card-content2">
                        <p>
                            Desprendible de pago de los últimos 2 meses
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-12">
                <div class="card-testimonios">
                    <div class="contenedor-circle">
                        <div class="circle">
                            <img src="<?= base_url('public/assets/img/REFERENCIA.png') ?>" alt="Banner Desktop" class="img-fluid">
                        </div>
                    </div>
                    <div class="card-content">
                        <p>
                            2 referencias personal y familiar
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ---------------------------------Fin de la seccion requisitos--------------------------------------------------------- -->
    <section>
        <div class="text-center">
            <h1 class="titulo-convenio fw-bold">Convenios</h1>
            <div class="clientes">
                <div class="slider">
                    <div class="slide-track">
                        <!-- Duplicar para animación continua -->
                        <div class="slide">FOPEP</div>
                        <div class="slide">Fiduprevisora</div>
                        <div class="slide">Casur</div>
                        <div class="slide">Cagen</div>
                        <div class="slide2">Ejército Nacional</div>
                        <div class="slide2">Pensionado Mindefensa</div>
                        <div class="slide">Fuerza Aérea</div>
                        <div class="slide">Cremil</div>
                        <div class="slide">Policía Nacional</div>
                        <div class="slide">Colpensiones</div>

                        <!-- Repetición para efecto loop -->
                        <div class="slide">FOPEP</div>
                        <div class="slide">Fiduprevisora</div>
                        <div class="slide">Casur</div>
                        <div class="slide">Cagen</div>
                        <div class="slide2">Ejército Nacional</div>
                        <div class="slide2">Pensionado Mindefensa</div>
                        <div class="slide">Fuerza Aérea</div>
                        <div class="slide">Cremil</div>
                        <div class="slide2">Policía Nacional</div>
                        <div class="slide">Colpensiones</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ---------------------------------Fin de la seccion de convenios--------------------------------------------------------- -->

    <section class="mt-5">
        <div class="row">
            <div class="col-1 d-none d-lg-block">
                <div class="linea-p">
                    <div class="line-container">
                        <div class="point"></div>
                        <div class="line3"></div>
                        <div class="point"></div>
                        <div class="line3"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5 col-lg-5 col-12">
                <div class="row text-center">
                    <div class="col-12 columnaPreguntas">
                        <div class="faq-item">
                            <div class="faq-question text-center">
                                <h4>¿Qué es un crédito de libranza?</h4>
                                <!-- <span class="toggle-arrow">▼</span> -->
                            </div>
                            <p class="faq-answer">
                                Un crédito de libranza es un tipo de
                                préstamo en el que las cuotas se
                                descuentan automáticamente del salario o
                                pensión del solicitante, antes de que reciba
                                el dinero en su cuenta. <br><br>
                                Es una forma más segura y organizada de
                                pagar, ya que evita retrasos o cobros
                                adicionales por olvido.
                            </p>
                        </div>
                    </div>
                    <div class="col-12 ">
                        <div class="faq-item">
                            <div class="faq-question text-center">
                                <h4>¿Cómo solicitar un crédito de libranza?</h4>
                                <!-- <span class="toggle-arrow">▼</span> -->
                            </div>
                            <p class="faq-answer">
                                <span class="parrrafoN">Escríbe a nuestra línea de WhatsApp
                                    322 9067508</span> y verifica que la empresa
                                donde trabajas o entidad pagadora de tu
                                pensión tiene convenio de libranza.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-1 d-none d-lg-block">
                <div class="linea-p">
                    <div class="line-container">
                        <div class="point"></div>
                        <div class="line3"></div>
                        <div class="point"></div>
                        <div class="line3"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5 col-lg-5 col-12">
                <div class="row text-center">
                    <div class="col-12">
                        <div class="faq-item">
                            <div class="faq-question text-center">
                                <h4>¿Qué documentos se necesitan para solicitar el crédito de libranza?</h4>
                                <!-- <span class="toggle-arrow">▼</span> -->
                            </div>

                            <div class="faq-answer"> <!-- 👈 Agregamos este contenedor que antes no estaba -->
                                <div class="parrafoObj">
                                    <div class="icono-obj">
                                        <img src="<?= base_url('public/assets/img/PAGINA WEB-38.png') ?>" alt="icono" class="icon-objetivos">
                                    </div>
                                    <div class="texto-obj">
                                        <p>Fotocopia de tu cédula al 150%.</p>
                                    </div>
                                </div>

                                <div class="parrafoObj">
                                    <div class="icono-obj">
                                        <img src="<?= base_url('public/assets/img/PAGINA WEB-38.png') ?>" alt="icono" class="icon-objetivos">
                                    </div>
                                    <div class="texto-obj">
                                        <p>Últimos 2 desprendibles de nómina.</p>
                                    </div>
                                </div>

                                <div class="parrafoObj">
                                    <div class="icono-obj">
                                        <img src="<?= base_url('public/assets/img/PAGINA WEB-38.png') ?>" alt="icono" class="icon-objetivos">
                                    </div>
                                    <div class="texto-obj">
                                        <p>Documentos adicionales que puedan ser requeridos por la entidad empleadora o pagadora de tu pensión.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 ">
                        <div class="faq-item">
                            <div class="faq-question text-center">
                                <h4>¿Cuáles son los canales
                                    de comunicación?</h4>
                                <!-- <span class="toggle-arrow">▼</span> -->
                            </div>
                            <p class="faq-answer">
                                Los canales oficiales de arar Financiera son: <br>
                                <span class="text-azul">Correo:</span> <span class="parrrafoN">auxiliar@ararfinanciera.com</span> <br>
                                <span class="text-azul">Área Comercial:</span> <span class="parrrafoN">
                                    <a class="sin-link" href="https://api.whatsapp.com/send?phone=573229067508" target="_blank">3229067508 </a>
                                </span><br>
                                <span class="text-azul">Área de Cartera:</span> <span class="parrrafoN"> 3144237431 </span> <br>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>
    <!-- ---------------------------------Fin de la seccion de preguntas --------------------------------------------------------- -->

    <div class="cp-linea-maps">
        <div class="row">
            <div class="col-5 linea-azul "></div>
            <div class="col-7 linea-gris "></div>
        </div>
    </div>
    <script src="<?= base_url('public/assets/js/productos.js') ?>"></script>
<?php echo $this->endSection(); ?>