<?php

use Livewire\Component;

new class extends Component
{
    public $data;
    public function mount($data){
        $this->data=$data;
    }
};
?>

<div>
    <div
        wire:ignore
        id="store-map"
        class="mt-8 relative z-0 w-full h-[400px] rounded-[3.5rem] overflow-hidden border border-white/40 bg-white/40 shadow-sm transition-all duration-500 hover:shadow-2xl dark:border-white/10 dark:bg-white/[0.03]"
    ></div>

    @push('styles')
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        />
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

        <script>
            function initStoreMap() {
                const mapData = {{ Js::from($data) }};

                const latitude = mapData.lat;
                const longitude = mapData.long;

                const mapElement = document.getElementById('store-map');

                if (!mapElement) {
                    return;
                }

                // جلوگیری از ساخت مجدد نقشه
                if (mapElement._leaflet_id) {
                    return;
                }


                const map = L.map(mapElement, {
                    scrollWheelZoom: false,
                }).setView(
                    [latitude, longitude],
                    16
                );

                L.tileLayer(
                    'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }
                ).addTo(map);

                L.marker([
                    latitude,
                    longitude
                ])
                    .addTo(map)
                    .bindPopup(`
                    <div class="text-center p-2">
                        <strong>زاراچرم</strong>
                        <br>
                        <span>فروشگاه کیف و کفش</span>
                    </div>
                `)
                    .openPopup();

                setTimeout(() => {
                    map.invalidateSize();
                }, 300);
            }

            if (document.readyState === 'loading') {
                document.addEventListener(
                    'DOMContentLoaded',
                    initStoreMap,
                    { once: true }
                );
            } else {
                initStoreMap();
            }

            document.addEventListener(
                'livewire:navigated',
                initStoreMap
            );
        </script>
    @endpush
</div>
