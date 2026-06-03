<button {{ $attributes->merge(['type' => 'submit', 'class' => 'app-btn-danger']) }}>
    {{ $slot }}
</button>
