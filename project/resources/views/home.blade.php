@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/home.css') }}">
@section('content')
    <div id="content-page">
        <h4 class="display-5 text-center">Bienvenid@ {{ session()->get('usuarioNombre') }}</h4>
    </div>
@endsection
