<x-adminlte4::layouts.app :title="__('web/settings.title')">
    <x-adminlte4::card :title="__('web/settings.title')" :subtitle="__('web/settings.subtitle')">
        <div class="row g-3">
            @foreach ([
                ['route' => 'settings.platform', 'icon' => 'bi bi-sliders', 'label' => __('web/settings.groups.platform')],
                ['route' => 'settings.file-hosting', 'icon' => 'bi bi-cloud-upload', 'label' => __('web/settings.groups.file_hosting')],
                ['route' => 'settings.microstock', 'icon' => 'bi bi-images', 'label' => __('web/settings.groups.microstock')],
                ['route' => 'settings.vtual', 'icon' => 'bi bi-broadcast', 'label' => __('web/settings.groups.vtual')],
                ['route' => 'notification-audiences.index', 'icon' => 'bi bi-bell', 'label' => __('web/settings.groups.notification_audiences')],
                ['route' => 'currencies.index', 'icon' => 'bi bi-currency-exchange', 'label' => __('web/settings.groups.currencies')],
            ] as $card)
                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route($card['route']) }}" class="card h-100 shadow-sm text-decoration-none text-body">
                        <div class="card-body d-flex align-items-center gap-3">
                            <i class="{{ $card['icon'] }} fs-3 text-primary"></i>
                            <span class="fw-semibold">{{ $card['label'] }}</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
