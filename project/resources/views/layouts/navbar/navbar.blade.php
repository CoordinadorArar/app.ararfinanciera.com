<div class="sticky-top">
    <meta name="csrf-token-ambiente" content="{{ csrf_token() }}" />
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <button class="btn btn-primary" type="button" id="sidebar-show-button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasScrolling" aria-controls="offcanvasScrolling">
                <i class="fa fa-bars"></i> Menú
            </button>
            <button class="btn btn-dark d-inline-block d-lg-none ml-end" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="nav navbar-nav ms-auto">
                    <li class="nav-item active">
                        <a class="nav-link" href="#">
                            <i class="fa fa-bell position-relative">
                            </i>
                            <span class="position-absolute translate-middle badge rounded-pill bg-danger" id="icon-notifications">
                                99+
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    @if(config('database.ambiente') === 'demo')
        <div class="amb-franja">
            <span><i class="fas fa-flask"></i> <strong>Ambiente demo</strong></span>
            <span class="amb-texto">· Estás viendo y modificando datos de prueba.</span>
            <button type="button" class="btn" onclick="conmutarAmbiente('produccion')">Volver a Producción</button>
        </div>
    @endif
</div>