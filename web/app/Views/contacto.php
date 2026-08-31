<?php echo $this->extend('template/plantilla'); ?>

<?php echo $this->section('css'); ?>
<!-- Css -->
<link href="<?= base_url('public/assets/css/contacto.css') ?>" rel="stylesheet">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
    <input type="hidden" id="baseUrl" value="<?= base_url() ?>">

    <section>
        <div class="c-principal">
            <div class="c-titulo text-center pb-4">
                <h1 class="titulo-contacto">¡Queremos Escucharte!</h1>
            </div>

            <div class="principal-parrafo">
                <div class="c-parrafo ">
                    <p class="parrafo-contacto"> Tienes una pregunta, queja reclamo o sugerencia de nuestros productos y/o servicios, ayúdanos a mejorar diligenciando
                        el siguiente formulario.
                    </p>
                </div>
            </div>

            <div class="principal-formulario">
                <div class="c-formulario text-white">
                    <form class="row" id="form-contacto" onsubmit="return envioForm(event)">
                        <div class="col-md-6 c-input-form mt-4">
                            <label for="rangoEdad" class="small p-formulario">Asunto:</label>
                            <select class="form-control form-control-sm" id="asunto" name="asunto" required>
                                <option value="" disabled selected>Seleccione una opción</option>
                                <option value="PQR">PQR</option>
                                <option value="Sugerencia">Sugerencias</option>
                                <option value="Informacion comercial">Información Comercial</option>
                                <option value="Crédito y cartera">Crédito y Cartera</option>
                                <option value="Trabaja con nosotros">Quieres trabajar con nosotros</option>
                            </select>
                        </div>
                        <div class="col-md-6 mt-4">
                            <label for="correo" class="small p-formulario">Correo Electrónico:</label>
                            <input type="email" class="form-control form-control-sm" id="correo" name="correo" required>
                        </div>
                        <div class="col-md-6">
                            <label for="celular" class="small p-formulario">Teléfono(s)</label>
                            <input type="tel" class="form-control form-control-sm" id="telefono" name="telefono" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mensaje" class="small p-formulario">Mensaje</label>
                            <textarea class="form-control form-control-sm input-mensaje" id="mensaje" name="mensaje" rows="4" required></textarea>
                        </div>
                        <div class="col-md-6 input-nombre">
                            <label for="cedula" class=" small p-formulario ">Nombre y Apellidos</label>
                            <input type="text" class="form-control form-control-sm " id="nombreCompleto" name="nombreCompleto" required>
                        </div>

                        <div class="d-flex flex-column align-items-end">
                            <div style="max-width: 50%; width: 100%;">
                                <div class="form-check mt-1 mb-3">
                                    <input class="form-check-input" type="checkbox" required>
                                    <label class="form-check-label text-xs p-formulario">
                                        Autorizo a ARAR FINANCIERA S.A.S. el manejo de mis datos
                                        personales de acuerdo a las políticas de tratamiento de
                                        información de la compañía.
                                    </label>
                                </div>
                                <div class="d-flex justify-content-center mb-4">
                                    <button type="submit" class="btn-formulario">Enviar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class=" c-deseasSaldo">
            <div class="row text-center c-libranzaSaldo text-white">
                <div class="col-xl-5 col-lg-5 col-12 c-libraza c-libraza2">
                    <p class="text-1">Crédito de libranza</p>
                    <p class="text-2">Escríbenos a la línea de WhatsApp</p>
                    <div class="text-azul">
                        <div class="d-flex align-items-center">
                            <img src="<?= base_url('public/assets/img/ICONO-BOTON-MANITA.png') ?>" alt="Icono mano" class="me-2 icono-mano">
                            <div class="text-azul2">
                                <a class="sin-link" href="https://api.whatsapp.com/send?phone=573229067508" target="_blank">
                                    <p class="text-3 m-0">3229067508</p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-2 d-none d-lg-flex justify-content-center align-items-center">
                    <div class="line2"></div>
                </div>

                <div class="col-xl-5 col-lg-5 col-12 c-libraza  ">
                    <p class="text-1">Certificado de saldo</p>
                    <p class="text-2">Escríbenos a la línea de WhatsApp</p>
                    <div class="text-azul">
                        <div class="d-flex align-items-center">
                            <img src="<?= base_url('public/assets/img/ICONO-BOTON-MANITA.png') ?>" alt="Icono mano" class="me-2 icono-mano">
                            <div class="text-azul2">
                                <p class="text-3 m-0">
                                    <a class="sin-link" href="https://api.whatsapp.com/send?phone=573144237431" target="_blank">3144237431 </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="principal-deseas">
                <div class="c-deseas text-center ">
                    <div>
                        <p class="p-deseas1">Sí deseas</p>
                        <p class="p-deseas2 fw-bold">SOLICITAR:</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cp-maps ">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3959.558105425227!2d-73.1157018!3d7.0610932!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8e683f687433d5f9%3A0xc8fd75ea4d305af3!2sARAR%20FINANCIERA!5e0!3m2!1ses-419!2sco!4v1740403056587!5m2!1ses-419!2sco" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </section>
    <!-- ---------------------------------Fin de la seccion del maps --------------------------------------------------------- -->

    <div class="cp-linea-maps">
        <div class="row">
            <div class="col-5 linea-azul "></div>
            <div class="col-7 linea-gris "></div>
        </div>
    </div>

    <script src="<?= base_url('public/assets/js/contacto.js') ?>"></script>

<?php echo $this->endSection(); ?>