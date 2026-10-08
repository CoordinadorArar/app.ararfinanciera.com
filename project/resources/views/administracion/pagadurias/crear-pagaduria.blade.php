@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pagadurias.css') }}">
@endpush
@section('content')
    <div class="ui-contenedor">
        <meta name="csrf-token-pagadurias" content="{{ csrf_token() }}" />
        <header class="ui-encabezado">
            <div>
                <h1 class="ui-titulo">Pagadurías</h1>
                <p class="ui-descripcion">Fórmulas de cupo, parámetros y reglas por edad</p>
            </div>
            <div class="ui-acciones">
                <button type="button" class="btn btn-primary ui-btn" onclick="abrirModalPagaduria(false)">
                    <i class="fas fa-plus" aria-hidden="true"></i><span>Nueva pagaduría</span>
                </button>
            </div>
        </header>
        <div class="row g-3">
            <div class="col-12 col-lg-3">
                <div class="d-lg-none mb-1">
                    <label for="selectPagaduria" class="form-label">Pagaduría</label>
                    <select id="selectPagaduria" class="form-select" onchange="seleccionarPagaduria(this.value)"></select>
                </div>
                <section class="ui-card d-none d-lg-block" aria-labelledby="titulo-lista-pagadurias">
                    <div class="ui-card-cab">
                        <h2 id="titulo-lista-pagadurias">Pagadurías</h2>
                        <span class="ui-badge ui-badge-info" id="totalPagadurias"></span>
                    </div>
                    <div class="mb-2 d-none" id="buscadorPagadurias">
                        <label for="buscarPagaduria" class="visually-hidden">Buscar pagaduría</label>
                        <input type="search" id="buscarPagaduria" class="form-control form-control-sm" placeholder="Buscar…" autocomplete="off" oninput="pintarListaPagadurias()">
                    </div>
                    <div class="list-group list-group-flush ui-lista-pagadurias" id="listaPagadurias"></div>
                </section>
            </div>
            <div class="col-12 col-lg-9">
                <section class="ui-card" id="panelPagaduria"></section>
            </div>
        </div>
    </div>
    <div class="modal fade ui-modal" id="modalPagaduria" tabindex="-1" aria-labelledby="tituloModalPagaduria" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <form class="modal-content" novalidate onsubmit="guardarNombrePagaduria(event)">
                <div class="modal-header">
                    <h2 class="modal-title fs-6" id="tituloModalPagaduria">Nueva pagaduría</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="nombrePagaduria" class="form-label">Nombre de la pagaduría</label>
                    <input type="text" id="nombrePagaduria" class="form-control" maxlength="50" autocomplete="off" aria-describedby="error-nombrePagaduria" oninput="this.classList.remove('is-invalid')" onkeypress="return noStrangeCharacters(event)">
                    <span class="invalid-feedback" role="alert" id="error-nombrePagaduria"></span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary ui-btn" data-bs-dismiss="modal">
                        <i class="fas fa-xmark" aria-hidden="true"></i><span>Cancelar</span>
                    </button>
                    <button type="submit" class="btn btn-primary ui-btn" id="btnGuardarPagaduria">
                        <i class="fas fa-floppy-disk" aria-hidden="true"></i><span>Guardar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade ui-modal" id="modalProbarFormula" tabindex="-1" aria-labelledby="tituloProbarFormula" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-6" id="tituloProbarFormula">Probar fórmula</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="ui-descripcion mb-3" id="probarSubtitulo"></p>
                    <div class="row g-3" id="probarValores"></div>
                    <div class="ui-probar-resultado mt-4" aria-live="polite">
                        <span class="ui-cifra-etiqueta">Fórmula con los valores</span>
                        <p class="ui-operacion" id="probarOperacion">—</p>
                        <span class="ui-cifra-etiqueta">Cupo resultante</span>
                        <span class="ui-cifra" id="probarResultado">—</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn ui-btn ui-btn-sec" data-bs-dismiss="modal">
                        <i class="fas fa-xmark" aria-hidden="true"></i><span>Cerrar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/pagadurias.js') }}"></script>
@endpush
