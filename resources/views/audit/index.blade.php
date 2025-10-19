@extends('layouts.app')

@section('title', __('audit.title'))

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="card welcome-card">
                <div class="card-body p-4">
                    <h2 class="fw-bold mb-4">
                        <i class="bi bi-journal-text me-2"></i>{{ __('audit.title') }}
                    </h2>

                    @include('audit.partials.tenant-selector')

                    @include('audit.partials.filters')
                </div>
            </div>

            <div class="mt-3">
                @include('audit.partials.table')
            </div>
        </div>
    </div>
</div>
@endsection
