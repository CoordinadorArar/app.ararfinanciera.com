<nav id="shellMenu" class="offcanvas-lg offcanvas-start shell-menu" tabindex="-1" aria-label="Menú principal" aria-labelledby="shellMenuTitulo">
    <div class="offcanvas-header">
        <a href="{{ url('/home') }}"><img src="{{ asset('images/LogoArar.png') }}" alt="Arar Financiera" height="32"></a>
        <h2 id="shellMenuTitulo" class="visually-hidden">Menú principal</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#shellMenu" aria-label="Cerrar menú"></button>
    </div>
    <div class="offcanvas-body">
        <a class="shell-menu-marca d-none d-lg-flex" href="{{ url('/home') }}"><img src="{{ asset('images/LogoArar.png') }}" alt="Arar Financiera"></a>
        <div id="div-menu-content" aria-busy="true">
            @for ($i = 0; $i < 6; $i++)
                <span class="shell-menu-esqueleto"></span>
            @endfor
            <span class="visually-hidden" role="status">Cargando menú…</span>
        </div>
    </div>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>
</nav>
