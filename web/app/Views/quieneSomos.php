<?php echo $this->extend('template/plantilla'); ?>

<?php echo $this->section('css'); ?>
    <!-- Css -->
    <link href="<?= base_url('public/assets/css/quieneSomos.css') ?>" rel="stylesheet">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
    <input type="hidden" id="baseUrl" value="<?= base_url() ?>">
    <section>
        <div class="banner1">
            <img src="<?= base_url('public/assets/img/QUIENES SOMOS.jpg') ?>" alt="Banner Desktop" class="img-fluid d-none d-lg-block">
            <img src="<?= base_url('public/assets/img/QUIENES SOMOS.jpg') ?>" alt="Banner Mobile" class="w-100 d-lg-none img-banner-1">

            <div class="">
                <div class="tituloQS text-center">
                    Quiénes somos
                </div>
                <div class="parrafoQS">
                    <p>
                        Somos una <label class="parrrafoN">financiera 100% especializada en
                        créditos de libranza</label>, comprometidos en ofrecer
                        soluciones financieras sencillas, seguras y
                        accesibles para nuestros clientes. Brindamos un
                        respaldo cofiable y personalizado.
                        <br class="">
                        <br class="d-none d-md-block">
                        Crecemos contigo, brindándote confiaza y
                        respaldo en cada paso.
                    </p>
                </div>

            </div>

        </div>
    </section>
    <!-- ---------------------------------Fin de la seccion del quienes somos--------------------------------------------------------- -->
    <section class="seccion2">
        <div class="row ">
            <div class="col-xl-5 col-lg-5 col-12 text-center">
                <div class="btn-mv ">
                    Misión
                </div>
            </div>   
            <div class="col-1 ">

            </div>
            <div class="col-12 d-lg-none">
                <p class="parrafo-mv">
                    Nos comprometemos a ofrecer soluciones
                    finacieras especializadas y alternativas
                    que brinden liquidez a nuestros clientes en
                    los momentos requeridos. Lo hacemos con
                    transparencia, agilidad y una asesoria de
                    calidad, para que cada decisión finaciera sea
                    segura y confiable.
                </p>
            </div>

            <div class="linea-p d-none d-lg-block">
                <div class="line-container">
                    <div class="point left"></div>
                    <div class="line3"></div>
                    <div class="point right"></div>
                </div>
            </div>

            <div class="col-1"> 
              
            </div>

            <div class="col-xl-5 col-lg-5 col-12 text-center ">
                <div class="btn-mv btn-mv2">
                Visión
                </div>
            </div>

            <div class="col-12 d-lg-none">
                <p class="parrafo-mv">
                    Queremos ser reconocidos como una
                    financiera con gran capacidad de apoyo,
                    honestidad, asesoría y transparencia,
                    brindadando créditos oportunos que
                    permita satisfacer las necesidades de
                    nuestros clientes.
                </p>
            </div>
        </div>

        <div class="d-none d-lg-block">
            <div class="row">
                <div class="col-xl-5 col-lg-5 col-12">
                    <p class="parrafo-mv">
                        Nos comprometemos a ofrecer soluciones
                        finacieras especializadas y alternativas
                        que brinden liquidez a nuestros clientes en
                        los momentos requeridos. Lo hacemos con
                        transparencia, agilidad y una asesoria de
                        calidad, para que cada decisión finaciera sea
                        segura y confiable.
                    </p>
                </div>
                <div class="col-xl-2 col-lg-2 d-none d-lg-flex justify-content-center align-items-center">
                    <div class="line2"></div>
                </div>
                <div class="col-xl-5 col-lg-5 col-12">
                    <p class="parrafo-mv">
                        Queremos ser reconocidos como una
                        financiera con gran capacidad de apoyo,
                        honestidad, asesoría y transparencia,
                        brindadando créditos oportunos que
                        permita satisfacer las necesidades de
                        nuestros clientes.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <!-- ---------------------------------Fin de la seccion de mision vision --------------------------------------------------------- -->
    <section>
        <div class="banner2">
            <img src="<?= base_url('public/assets/img/PAGINA WEB-39.png') ?>" alt="Banner Desktop" class="img-fluid d-none d-lg-block">
            <img src="<?= base_url('public/assets/img/PAGINA WEB-39.png') ?>" alt="Banner Mobile" class=" d-lg-none img-banner-2">

            <div class="c-principal-obj">
                <div class="tituloObj text-center p-3">
                    Objetivos de la compañía
                </div>
                <div class="parrafoObj">
                    <div class="icono-obj">
                        <img src="<?= base_url('public/assets/img/PAGINA WEB-38.png') ?>" alt="Banner Desktop" class="icon-objetivos">
                    </div>
                    <div class="texto-obj">
                        <p>
                            Fortalecer nuestros procesos para ofrecer un
                            servicio financiero basado en la honestidad,
                            claridad y ética profesional.
                        </p>
                    </div>
                </div>
                <div class="parrafoObj">
                    <div class="icono-obj">
                        <img src="<?= base_url('public/assets/img/PAGINA WEB-38.png') ?>" alt="Banner Desktop" class="icon-objetivos">
                    </div>
                    <div class="texto-obj">
                        <p>
                            Disponemos de herramientas tecnológicas y 
                            personal capacitado para el manejo adecuado
                            de las operaciones.
                        </p>
                    </div>
                </div>
                <div class="parrafoObj">
                    <div class="icono-obj">
                        <img src="<?= base_url('public/assets/img/PAGINA WEB-38.png') ?>" alt="Banner Desktop" class="icon-objetivos">
                    </div>
                    <div class="texto-obj">
                        <p>
                            Ampliar nuestras coberturas y fortalecer alianzas
                            estratégicas para llegar a mas clientes y 
                            sectores con nuestras soluciones financieras.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ---------------------------------Fin de la seccion de objetivos--------------------------------------------------------- -->

    <div class="cp-linea-maps">
        <div class="row">
            <div class="col-5 linea-azul "></div>
            <div class="col-7 linea-gris "></div>
        </div>
    </div>
<?php echo $this->endSection(); ?>