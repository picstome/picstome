<div
    x-data="{
        active: false,
        wakeLock: null,

        async start() {
            this.active = true;
            document.body.classList.add('overflow-hidden');

            try {
                if (! document.fullscreenElement && document.documentElement.requestFullscreen) {
                    await document.documentElement.requestFullscreen();
                }
            } catch {}

            await this.requestWakeLock();
        },

        async stop() {
            this.active = false;
            document.body.classList.remove('overflow-hidden');

            await this.releaseWakeLock();

            if (document.fullscreenElement) {
                try {
                    await document.exitFullscreen();
                } catch {}
            }
        },

        async requestWakeLock() {
            if (! ('wakeLock' in navigator) || this.wakeLock) {
                return;
            }

            try {
                const wakeLock = await navigator.wakeLock.request('screen');

                wakeLock.addEventListener('release', () => {
                    this.wakeLock = null;
                });

                this.wakeLock = wakeLock;
            } catch {
                this.wakeLock = null;
            }
        },

        async releaseWakeLock() {
            if (! this.wakeLock) {
                return;
            }

            try {
                await this.wakeLock.release();
            } catch {}

            this.wakeLock = null;
        }
    }"
    x-on:black-screen:start.window="start()"
    x-on:keydown.escape.window="active && stop()"
    x-on:fullscreenchange.window="! document.fullscreenElement && active && stop()"
    x-on:visibilitychange.window="document.visibilityState === 'visible' && active && requestWakeLock()"
>
    <div
        x-show="active"
        style="display: none"
        class="fixed inset-0 z-[100] cursor-none touch-none bg-black"
        data-black-screen-overlay
        role="presentation"
        x-on:click="stop()"
    >
        <span class="sr-only">{{ __('Press ESC or click anywhere to exit.') }}</span>
    </div>
</div>
