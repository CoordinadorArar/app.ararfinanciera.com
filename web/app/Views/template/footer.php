<?php echo $this->section('footer'); 
$current = uri_string(); // Detectamos la ruta actual
?>
<footer class=" pt-xl-5 px-lg-5 contenedor-p-footer">
    <div class="row ">
        <div class="col-xl-5 col-lg-5 col-md-12 col-sm-12 col-xs-12">
            <div class="row">
                <div class="col-xl-8 col-lg-8 col-md-12 col-sm-12 col-xs-12 c-imagen-footer">
                    <img src="<?= base_url('public/assets/img/logo-azul.png') ?>" alt="Bootstrap" class="img-footer">
                </div>
                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 col-12 c-banner-footer "> <!-- Cambiado col-xs-6 a col-6 -->
                    <div class="row">
                        <a class="col-12 <?= $current == '' ? 'titulos-footer fw-bold' : '' ?>" href="<?= base_url('/') ?>">Inicio</a>
                        <a class="col-12 <?= $current == 'quieneSomos' ? 'titulos-footer fw-bold' : '' ?>" href="<?= base_url('quieneSomos') ?>">Quienes somos</a>
                        <a class="col-12 <?= $current == 'productos' ? 'titulos-footer fw-bold' : '' ?>" href="<?= base_url('productos') ?>">Productos</a>
                        <a class="col-12 <?= $current == 'simulador' ? 'titulos-footer fw-bold' : '' ?>" href="<?= base_url('simulador') ?>">Simulador</a>
                        <a class="col-12 <?= $current == 'contacto' ? 'titulos-footer fw-bold' : '' ?>" href="<?= base_url('contacto') ?>">Contacto</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-4 col-md-7 col-sm-12 col-xs-12 contenedor-atencion">
            <div class="row">
                <div class="col-xl-2 col-lg-2">
                    <div class=" line d-none d-lg-block"></div>
                </div>
                <div class="col-xl-10 col-lg-10 col-md-12 col-sm-12 col-xs-12 c-atencion-texto ">
                    <p class="titulos-footer fw-bold"> ATENCIÓN AL CLIENTE </p>
                    <p> FLORIDABLANCA | SANTANDER</p>
                    <p> Ecoparque Empresarial Anillo Vial Oficina 206 Torre 1</p>
                    <p class="titulos2-footer fw-bold">(+57) (607) 6391010 / (+57) (607) 6985203 </p>
                    <p> Ext. 6103 Eliana Marcela Ojeda  - área Cartera </p>
                    <p class="fw-bold">Área Comercial : 
                        <label class="titulos2-footer fw-bold">
                            <a href="https://api.whatsapp.com/send?phone=573229067508" target="_blank"> 322 9067508 </a>
                        </label>
                    </p>
                    <p class="fw-bold">Área de cartera : <label class="titulos2-footer fw-bold">314 4237431</label></p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-3 col-md-5 col-sm-12 col-xs-12 contenedor-atencion">
            <div class="row">
                <div class="col-xl-2 col-lg-2">
                    <div class=" line d-none d-lg-block"></div>
                </div>
                <div class="col-xl-9 col-lg-9 col-md-12 col-sm-12 col-xs-12  c-atencion-texto ms-1">
                    <p class="titulos-footer fw-bold"> INFORMACIÓN </p>
                    <p class="titulos2-footer"> auxiliar@ararfinanciera.com</p>
                    <p class="titulos-footer fw-bold"> SÍGUENOS</p>
                    <div class=" ">
                        <a href="https://www.instagram.com/ararfinanciera" target="_blank" class="text-decoration-none">
                            <div class="row">
                                <div class="col-xl-2 col-lg-2 col-md-2 col-sm-1 col-1">
                                    <div class="iconos-footer-insta ">
                                        <i class="fab fa-instagram icono-insta "></i>
                                    </div>
                                </div>
                                <div class="col-xl-10 col-lg-10 col-md-10 col-sm-11 col-10 label-icon">
                                    <label class="text-nav">@ararfinanciera</label>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="mt-2">
                        <a href="https://www.facebook.com/ArarFinanciera" target="_blank" class="text-decoration-none">
                            <div class="row">
                                <div class="col-xl-2 col-lg-2 col-md-2 col-sm-1 col-1">
                                    <div class="iconos-footer-insta">
                                        <i class="fab fa-facebook  icono-face "></i>
                                    </div>
                                </div>
                                <div class="col-xl-10 col-lg-10 col-md-10 col-sm-10 col-10 label-icon">
                                    <label class="text-nav">Arar Financiera</label>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</footer>
<div class="c-pie-pagina text-center mt-3">
    <p class="c-politica  p-2">
        <a href="<?= base_url('politica-tratamiento-datos') ?>" class="text-decoration-none link-politica">    
            Política de tratamiento de datos Copyright © 2021 ARAR Financiera 
        </a>
    </p>
</div>
<?php echo $this->endSection(); ?>