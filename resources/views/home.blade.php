@extends('layouts.app')

@section('title', __('home.title'))

@push('styles')
<style>
    body {
        background: #f8f9fa;
    }

    .navbar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .welcome-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
</style>
@endpush

@section('content')
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            <i class="bi bi-shield-check me-2"></i>{{ __('home.app_name') }}
        </a>
        <div class="ms-auto">
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-light">
                    <i class="bi bi-box-arrow-right me-2"></i>{{ __('home.logout') }}
                </button>
            </form>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card welcome-card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-house-door display-1 text-primary"></i>
                        <h2 class="fw-bold mt-3">{{ __('home.welcome') }}</h2>
                        <p class="text-muted">{{ __('home.subtitle') }}</p>
                    </div>

                    @if(auth()->check())
                        <div class="alert alert-success">
                            <i class="bi bi-person-check-fill me-2"></i>
                            {{ __('home.logged_in_as') }}: <strong>{{ auth()->user()->getFullName() }} - {{auth()->user()->email}}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
