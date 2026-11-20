{{-- Must be placed inside a <form x-data="{ busy: false }" x-on:submit="busy = true">. --}}
<button type="submit" class="auth-submit" x-bind:disabled="busy">
    <svg x-show="busy" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25" />
        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    <span>{{ $slot }}</span>
</button>
