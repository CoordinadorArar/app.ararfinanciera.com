<header class="shell-barra">
    <div class="shell-barra-fila">
        <button class="btn ui-btn ui-btn-sec shell-btn-menu d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#shellMenu" aria-controls="shellMenu" aria-label="Abrir menú">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <a class="shell-marca-movil d-lg-none" href="{{ url('/home') }}"><img src="{{ asset('images/LogoArar.png') }}" alt="Arar Financiera"></a>
        <div class="dropdown ms-auto">
            <button type="button" class="shell-usuario" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="shell-avatar" aria-hidden="true">{{ $shellUsuario['iniciales'] }}</span>
                <span class="shell-usuario-datos">
                    <span class="shell-usuario-nombre">{{ $shellUsuario['nombre'] }}</span>
                    <span class="shell-usuario-rol">{{ $shellUsuario['rol'] }}</span>
                </span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shell-usuario-menu">
                <li class="dropdown-item-text">
                    <strong class="d-block">{{ $shellUsuario['nombre'] }}</strong>
                    <span class="d-block text-break">{{ $shellUsuario['correo'] }}</span>
                    <span class="shell-usuario-rol">{{ $shellUsuario['rol'] }}</span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('perfil-usuario') }}"><i class="fas fa-user fa-fw" aria-hidden="true"></i>Mi perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button type="button" class="dropdown-item" onclick="logOut()"><i class="fas fa-right-from-bracket fa-fw" aria-hidden="true"></i>Cerrar sesión</button></li>
            </ul>
        </div>
    </div>
    @if(config('database.ambiente') === 'demo')
        <div class="amb-franja">
            <span><i class="fas fa-flask"></i> <strong>Ambiente demo</strong></span>
            <span class="amb-texto">· Estás viendo y modificando datos de prueba.</span>
            <button type="button" class="btn" onclick="conmutarAmbiente('produccion')">Volver a Producción</button>
        </div>
    @endif
</header>
