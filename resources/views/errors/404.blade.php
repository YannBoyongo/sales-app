@extends('errors.layout')

@section('code', '404')
@section('title', 'Page introuvable')
@section('headline', 'Cette page n’existe pas.')
@section('message', 'L’adresse que vous avez saisie est incorrecte, ou la ressource a été déplacée ou supprimée.')

@section('icon')
    <svg class="h-8 w-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
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
