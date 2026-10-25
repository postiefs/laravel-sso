@props(['label' => null])

@if (app(\Postiefs\Sso\Contracts\PortalClient::class)->enabled())
    <a href="{{ route('sso.login') }}" {{ $attributes->merge(['style' => 'display:block;padding:0.6rem 1rem;border:1px solid #d1d5db;border-radius:0.5rem;text-align:center;text-decoration:none;color:inherit;']) }}>
        {{ $label ?? trans('postiefs-sso::messages.sign_in_with_microsoft') }}
    </a>
@endif
