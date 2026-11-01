@props([
    'id',
    'title' => null,
    'maxWidth' => '540px',
    'showClose' => true,
])

<div id="{{ $id }}" {{ $attributes->merge(['class' => 'modal-backdrop']) }}>
    <div class="modal-card" style="max-width: {{ $maxWidth }};">
        @if($title)
            <div class="modal-header">
                <h3 class="modal-title">{{ $title }}</h3>
                @if($showClose)
                    <button type="button" class="btn-modal-close" onclick="const m = document.getElementById('{{ $id }}'); if(m){ m.classList.remove('active', 'is-active'); m.style.display = 'none'; }" aria-label="Close">&times;</button>
                @endif
            </div>
        @endif
        <div class="modal-body">
            {{ $slot }}
        </div>
        @if(isset($footer))
            <div class="modal-footer">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
