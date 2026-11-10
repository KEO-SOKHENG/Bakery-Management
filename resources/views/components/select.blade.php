@props([
    'name',
    'label' => null,
    'labelKey' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'required' => false,
    'error' => null,
    'help' => null,
])

<div class="form-group">
    @if($label)
        <label for="{{ $attributes->get('id', $name) }}" class="form-label">
            @if($labelKey)<span data-lang-key="{{ $labelKey }}">{{ $label }}</span>@else{{ $label }}@endif
            @if($required)
                <span class="required-star">*</span>
            @endif
        </label>
    @endif
    <select
        name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        @if($required) required @endif
        {{ $attributes->merge(['class' => 'form-control-select' . ($errors->has($name) || $error ? ' is-invalid' : '')]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @if(trim($slot) !== '')
            {{ $slot }}
        @else
            @foreach($options as $val => $text)
                <option value="{{ $val }}" {{ (string)old($name, $selected) === (string)$val ? 'selected' : '' }}>
                    {{ $text }}
                </option>
            @endforeach
        @endif
    </select>
    @if($error || $errors->has($name))
        <span class="form-error">{{ $error ?? $errors->first($name) }}</span>
    @elseif($help)
        <span class="form-help">{{ $help }}</span>
    @endif
</div>
