@php
    $analytics = app(\App\Services\AnalyticsService::class);
@endphp

@if ($analytics->shouldTrack(request()))
    @php
        $ga4Id = $analytics->measurementId();
        $ga4Config = [
            'linker' => ['domains' => ['picstome.com']],
        ];

        if (auth()->check()) {
            $ga4Config['user_id'] = (string) auth()->id();
        }
    @endphp

    {{-- GA4 only loads after the visitor consented on the marketing site. --}}
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            window.dataLayer.push(arguments);
        }

        gtag('consent', 'default', {
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            analytics_storage: 'granted',
        });
        gtag('js', new Date());
        gtag('config', @js($ga4Id), @js($ga4Config));

        window.addEventListener('analytics:event', function (event) {
            var detail = event.detail || {};

            if (detail.name && typeof window.gtag === 'function') {
                window.gtag('event', detail.name, detail.params || {});
            }
        });
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}"></script>
@endif
