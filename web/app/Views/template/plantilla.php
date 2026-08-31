<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <!-- Boostrap 5.3  Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Boostrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- RENDERIZAR CUSTOM CSS, SI EXISTEN -->
    <link href="<?= base_url('public/assets/css/main.css') ?>" rel="stylesheet">
    <?php echo $this->renderSection("css"); ?>

    <!-- Boostrap 5.3 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Sweetalert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.0/css/all.min.css" integrity="sha512-9xKTRVabjVeZmc+GUW8GgSmcREDunMM+Dt/GrzchfN8tkwHizc5RP4Ok/MXFFy5rIjJjzhndFScTceq5e6GvVQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <script src="<?= base_url('public/assets/js/main.js') ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.0/js/all.min.js" integrity="sha512-8py0AXTY8pfAroJmBkYfJ+VuKUKMMsUOC1MldW9kkC/k4SZi6AexSDS60QYn41U2rp8KL9IpVHy8FxW2TDmjDA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>

<body>
    <div>
        <!-- inicio del encabezado -->
        <?php 
            $this->include('template/header');
            echo $this->renderSection('header'); 
        ?>
        <!-- fin del encabezado -->

        <!-- RENDERIZAR CONTENIDO PRINCIPAL -->
        <?php echo $this->renderSection("contenido"); ?>

        <!-- inicio del pie de pagina -->
        <?php 
            $this->include('template/footer');
            echo $this->renderSection('footer');
        ?>
        <!-- fin del pie de pagina -->

        <a href="https://api.whatsapp.com/send?phone=573229067508" class="whatsapp-float" target="_blank">
            <img src="https://img.icons8.com/color/48/000000/whatsapp.png" alt="WhatsApp">
        </a>
    </div>
</body>
</html>