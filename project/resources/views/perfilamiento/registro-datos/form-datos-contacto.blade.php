<div id="form-datos-contacto" class="forms-datos-tercero">
    <div class="container">
        <button class="btn btn-secondary btn-sm float-left"><i class="fa fa-arrow-left"></i> Atras</button>
        <h4 class="text-center">Datos de contacto</h4>
    </div>
    <div class="card w-75 ms-auto me-auto mb-5">
        <div class="card-body">
            <form id="form-contacto">
                <p class="lead">Datos conyuge</p>
                <div class="row mb-2">
                    <div class="col">
                        <div class="form-group">
                            <label for="nombreConyuge">Nombre</label>
                            <input type="email" id="nombreConyuge" class="form-control" placeholder="Ingresa nombre de cónyuge" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="documentoConyuge">No Documento</label>
                            <input type="text" id="documentoConyuge" class="form-control" placeholder="Ingresa documento de cónyuge" onkeypress="return soloNumeros(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="telefonoConyuge">Teléfono</label>
                            <input type="text" id="telefonoConyuge" class="form-control" placeholder="Ingresa teléfono de cónyuge" onkeypress="return soloNumeros(event)">
                        </div>
                    </div>
                </div><hr>
                <p class="lead">Referencias familiares (*)</p>
                <div class="row mb-2">
                    <div class="col">
                        <div class="form-group">
                            <label for="nombreReferencia1">Nombre</label>
                            <input type="email" id="nombreReferencia1" class="form-control" placeholder="Ingresa nombre" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="direccionReferencia1">Dirección</label>
                            <input type="text" id="direccionReferencia1" class="form-control" placeholder="Ingresa dirección" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="telefonoReferencia1">Teléfono</label>
                            <input type="text" id="telefonoReferencia1" class="form-control" placeholder="Ingresa teléfono" onkeypress="return soloNumeros(event)">
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col">
                        <div class="form-group">
                            <label for="nombreReferencia2">Nombre</label>
                            <input type="email" id="nombreReferencia2" class="form-control" placeholder="Ingresa nombre" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="direccionReferencia2">Dirección</label>
                            <input type="text" id="direccionReferencia2" class="form-control" placeholder="Ingresa dirección" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="telefonoReferencia2">Teléfono</label>
                            <input type="text" id="telefonoReferencia2" class="form-control" placeholder="Ingresa teléfono" onkeypress="return soloNumeros(event)">
                        </div>
                    </div>
                </div><hr>
                <p class="lead">Referencias personales (*)</p>
                <div class="row mb-2">
                    <div class="col">
                        <div class="form-group">
                            <label for="nombreReferencia1">Nombre</label>
                            <input type="email" id="nombreReferencia3" class="form-control" placeholder="Ingresa nombre" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="direccionReferencia3">Dirección</label>
                            <input type="text" id="direccionReferencia3" class="form-control" placeholder="Ingresa dirección" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="telefonoReferencia3">Teléfono</label>
                            <input type="text" id="telefonoReferencia3" class="form-control" placeholder="Ingresa teléfono" onkeypress="return soloNumeros(event)">
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col">
                        <div class="form-group">
                            <label for="nombreReferencia4">Nombre</label>
                            <input type="email" id="nombreReferencia4" class="form-control" placeholder="Ingresa nombre" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="direccionReferencia4">Dirección</label>
                            <input type="text" id="direccionReferencia4" class="form-control" placeholder="Ingresa dirección" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="telefonoReferencia4">Teléfono</label>
                            <input type="text" id="telefonoReferencia4" class="form-control" placeholder="Ingresa teléfono" onkeypress="return soloNumeros(event)">
                        </div>
                    </div>
                </div><hr>
            </form>
            <div class="d-grid">
                <button onclick="sendContactData()" class="btn btn-primary">Enviar</button>
            </div>
        </div>
    </div>
</div>