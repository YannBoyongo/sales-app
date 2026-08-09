@extends('errors.layout')

@section('code', '429')
@section('title', 'Trop de requêtes')
@section('headline', 'Vous avez effectué trop de requêtes.')
@section('message', 'Patientez quelques instants avant de réessayer. Cette limite protège la stabilité de l’application.')

@section('icon')
    <svg class="h-8 w-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
    </svg>
@endsection

@section('actions')
    <button type="button" onclick="history.back()" class="app-btn-secondary w-full sm:w-auto">
        Retour
    </button>
    @auth
        <a href="{{ route('dashboard') }}" class="app-btn-primary w-full sm:w-auto">
            Tableau de bord
        </a>
    @else
        <a href="{{ url('/') }}" class="app-btn-primary w-full sm:w-auto">
            Accueil
        </a>
    @endauth
@endsection
