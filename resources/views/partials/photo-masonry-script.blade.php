{{--
    Alpine component for the photo masonry wall.

    The server renders a flat list of tiles plus the responsive CSS columns as
    a no-JavaScript fallback. Once Alpine is ready this component takes over:
    it gives every tile an absolute column/position and appends new tiles to the
    shortest column, so loading another page never reflows photos that are
    already on screen.
--}}
Alpine.data('photoMasonry', () => ({
    gap: 4,
    breakpoints: [0],
    columnCounts: [1],
    columnCount: 0,
    assignments: new Map(),
    frame: null,
    resizeObserver: null,
    mutationObserver: null,
    tileObserver: null,
    observedTiles: new WeakSet(),

    init() {
        this.gap = Number(this.$el.dataset.masonryGap || 4);
        this.breakpoints = (this.$el.dataset.masonryBreakpoints || '0').split(',').map(Number);
        this.columnCounts = (this.$el.dataset.masonryColumns || '1').split(',').map(Number);

        this.$el.style.position = 'relative';

        this.resizeObserver = new ResizeObserver(() => this.schedule());        this.resizeObserver.observe(this.$el);

        this.tileObserver = new ResizeObserver(() => this.schedule());

        this.mutationObserver = new MutationObserver(() => {
            this.observeTiles();
            this.schedule();
        });

        this.observeTiles();
        this.observeMutations();

        // Livewire morphs can drop the inline height while a tile updates.
        // The morph hook above restores it; this listener re-runs the layout
        // once the new DOM is in place.
        this.$el.addEventListener('masonry:relayout', () => this.schedule());

        // Wait for the child x-data components (image aspects, video/PDF
        // placeholders) to initialize before measuring tile heights.
        this.$nextTick(() => this.layout(true));
    },

    destroy() {
        this.resizeObserver?.disconnect();
        this.tileObserver?.disconnect();
        this.mutationObserver?.disconnect();

        if (this.frame) {
            cancelAnimationFrame(this.frame);
        }
    },

    observeMutations() {
        this.mutationObserver.observe(this.$el, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class'],
        });
    },

    observeTiles() {
        this.$el.querySelectorAll(':scope > [data-photo-card]').forEach((tile) => {
            if (this.observedTiles.has(tile)) {
                return;
            }

            this.observedTiles.add(tile);
            this.tileObserver.observe(tile);
        });
    },

    schedule() {
        if (this.frame) {
            return;
        }

        this.frame = requestAnimationFrame(() => {
            this.frame = null;
            this.layout();
        });
    },

    countForWidth(width) {
        let count = this.columnCounts[0] || 1;

        this.breakpoints.forEach((breakpoint, index) => {
            if (width >= breakpoint && this.columnCounts[index] !== undefined) {
                count = this.columnCounts[index];
            }
        });

        return Math.max(1, count);
    },

    layout(force = false) {
        const width = this.$el.clientWidth;

        // Hidden tab panels have no width; the resize observer lays them out
        // again as soon as they become visible.
        if (! width) {
            return;
        }

        const columns = this.countForWidth(window.innerWidth);
        const gap = this.gap;
        const columnWidth = (width - gap * (columns - 1)) / columns;
        const tiles = Array.from(this.$el.querySelectorAll(':scope > [data-photo-card]'));

        if (! tiles.length) {
            this.$el.style.height = '';

            return;
        }

        // Our own inline style writes would otherwise feed the observer.
        this.mutationObserver?.disconnect();

        if (force || columns !== this.columnCount) {
            this.assignments.clear();
        }

        this.columnCount = columns;

        // Normalize the width before measuring so the browser resolves each
        // image height at its final column width in one layout pass.
        tiles.forEach((tile) => {
            tile.style.position = 'absolute';
            tile.style.width = `${columnWidth}px`;
            tile.style.margin = '0';
            tile.style.left = '0';
            tile.style.top = '0';
        });

        const heights = new Array(columns).fill(0);
        const metrics = tiles.map((tile) => ({
            tile,
            key: tile.dataset.photoId,
            height: tile.offsetHeight,
        }));

        metrics.forEach(({ tile, key, height }) => {
            let column = key !== undefined ? this.assignments.get(key) : undefined;

            if (column === undefined || column >= columns) {
                column = heights.indexOf(Math.min(...heights));

                if (key !== undefined) {
                    this.assignments.set(key, column);
                }
            }

            tile.style.left = `${column * (columnWidth + gap)}px`;
            tile.style.top = `${heights[column]}px`;
            heights[column] += height + gap;
        });

        this.$el.style.height = `${Math.max(0, Math.max(...heights) - gap)}px`;
        this.$el.dataset.masonryReady = 'true';

        this.observeTiles();
        this.observeMutations();
    },
}));

// Keep the masonry wall from collapsing while Livewire morphs a tile (for
// example when a photo is favorited). Morphdom drops the inline height Alpine
// wrote, which shrinks the page and can clamp the scroll position to the top.
// Copy the current height onto the incoming element before the morph and let
// the masonry relayout afterwards.
const preserveMasonryHeight = () => {
    if (! window.Livewire?.hook) {
        return;
    }

    Livewire.hook('morph.updating', ({ el, toEl }) => {
        if (! el?.hasAttribute?.('data-photo-masonry') || ! toEl) {
            return;
        }

        const height = el.style.height || (el.offsetHeight ? `${el.offsetHeight}px` : '');

        if (height) {
            toEl.style.height = height;
            toEl.style.minHeight = height;
        }
    });

    Livewire.hook('morph.updated', ({ el }) => {
        if (! el?.hasAttribute?.('data-photo-masonry')) {
            return;
        }

        el.style.minHeight = '';
        el.dispatchEvent(new CustomEvent('masonry:relayout'));
    });
};

if (window.Livewire) {
    preserveMasonryHeight();
} else {
    document.addEventListener('livewire:init', preserveMasonryHeight);
}
