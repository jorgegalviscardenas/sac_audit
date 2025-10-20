@if($currentTenant && count($currentTenant->entities) > 0)
<form action="{{ route('audit') }}" method="GET">
    <div class="mb-4">
        <label class="form-label fw-bold">
            <i class="bi bi-filter me-2"></i>{{ __('audit.filters') }}
        </label>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="entitySelect" class="form-label">{{ __('audit.filter_entity') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="entitySelect" name="entity_id" required>
                    <option value="">{{ __('audit.select_entity_placeholder') }}</option>
                    @foreach($currentTenant->entities as $entityConfig)
                        <option value="{{ $entityConfig->entity->id }}"
                            @if(request('entity_id') === $entityConfig->entity->id) selected @endif>
                            {{ $entityConfig->entity->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label for="auditType" class="form-label">{{ __('audit.filter_type') }}</label>
                <select class="form-select" id="auditType" name="audit_type">
                    <option value="">{{ __('audit.all_types') }}</option>
                    @foreach($auditTypes as $type)
                        <option value="{{ $type->id }}" @if(request('audit_type') == $type->id) selected @endif>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="fromDate" class="form-label">{{ __('audit.filter_from') }}</label>
                <input type="date" class="form-control" id="fromDate" name="from" value="{{ request('from') }}">
            </div>

            <div class="col-md-4">
                <label for="toDate" class="form-label">{{ __('audit.filter_to') }}</label>
                <input type="date" class="form-control" id="toDate" name="to" value="{{ request('to') }}">
            </div>

            <div class="col-md-4">
                <label for="objectId" class="form-label">{{ __('audit.filter_object_id') }}</label>
                <input type="text" class="form-control" id="objectId" name="object_id"
                       placeholder="00000000-0000-0000-0000-000000000000" value="{{ request('object_id') }}">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-2"></i>{{ __('audit.filter_button') }}
                </button>
                <a href="{{ route('audit') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-2"></i>{{ __('audit.clear_filters') }}
                </a>
            </div>
        </div>
    </div>
</form>
@endif
