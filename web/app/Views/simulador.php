<?php echo $this->extend('template/plantilla'); ?>

<?php echo $this->section('css'); ?>
    <!-- Css -->
    <link href="<?= base_url('public/assets/css/simulador.css') ?>" rel="stylesheet">
<?php echo $this->endSection(); ?>

<?php echo $this->section('contenido'); ?>
    <input type="hidden" id="baseUrl" value="<?= base_url() ?>">
        <div class="mt-3 c-banner">
            <img src="<?= base_url('public/assets/img/SIMULADOR DE CREDITO.jpg') ?>" alt="Banner Desktop" class="img-fluid d-none d-md-block">
            <img src="<?= base_url('public/assets/img/SIMULADOR DE CREDITO.jpg') ?>" alt="Banner Mobile" class="img-fluid d-md-none">
            <h1 class="titulo-simulador">Simulador de crédito de libranza</h1>
        </div>

        <section class="cp-simulador">
            <div class="c-textos-simulador text-center mt-4">
                <p class="parrafo-calcula px-3 px-sm-0">Calcula el valor de la cuota o el monto que puede solicitar de 
                    acuerdo con las necesidades de crédito y capacidad de pago.
                </p>
            </div>

            <div class="c-azul">
                <div class="textos-azul text-center text-white">
                    <div class="contenedor-t-caracte">
                    <h1 class="t-caracte">Características</h1>
                    </div>
                    <div class="row justify-content-center contenedor-cards">
                        <div class="col-lg-3 col-sm-12 col-12 card-caracte">
                            <img src="<?= base_url('public/assets/img/ICONOS-SIMULADOR-23.png') ?>" alt="Bootstrap" class="img-icono">
                            <div class="card-parrafos">
                                <p class="text-1">Edad entre</p>
                                <p class="text-2">18 y 84 Años</p>
                            </div>
                        </div>

                        <div class="col-lg-3 col-sm-12 col-12 card-caracte">
                            <img src="<?= base_url('public/assets/img/ICONOS-SIMULADOR-24.png') ?>" alt="Bootstrap" class="img-icono">
                            <div class="card-parrafos">
                                <p class="text-1">Seguro de vida deudor incluida en la cuota</p>
                            </div>
                        </div>

                        <div class="col-lg-3 col-sm-12 col-12 card-caracte">
                            <img src="<?= base_url('public/assets/img/ICONOS-SIMULADOR-25.png') ?>" alt="Bootstrap" class="img-icono">
                            <div class="card-parrafos">
                                <p class="text-1">Plazo entre</p>
                                <p class="text-2">12 y 120 Meses</p>
                            </div>
                        </div>
                    </div>

                    <div class="c-aprobacion mt-lg-4">
                        <p>Aprobación sujeta a estudio de crédito y políticas de Arar Financiera</p>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div class="btn-continuar" id="btnContinuar" onclick="toggleForm()">
                            Continuar
                        </div>
                    </div>
                </div>
            </div>

            <div class="c-form-simulador" id="formularioSimulador">
                <div class="row">
                    <div class="col-2 d-none d-md-block"></div>  <!-- Se oculta en móvil, aparece en ≥768px -->
                    <div class="col-12 col-md-8">  <!-- col-12 en móvil, col-8 en ≥768px -->
                        <form class="row hide" id="simuladorForm">
                            <div class="col-md-12 c-input-form">
                                <label for="rangoEdad" class="small p-formulario">Rango de edad:</label>
                                <select class="form-control input-form" id="rangoEdad" name="rangoEdad" required>
                                    <option value="" disabled selected>Seleccione una opción</option>
                                    <option value="74">Menor a 75</option>
                                    <option value="76">Mayor a 75</option>
                                </select>
                            </div>

                            <div class="col-md-12 c-input-form">
                                <label for="valorCredito" class="small p-formulario">Valor del Crédito:</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-dollar-sign text-primary"></i>
                                    </span>
                                    <input type="text" class="form-control input-form" id="valorCredito" name="valorCredito" placeholder="0.0" required pattern="^\d{1,3}(,\d{3})*(\.\d{1,2})?$" title="Solo números con hasta 2 decimales" oninput="formatCurrency(this)">
                                </div>
                            </div>
                            <div class="col-md-12 c-input-form">
                                <label for="numeroCuotas" class="small p-formulario">Número de cuotas:</label>
                                <select class="form-control input-form" id="numeroCuotas" name="numeroCuotas" required>
                                    <option value="" disabled selected>Elija</option>
                                    <!-- Generando opciones del 1 al 24 -->
                                    <?php for ($i = 6; $i <= 120; $i += 6): ?>
                                        <option value="<?= $i; ?>"><?= $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="col-12 mt-4 mb-4">
                                <button type="submit" class="btn-calcular">Calcular</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-2 d-none d-md-block"></div>  <!-- Se oculta en móvil, aparece en ≥768px -->
                </div>
            </div>
        </section>

        <!-- ---------------------------------Fin de la seccion del simulador-------------------------------------------------------- -->
        <section>
                <div class="container mt-4 text-center">
                    <div id="resultado" class="mt-4">
                    </div>
                </div>
        </section>

        <div class="cp-linea-maps">
            <div class="row">
                <div class="col-5 linea-azul "></div>
                <div class="col-7 linea-gris "></div>
            </div>
        </div>
    </div>

    <script src="<?= base_url('public/assets/js/simulador.js') ?>"></script>
<?php echo $this->endSection(); ?>