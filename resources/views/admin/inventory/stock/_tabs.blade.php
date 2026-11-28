<nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="{{ __('inventory.stock.title') }}">
    @foreach ([
        'stock' => ['admin.stock.index', 'inventory.stock.title'],
        'batches' => ['admin.stock.batches', 'inventory.stock.batches_title'],
        'movements' => ['admin.stock.movements', 'inventory.stock.movements_title'],
    ] as $tabKey => [$tabRoute, $tabLabel])
        <a href="{{ route($tabRoute) }}"
            class="rounded-xl border px-3 py-1.5 {{ $active === $tabKey ? 'border-brand-500 bg-brand-500/10 font-semibold' : 'border-line hover:bg-surface' }}">
            {{ __($tabLabel) }}
        </a>
    @endforeach
</nav>
