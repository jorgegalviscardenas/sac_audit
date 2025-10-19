@if($currentTenant && count($currentTenant->entities) > 0)
<form action="{{ route('audit') }}" method="GET">
    <div class="mb-4">
        <label for="entitySelect" class="form-label fw-bold">
            <i class="bi bi-filter me-2"></i>{{ __('audit.filter_entity') }}
        </label>
        <div class="row g-2">
            <div class="col">
                <select class="form-select form-select-lg" id="entitySelect" name="entity_id">
                    <option value="">{{ __('audit.select_entity_placeholder') }}</option>
                    @foreach($currentTenant->entities as $entityConfig)
                        <option value="{{ $entityConfig->entity->id }}"
                            @if(request('entity_id') === $entityConfig->entity->id) selected @endif>
                            {{ $entityConfig->entity->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary btn-lg">
                    {{ __('audit.filter_button') }}
                </button>
            </div>
        </div>
    </div>
</form>
@endif
