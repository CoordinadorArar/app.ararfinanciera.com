<div id="documentos-soporte" class="procesos-div">
    <button class="btn btn-sm btn-danger" id="backButton" onclick="backToTable('documentos-soporte')" title="Volver a vista de procesos">
        <i class="fas fa-arrow-left"></i> Volver
    </button>
    <h4 class="text-center">Subida de documentos de soporte</h4><hr>
    <div class="row">
        <meta name="csrf-token-procesos" content="{{ csrf_token() }}" />
        <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="text-center">Información del proceso</h5>
                    <div id="infoProceso">

                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
            <div class="card">
                <div class="card-body">
                    <div id="listaDocumentos">
                        <h5 class="text-center">Documentos</h5>
                        <form action="{{ route('subir-documentos-soporte') }}" id="form-documentos" enctype="multipart/form-data" method="post">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>