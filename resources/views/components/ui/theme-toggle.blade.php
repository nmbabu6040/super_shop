<button type="button" x-data="{
    isDark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.isDark = !this.isDark;
        document.documentElement.classList.toggle('dark', this.isDark);
        try { localStorage.setItem('theme', this.isDark ? 'dark' : 'light'); } catch (storageError) {}
    },
}" x-on:click="toggle()" class="btn btn-ghost size-9 p-0!"
    aria-label="{{ __('ui.toggle_theme') }}">
    <svg x-show="! isDark" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
    <svg x-show="isDark" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="5" />
        <path
            d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
    </svg>
</button>
