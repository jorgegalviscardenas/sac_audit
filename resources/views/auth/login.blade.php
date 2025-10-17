@extends('layouts.app')

@section('title', __('auth.login.title'))

@push('styles')
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }

    .btn-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-gradient:hover {
        background: linear-gradient(135deg, #5568d3 0%, #6a3f91 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-gradient:active {
        transform: translateY(0);
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card login-card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold">{{ __('auth.login.heading') }}</h2>
                        <p class="text-muted">{{ __('auth.login.subtitle') }}</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>{{ __('auth.login.error') }}</strong>
                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope me-1"></i>{{ __('auth.login.email') }}
                            </label>
                            <input
                                type="email"
                                class="form-control form-control-lg @error('email') is-invalid @enderror"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                placeholder="{{ __('auth.login.email_placeholder') }}"
                            >
                            @error('email')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock me-1"></i>{{ __('auth.login.password') }}
                            </label>
                            <input
                                type="password"
                                class="form-control form-control-lg @error('password') is-invalid @enderror"
                                id="password"
                                name="password"
                                required
                                placeholder="{{ __('auth.login.password_placeholder') }}"
                            >
                            @error('password')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                {{ __('auth.login.remember_me') }}
                            </label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg btn-gradient">
                                <i class="bi bi-box-arrow-in-right me-2"></i>{{ __('auth.login.sign_in') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
