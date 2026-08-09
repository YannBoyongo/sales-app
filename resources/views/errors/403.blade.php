@php
    $forbiddenMessage = trim($exception->getMessage() ?? '');
    if ($forbiddenMessage === '' || in_array($forbiddenMessage, ['Forbidden', 'HTTP 403 Forbidden'], true)) {
        $forbiddenMessage = 'Votre compte ne dispose pas des autorisations nécessaires pour consulter ou modifier cette ressource.';
    }
@endphp

@extends('errors.layout')

@section('code', '403')
@section('title', 'Accès refusé')
@section('headline', 'Vous n’avez pas accès à cette page.')

@section('message')
    {{ $forbiddenMessage }}
@endsection

@section('hint')
    Si vous pensez qu’il s’agit d’une erreur, contactez un administrateur pour vérifier vos rôles.
@endsection

@section('icon')
    <svg class="h-8 w-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
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
        <a href="{{ route('login') }}" class="app-btn-primary w-full sm:w-auto">
            Se connecter
        </a>
    @endauth
@endsection
