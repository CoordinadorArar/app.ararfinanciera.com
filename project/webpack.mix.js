const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css')
    .sourceMaps();

// mix.combine([
//     'public/js/dataTables.bootstrap5.min.js',
//     'public/js/jquery.dataTables.min.js',
//     'public/js/jquery.preloader.min.js',
//     'public/js/sweetalert2@11.js',
//     'public/js/main.js',
//     'public/js/useful-functions.js',
//     'public/js/form-datos.js',
//     'public/js/calculate.js',
//     'public/js/list-advisers.js',
//     'public/js/list-users.js',
//     'public/js/payrolls.js',
//     'public/js/perfil-usuario.js',
// ], 'public/js/app.js', 'public/js');
