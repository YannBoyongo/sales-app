@extends('errors.layout')

@section('code', '419')
@section('title', 'Session expirée')
@section('headline', 'Votre session a expiré.')
@section('message', 'Pour des raisons de sécurité, la page est restée inactive trop longtemps. Rechargez la page ou reconnectez-vous pour continuer.')

@section('icon')
    <svg class="h-8 w-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection

@section('actions')
    <button type="button" onclick="window.location.reload()" class="app-btn-secondary w-full sm:w-auto">
        Recharger la page
    </button>
    <a href="{{ route('login') }}" class="app-btn-primary w-full sm:w-auto">
        Se reconnecter
    </a>
@endsection
