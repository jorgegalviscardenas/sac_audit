@if(!request('entity_id') && isset($currentTenant) && count($currentTenant->entities) > 0)
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>{{ __('audit.select_entity_to_view') }}
</div>
@elseif(isset($audits) && count($audits) > 0)
<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>{{ __('audit.table.date') }}</th>
                <th>{{ __('audit.table.type') }}</th>
                <th>{{ __('audit.table.entity') }}</th>
                <th>{{ __('audit.table.object_id') }}</th>
                <th>{{ __('audit.table.blame_user') }}</th>
                <th>{{ __('audit.table.diffs') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($audits as $audit)
            <tr>
                <td>{{ $audit->created_at->format('Y-m-d H:i:s') }}</td>
                <td>
                    <span class="badge bg-{{ $audit->type === 1 ? 'success' : ($audit->type === 2 ? 'warning' : 'danger') }}">
                        {{ __('audit.types.' . $audit->type) }}
                    </span>
                </td>
                <td>{{ $entity?->name }}</td>
                <td><code>{{ $audit->object_id }}</code></td>
                <td>{{ $audit->blame_user }}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#diffs-{{ $audit->id }}"
                            aria-expanded="false">
                        <i class="bi bi-eye"></i> {{ __('audit.table.view_diffs') }}
                    </button>
                    <div class="collapse mt-2" id="diffs-{{ $audit->id }}">
                        <pre class="bg-light p-2 rounded"><code>{!! $audit->getDiffsHTML() !!}</code></pre>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if(method_exists($audits, 'links'))
    <div class="mt-3">
        <div class="text-center mb-2">
            <small class="text-muted">
                {{ __('audit.pagination.showing', [
                    'from' => $audits->firstItem() ?? 0,
                    'to' => $audits->lastItem() ?? 0,
                    'total' => number_format($audits->total())
                ]) }}
            </small>
        </div>
        <div class="d-flex justify-content-center">
            {{ $audits->onEachSide(1)->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endif
@elseif(isset($audits))
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>{{ __('audit.no_audits') }}
</div>
@endif
