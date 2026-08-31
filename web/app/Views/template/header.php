<?php echo $this->section('header'); 
$current = uri_string(); // Detectamos la ruta actual
?>
    <section class="c-inicial">
            <header class="py-3">
                <div class="c-ararfinanciera d-flex align-items-center  ">
                    <div class="social-icons  ms-lg-3 me-lg-5">
                        <a href="https://www.instagram.com/ararfinanciera" target="_blank" class="text-decoration-none">
                            <div class="row">
                                <div class=" col-xl-2 col-lg-2 col-md-2 col-sm-3 col-3 text-center ">
                                    <div class="iconos-footer-insta">
                                        <i class="fab fa-instagram text-white icono-insta"></i>
                                    </div>
                                </div>
                                <div class="col-xl-10 col-lg-10 col-md-10 col-sm-9 col-9">
                                    <span  class="text-nav-footer">ararfinanciera</span >
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="">
                        <a href="https://www.facebook.com/ArarFinanciera" target="_blank" class="text-decoration-none">
                            <div class="row">
                                <div class="col-xl-2 col-lg-2 col-md-2 col-sm-3 col-3 text-center">
                                    <div class="iconos-footer-insta">
                                        <i class="fab fa-facebook text-white icono-face"></i>
                                    </div>
                                </div>
                                <div class="col-xl-10 col-lg-10 col-md-10 col-sm-9 col-9">
                                    <span class="text-nav-footer">Arar Financiera</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <nav class="navbar navbar-expand-lg nav-contenedor d-flex justify-content-between">
                    <div class="d-flex flex-row align-items-center c-logo-nav">
                        <a class="navbar-brand" href="<?= base_url('/') ?>">
                            <img src="<?= base_url('public/assets/img/logo-azul.png') ?>" alt="Bootstrap" class="logo-principal">
                        </a>
                    </div>

                    <button class="navbar-toggler" id="toggleMenu" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="nav c-nav-principal">
                            <li class="nav-item">
                                <a class="nav-link active text-nav fw-bold <?= $current == '' ? 'titulos-footer' : '' ?>" href="<?= base_url('/') ?>">Inicio</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-nav fw-bold <?= $current == 'quieneSomos' ? 'titulos-footer' : '' ?>" href="<?= base_url('quieneSomos') ?>">Quiénes Somos</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-nav fw-bold <?= $current == 'productos' ? 'titulos-footer' : '' ?>" href="<?= base_url('productos') ?>">Productos</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-nav fw-bold <?= $current == 'simulador' ? 'titulos-footer' : '' ?>" href="<?= base_url('simulador') ?>">Simulador</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-nav fw-bold <?= $current == 'contacto' ? 'titulos-footer' : '' ?>" href="<?= base_url('contacto') ?>">Contacto</a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </header>
        </section>
<?php echo $this->endSection(); ?>