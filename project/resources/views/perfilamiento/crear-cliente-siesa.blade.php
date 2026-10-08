@extends('layouts.app')
@section('content')
    <div class="ui-contenedor" id="clientesSiesa" data-admin="{{ collect($rol)->contains('IdRol',1) ? 1 : 0 }}">
        <header class="ui-encabezado">
            <div>
                <h1 class="ui-titulo" id="tituloClientes" tabindex="-1">Clientes en SIESA</h1>
                <p class="ui-descripcion">Creación de tercero, cliente y proveedor a partir de FactoringManager</p>
            </div>
            <div class="ui-acciones" id="badgeEnvio"></div>
        </header>
        <div id="alertaEnvio"></div>
        <div class="ui-contadores" role="group" aria-label="Filtrar por estado en SIESA" id="contadores"></div>
        <div class="ui-barra">
            <div class="ui-buscador" role="search">
                <label for="busquedaCliente" class="visually-hidden">Buscar por NIT o nombre</label>
                <i class="fas fa-magnifying-glass ui-buscador-icono" aria-hidden="true"></i>
                <input type="text" id="busquedaCliente" class="form-control" placeholder="Buscar por NIT o nombre" maxlength="100" autocomplete="off">
                <button type="button" class="ui-buscador-limpiar d-none" id="limpiarBusqueda" aria-label="Limpiar búsqueda" onclick="limpiarBusqueda()"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <p class="ui-resumen" id="resumenClientes" aria-live="polite"></p>
        </div>
        <section class="ui-card" aria-labelledby="tituloClientes">
            <div id="bandejaClientes"></div>
        </section>
        <div class="modal fade" id="modalSiesa" tabindex="-1" aria-labelledby="tituloSiesa" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
                <div class="modal-content ui-modal">
                    <div class="modal-header align-items-start">
                        <div>
                            <h2 class="modal-title" id="tituloSiesa" tabindex="-1"></h2>
                            <span class="ui-modal-sub" id="subSiesa"></span>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-2" id="estadoSiesa"></div>
                        </div>
                        <button type="button" class="btn-close" id="cerrarSiesa" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ui-pestanas" role="tablist" aria-label="Creación en SIESA" id="pestanasSiesa" onkeydown="teclaPestana(event)"></div>
                        <div class="ui-pestana-panel" role="tabpanel" id="panel-validacion" aria-labelledby="tab-validacion" tabindex="0"></div>
                        <div class="ui-pestana-panel" role="tabpanel" id="panel-vista" aria-labelledby="tab-vista" tabindex="0" hidden></div>
                        <div class="ui-pestana-panel" role="tabpanel" id="panel-ejecucion" aria-labelledby="tab-ejecucion" tabindex="0" hidden></div>
                    </div>
                    <div class="modal-footer flex-column-reverse flex-sm-row align-items-stretch align-items-sm-center">
                        <button type="button" class="btn ui-btn ui-btn-sec me-sm-auto" id="btnCerrarSiesa" data-bs-dismiss="modal">Cerrar</button>
                        <p class="ui-campo-ayuda m-0 order-last order-sm-0" id="ayudaEjecutar"></p>
                        <button type="button" class="btn ui-btn ui-btn-sec" id="btnValidar" onclick="validar()"><i class="fas fa-rotate-right" aria-hidden="true"></i><span>Validar de nuevo</span></button>
                        <button type="button" class="btn btn-primary ui-btn" id="btnEjecutar" onclick="ejecutar()"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/crear-cliente-siesa.js') }}"></script>
@endpush
