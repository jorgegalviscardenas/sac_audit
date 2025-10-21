<form action="{{ route('update.tenant') }}" method="POST" id="tenantForm">
    @csrf
    <div class="mb-4">
        <label for="tenantSelect" class="form-label fw-bold">
            <i class="bi bi-building me-2"></i>{{ __('audit.select_tenant') }}
        </label>
        <div class="row g-2">
            <div class="col">
                <select class="form-select form-select-lg select2-tenant" id="tenantSelect" name="tenant_id" data-placeholder="{{ __('audit.select_tenant_placeholder') }}">
                    <option value=""></option>
                    @if($currentTenant)
                        <option value="{{ $currentTenant->tenant->id }}" selected>
                            {{ $currentTenant->tenant->name }}
                        </option>
                    @endif
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-lg">
                    {{ __('audit.change_button') }}
                </button>
            </div>
        </div>
    </div>
</form>

@if(session('success'))
    <div class="alert alert-success mt-3">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger mt-3">
        <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    </div>
@endif
