@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/gestion-documental-folios.css') }}">
@section('content')
    <div class="container" style="min-height: 75vh;" id="divDigitalizarFacturas">
        <h5 class="text-center"><strong>Digitalizar Facturas <i class="fas fa-file-upload"></i></strong></h5>
        <meta name="csrf-token-digitalizar" content="{{ csrf_token() }}" />

        <div class="container-fluid my-3">
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr class="bg-primary">
                            <th>Tipo</th>
                            <th>Operación</th>
                            <th>Cuota</th>
                            <th>Cliente</th>
                            <th>Fecha operación</th>
                            <th>Asesor</th>
                            <th>Nota</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyFacturasPendientes">
                        <tr><td colspan="8" class="text-center">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal digitalizar factura -->
    <div class="modal fade" id="modalDigitalizar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formDigitalizar" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Digitalizar factura <span id="spanFacturaModal"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="factura" id="inputFactura">
                        <input type="hidden" name="idCliente" id="inputIdCliente">

                        <div class="mb-3">
                            <label class="form-label">Archivo PDF</label>
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnEscanearPdf">
                                    <i class="fas fa-print"></i> Escanear PDF
                                </button>
                                <span id="spanArchivoEscaneado" class="d-none">
                                    <i class="fas fa-file-pdf text-danger"></i> escaneo.pdf
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-2" id="btnVerEscaneado"><i class="fas fa-eye"></i> Ver</button>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" id="btnEliminarEscaneado"><i class="fas fa-trash"></i> Eliminar</button>
                                </span>
                            </div>
                            <input type="file" class="form-control" name="archivo" id="inputArchivo" accept="application/pdf" required>
                            <span id="spanErrorEscaneo" class="text-danger small d-none"></span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Caja (en proceso)</label>
                            <select class="form-select" name="idCaja" id="selectCaja">
                                <option value="">-- Selecciona una caja --</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">O crea una caja nueva</label>
                            <input type="text" class="form-control" name="nombreCajaNueva" id="inputNombreCajaNueva" placeholder="Nombre de la caja nueva" maxlength="12">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/gestion-documental-digitalizar.js') }}"></script>
@endsection
