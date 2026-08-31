<?php echo $this->extend('template/plantilla'); ?>

<?php echo $this->section('css'); ?>
    <!-- Css -->
    <link href="<?= base_url('public/assets/css/home.css') ?>" rel="stylesheet">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
    <div id="carouselExampleAutoplaying" class="carousel slidem carrusel-banner" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active">
                
                <div class="mt-3 c-banner">
                    <img src="<?= base_url('public/assets/img/Arar-Financiera-Credito-Libranza.jpg') ?>" alt="Banner Desktop" class="img-fluid d-none d-md-block">
                    <img src="<?= base_url('public/assets/img/Arar-Financiera-Credito-Libranza-movil.jpg') ?>" alt="Banner Mobile" class="img-fluid d-md-none">
                </div>

                <div class="c-text-banner">
                    <div class="row">
                        <div class="ct-titulo col-12 py-lg-5 py-md-4 text-white">
                            CRÉDITO DE
                            <span class="line-break"></span>
                            LIBRANZA
                        </div>
                        <div class="ct-parrafo col-12 py-3 text-white">
                            Si eres pensionado pregunta
                            <span class="line-break"></span>
                            por nuestros convenios
                        </div>
                        <div class="ct-boton col-12 py-lg-4 py-md-3  text-center">
                            <a href="https://api.whatsapp.com/send?phone=573229067508" target="_blank">
                                <div class="btn-solicitalo ">
                                    ¡Solicítalo Ahora!
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
            <div class="carousel-item">
                <img src="<?= base_url('public/assets/img/BANNER-2.jpg') ?>" alt="Banner Desktop" class="img-fluid d-none d-md-block">
                <img src="<?= base_url('public/assets/img/BANNER-2-movil.jpg') ?>" alt="Banner Mobile" class="img-fluid d-md-none">

                <div class="img-boton">
                    <a href="https://api.whatsapp.com/send?phone=573229067508" target="_blank">
                        <img src="<?= base_url('public/assets/img/BOTON-BANNER-2.png') ?>" alt="Banner Desktop" class="img-fluid d-none d-md-block boton-banner2">
                        <img src="<?= base_url('public/assets/img/BOTON-BANNER-2.png') ?>" alt="Banner Mobile" class="img-fluid d-md-none boton-banner2">     
                    </a>
                </div>
            </div>
            <div class="carousel-item">
                <img src="<?= base_url('public/assets/img/BANNER-3.jpg') ?>" alt="Banner Desktop" class="img-fluid d-none d-md-block">
                <img src="<?= base_url('public/assets/img/BANNER-3-movil.jpg') ?>" alt="Banner Mobile" class="img-fluid d-md-none">
                <div class="img-boton2">
                    <a href="https://api.whatsapp.com/send?phone=573229067508" target="_blank">
                        <img src="<?= base_url('public/assets/img/BOTON-BANNER-3.png') ?>" alt="Banner Desktop" class="img-fluid d-none d-md-block boton-banner3">
                        <img src="<?= base_url('public/assets/img/BOTON-BANNER-3.png') ?>" alt="Banner Mobile" class="img-fluid d-md-none boton-banner3">     
                    </a>    
                </div>
            </div> 
            <div class="carousel-item">
                <img src="<?= base_url('public/assets/img/BANNER-4.jpg') ?>" alt="Banner Desktop" class="img-fluid d-none d-md-block">
                <img src="<?= base_url('public/assets/img/BANNER-4-movil.jpg') ?>" alt="Banner Mobile" class="img-fluid d-md-none">
                <div class="img-boton3">
                    <a href="https://api.whatsapp.com/send?phone=573229067508" target="_blank">
                            <img src="<?= base_url('public/assets/img/BOTON-BANNER-4.png') ?>" alt="Banner Desktop" class="img-fluid d-none d-md-block boton-banner4">
                        <img src="<?= base_url('public/assets/img/BOTON-BANNER-4.png') ?>" alt="Banner Mobile" class="img-fluid d-md-none boton-banner4">     
                    </a>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

    <section id="clientes" class="cp-clientes">
        <div class="c-clientes text-center">
            <h2 class="mb-5 titulo-clientes">Nuestros clientes</h2>
            <div class="row">
                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-12 c-cli-pensionados" data-bs-toggle="modal" data-bs-target="#modalPensionados">
                    <div class="p-3">
                        <div class=" border border-dark c-imagenes">
                            <div class="c-img">
                            </div>
                        </div>
                    </div>
                    <div class=" p-1 c-cliente-text text-white ">Pensionados</div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-12 c-cli-fuerzas" data-bs-toggle="modal" data-bs-target="#modalFuerzasArmadas">
                    <div class="p-3">
                        <div class="border border-dark c-imagenes">
                            <div class="c-img2">
                            </div>
                        </div>
                    </div>
                    <div class="p-1 c-cliente-text text-white ">Fuerzas Armadas</div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-12 c-cli-policias" data-bs-toggle="modal" data-bs-target="#modalPolicias">
                    <div class="p-3">
                        <div class="border border-dark c-imagenes">
                            <div class="c-img3">  
                            </div>
                        </div>
                    </div>
                    <div class="p-1 c-cliente-text text-white ">Policía</div>
                </div>
            </div>
        </div>
    </section>
    <!-- Fin de los clientes  -->
    <!-- Modales -->
    <div class="modal fade" id="modalPensionados" tabindex="-1" aria-labelledby="modalPensionadosLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="titulo-modal position-absolute">
                        <h2>Pensionados</h2>
                    </div>
                    <div class="modal-texto">
                        Disfrutar una vida plena y sin preocupaciones después de años de esfuerzo es posible con Arar Financiera. El crédito de libranza está diseñado especialmente para pensionados, brindándote el apoyo financiero que necesitas para cumplir tus sueños y necesidades.
                    </div>
                    <br>
                    <div class="contenedor-pensiones position-relative">
                        <!-- Fila 1 -->
                        <div class="fila-pension-con-linea">
                            <div class="row fila-pension">
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Casur</p>
                                </div>
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension ps-4">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Fopep</p>
                                </div>
                            </div>
                        </div>
                        <!-- Fila 2 -->
                        <div class="fila-pension-con-linea">
                            <div class="row fila-pension">
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Colpensiones</p>
                                </div>
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension ps-4">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Cremil</p>
                                </div>
                            </div>
                        </div>
                        <!-- Fila 3 -->
                        <div class="fila-pension-con-linea">
                            <div class="row fila-pension">
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Fiduprevisora</p>
                                </div>
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension ps-4">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Cagen-Tegen</p>
                                </div>
                            </div>
                        </div>

                        <!-- Línea vertical central -->
                        <div class="linea-central"></div>

                        <!-- Última fila (una sola columna) -->
                        <div class="row mt-2">
                            <div class="col-12 d-flex justify-content-center align-items-center celda-pension">
                                <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                <p class="m-0">Pensionado Ministerio de Defensa</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFuerzasArmadas" tabindex="-1" aria-labelledby="modalFuerzasArmadasLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body ">
                    <div class="titulo-modal position-absolute">
                        <h2>Fuerzas Armadas</h2>
                    </div>
                    <div class="modal-texto">
                        Ofrecemos créditos de libranza diseñados especialmente para los héroes de la Fuerza Pública, todos aquellos que velan por la seguridad de nuestro país.
                    </div>
                    <br>
                    <div class="contenedor-pensiones position-relative">
                        <!-- Fila 1 -->
                        <div class="fila-pension-con-linea">
                            <div class="row fila-pension">
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Ejército</p>
                                </div>
                            </div>
                        </div>

                        <!-- Fila 2 -->
                        <div class="fila-pension-con-linea">
                            <div class="row fila-pension">
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Fuerza Aérea</p>
                                </div>
                            </div>
                        </div>

                        <!-- Fila 3 -->
                        <div class="fila-pension-con-linea">
                            <div class="row fila-pension">
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Armada Nacional</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPolicias" tabindex="-1" aria-labelledby="modalPoliciasLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"> 
            <div class="modal-content">
                <div class="modal-body ">
                    <div class="titulo-modal position-absolute">
                        <h2>Policía</h2>
                    </div>
                    <div class="modal-texto">
                        Hemos creado créditos de libranza especialmente diseñados para policías, ofreciéndote el respaldo financiero que necesitas con la confianza y el respeto que te mereces.
                    </div>
                    <br>
                    <div class="contenedor-pensiones position-relative">
                        <!-- Fila 1 -->
                        <div class="fila-pension-con-linea">
                            <div class="row fila-pension">
                                <div class="col-6 d-flex align-items-center justify-content-start celda-pension">
                                    <img src="<?= base_url('public/assets/img/PALOMITA.png') ?>" class="icono-palomita">
                                    <p class="m-0">Policía Nacional de Colombia</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Fin de los Modales -->
    <section class="cp-pasos ">
        <div class="py-4 text-center c-titulo-pasos">
            <label> Conoce cómo solicitar tu crédito de Libranza </label>
        </div>
        <div>
            <img src="<?= base_url('public/assets/img/Arar-Financiera-Pasos-para-solicitar-un-credito.jpg') ?>" alt="Pasos Desktop" class="img-fluid d-none d-md-block">
            <img src="<?= base_url('public/assets/img/Arar-Financiera-Pasos-para-solicitar-un-credito-movil.jpg') ?>" alt="Pasos Mobile" class="img-fluid d-md-none">
        </div>
        <div class="c-pasos1 row text-center">
            <div class="col-12">
                <div class="col-12 c-label-icon">
                    <label class="label-pasos px-3">Paso 1</label>
                    <img src="<?= base_url('public/assets/img/Arar-Financiera-iconos-01.png') ?>" alt="Bootstrap" class="img-icono">
                </div>
                <div class="col-12 mt-2">
                    <p class="c-text-pasos">
                        Escríbenos a nuestra línea
                        <span class="line-break"></span>
                        de WhatsApp : <a href="https://api.whatsapp.com/send?phone=573229067508" target="_blank">322 9067508</a>
                        <span class="line-break"></span>
                        y envía tu último
                        <span class="line-break"></span>
                        desprendible de pago.
                    </p>
                </div>
            </div>
        </div>
        <div class="c-pasos2 row text-center">
            <div class="col-12 c-label-icon">
                <label class="label-pasos2 px-3">Paso 2</label>
                <img src="<?= base_url('public/assets/img/Arar-Financiera-iconos-02.png') ?>" alt="Bootstrap" class="img-icono">
            </div>
            <div class="col-12 mt-2">
                <p class="c-text-pasos2">
                    Nuestro equipo de expertos
                    <span class="line-break"></span>
                    revisará tu información y te
                    <span class="line-break"></span>
                    informará sobre la aprobación
                    <span class="line-break"></span>
                    del crédito de libranza.
                </p>
            </div>
        </div>
        <div class="c-pasos3 row text-center">
            <div class="col-12 c-label-icon">
                <label class="label-pasos px-3">Paso 3</label>
                <img src="<?= base_url('public/assets/img/Arar-Financiera-iconos-03.png') ?>" alt="Bootstrap" class="img-icono">
            </div>
            <div class="col-12 mt-2">

                <p class="c-text-pasos ">
                    Disfruta de los beneficios
                    <span class="line-break"></span>
                    financieros con
                    <span class="line-break"></span>
                    Arar Financiera.
                </p>

            </div>
        </div>
    </section>
    <!-- ---------------------------------Fin de la seccion de pasos --------------------------------------------------------- -->

    <section class="cp-simulador">
        <div class="c-imagen-simulador" >
            <img src="<?= base_url('public/assets/img/SIMULADOR DE CREDITO-INICIO.jpg') ?>" alt="Pasos Desktop" class="img-fluid d-none d-md-block">
            <img src="<?= base_url('public/assets/img/RESPONSIVE-SIMULADOR-1.jpg') ?>" alt="Pasos Mobile" class="img-fluid d-md-none">
        </div>
        <div class="c-simu-titulo">Simulador de crédito <span class="line-break "></span>
                                de libranza
        </div>
        <div class="c-simu-parrafo"> Simula las cuotas de tu crédito de libranza y <span class="line-break2"></span>
                                    calcula el plan de pagos de tu préstamo en línea.
        </div>
        <a href="<?= base_url('simulador') ?>" class="text-decoration-none">
            <div class="c-simu-boton">
                Clic aquí 
            </div>
        </a>
    </section>

    <!-- ---------------------------------Fin de la seccion de simulador --------------------------------------------------------- -->
    <section class="cp-formulario">
        <section class="c-formulario-gris">
            <div class="col-12 c-gris d-none d-md-block"></div>
        </section>
        <section class="cp-convenio">
            <div class="col-12 c-convenio-img">
                <img src="<?= base_url('public/assets/img/FOTO DEL FORMULARIO.jpg') ?>" alt="Bootstrap" class="img-convenio">
            </div>
            <div class="cp-convenios text-white fw-bold">
                <h2 class="convenio-titulo">Convenios</h2>
                <div class="convenio-parrafos mt-2">
                    <p>-&emsp;Armada Nacional</p>
                    <p>-&emsp;FOPEP</p>
                    <p>-&emsp;Fiduprevisora</p>
                    <p>-&emsp;Casur</p>
                    <p>-&emsp;Cagen</p>
                    <p>-&emsp;Ejército Nacional</p>
                </div>
            </div>
            <div class="mt-5 text-white convenio-parrafos c-parrafos1 fw-bold">
                <p>-&emsp;Pensionado Mindefensa</p>
                <p>-&emsp;Fuerza Aérea</p>
                <p>-&emsp;Cremil</p>
                <p>-&emsp;Policía Nacional</p>
                <p>-&emsp;Colpensiones</p>
            </div>
        </section>
        <section class="cp-solicita">
            <div class="row ">
                <div class="col-xl-7 col-lg-7 col-md-7 col-sm-12 col-12">
                    <div class="c-textos-solicita">
                        <h1 class="titulo-solicita">Solícita tu crédito <span class="line-break"></span>
                            de libranza aquí</h1>
                        <p class="parrafo-solicita fw-bold  mt-xl-5 mt-lg-5 mt-sm-4 mt-4"> Comparte tu información para <span class="line-break"></span>
                            comunicarnos contigo y brindarte <span class="line-break"></span>
                            asesoría personalizada
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <section class="c-formulario text-white">
            <div class="cs-formulario">
                <div class="ps-4">
                    <div class="f-titulos text-center mt-4">
                        <h4>Ingresa tú </h4>
                        <h4>información personal</h4>
                    </div>

                    <form class="row" id="form-solicitar" onsubmit="return envioForm(event)">
                        <div class="col-md-12">
                            <label for="nombre" class=" small p-formulario">Nombre Completo:</label>
                            <input type="text" class="form-control form-control-sm" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-12">
                            <label for="cedula" class=" small p-formulario">Cédula:</label>
                            <input type="text" class="form-control form-control-sm" id="cedula" name="cedula" required>
                        </div>
                        <div class="col-md-12">
                            <label for="correo" class="small p-formulario">Correo Electrónico:</label>
                            <input type="email" class="form-control form-control-sm" id="correo" name="correo" required>
                        </div>
                        <div class="col-md-12">
                            <label for="celular" class="small p-formulario">Celular:</label>
                            <input type="tel" class="form-control form-control-sm" id="celular" name="celular" required>
                        </div>
                        <div class="col-md-12">
                            <label for="pagaduria" class="small p-formulario">Selecciona la pagaduría:</label>
                            <select id="pagaduria" name="pagaduria" class="form-select form-select-sm p-formulario" required>
                                <option value="" disabled selected>Seleccione una opción</option>
                                <option value="pensionado_mindefensa ">Pensionado Mindefensa</option>
                                <option value="fuerza_aerea">Fuerza Aérea</option>
                                <option value="cremil">Cremil</option>
                                <option value="policia">Policía Nacional</option>
                                <option value="colpensiones">Colpensiones</option>
                                <option value="armada">Armada Nacional</option>
                                <option value="fopep">FOPEP</option>
                                <option value="fiduprevisora">Fiduprevisora</option>
                                <option value="casur">Casur</option>
                                <option value="cagen">Cagen</option>
                                <option value="ejercito">Ejército Nacional</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check mt-1 mb-1">
                                <input class="form-check-input" type="checkbox" required>
                                <label class="form-check-label text-xs p-formulario ">Acepto la Política de tratamiento de datos personales</label>
                            </div>
                        </div>
                        <div class="col-12 text-center mb-4">
                            <button type="submit" class="p-formulario">Solicitar</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </section>
    <!-- ---------------------------------Fin de la seccion del formulario --------------------------------------------------------- -->
    <section class="cp-maps">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3959.558105425227!2d-73.1157018!3d7.0610932!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8e683f687433d5f9%3A0xc8fd75ea4d305af3!2sARAR%20FINANCIERA!5e0!3m2!1ses-419!2sco!4v1740403056587!5m2!1ses-419!2sco" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </section>
    <!-- ---------------------------------Fin de la seccion del maps --------------------------------------------------------- -->
    <div class="cp-linea-maps">
        <div class="row">
            <div class="col-5 linea-azul "></div>
            <div class="col-7 linea-gris "></div>
        </div>
    </div>
    <script src="<?= base_url('public/assets/js/home.js') ?>"></script>
<?php echo $this->endSection(); ?>