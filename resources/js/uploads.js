/**
 * Persistent gallery uploader.
 *
 * Lives outside the gallery Livewire component on purpose: `wire:navigate`
 * replaces the page body, but this module and its XHRs stay alive. Files are
 * uploaded to a regular authenticated endpoint, so each completed file is
 * already stored even if the photographer navigates away mid-batch.
 *
 * The reactive state is an Alpine store rendered by the dock in the app
 * layout. Raw File objects are kept in a module-level Map because Alpine
 * reactivity must not proxy them before they are appended to a FormData.
 */

const rawFiles = new Map();
const xhrs = new Map();
const TERMINAL_PHOTO_STATUSES = ['processed', 'skipped'];
const RESOLVED_STATUSES = ['processed', 'skipped', 'canceled', 'removed'];
const PREVIEW_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg'];

let nextId = 0;

function config() {
    return window.PicstomeUploadsConfig ?? {};
}

function label(key, replacements = {}) {
    let text = config().labels?.[key] ?? key;

    Object.entries(replacements).forEach(([name, value]) => {
        text = text.replace(`:${name}`, String(value));
    });

    return text;
}

function extensionOf(name) {
    const dot = String(name).lastIndexOf('.');

    return dot === -1 ? '' : String(name).slice(dot + 1).toLowerCase();
}

function formatBytes(bytes) {
    if (!bytes) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** index;
    const locale = config().locale || undefined;

    return `${new Intl.NumberFormat(locale, {
        maximumFractionDigits: index === 0 ? 0 : 1,
    }).format(value)} ${units[index]}`;
}

function formatDuration(seconds) {
    const total = Math.max(1, Math.round(seconds));

    if (total < 60) {
        return `${total}s`;
    }

    const minutes = Math.floor(total / 60);
    const remainingSeconds = total % 60;

    if (minutes < 60) {
        return remainingSeconds > 0 ? `${minutes}m ${remainingSeconds}s` : `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
}

function createUploader() {
    return {
        entries: [],
        open: false,
        collapsed: false,
        completedAt: null,
        allowUnload: false,
        exitPromptUrl: null,
        closePrompt: false,
        galleries: {},
        oversized: [],
        unsupported: [],
        activeUploads: 0,
        maxParallelUploads: 10,
        maxUploadsPerMinute: 200,
        uploadTimestamps: [],
        remainingByGallery: {},
        fileVersionsEnabled: Boolean(config().fileVersionsEnabled),
        samples: [],
        speed: 0,
        eta: null,
        estimating: false,
        sampleTimer: null,
        pollTimer: null,
        pollInFlight: false,
        queueTimer: null,
        listenersInstalled: false,

        get pendingEntries() {
            return this.entries.filter((entry) => !this.isResolved(entry));
        },

        get visibleEntries() {
            return this.entries.filter((entry) => !this.isResolved(entry));
        },

        get batchCount() {
            return this.entries.length;
        },

        get batchTitle() {
            return this.batchCount === 1
                ? label('fileCount', { count: this.batchCount })
                : label('fileCountPlural', { count: this.batchCount });
        },

        get activeCount() {
            return this.entries.filter((entry) => ['checking', 'queued', 'uploading'].includes(entry.status)).length;
        },

        get processingCount() {
            return this.entries.filter(
                (entry) => entry.status === 'completed' && !TERMINAL_PHOTO_STATUSES.includes(entry.photoStatus),
            ).length;
        },

        get totalBytes() {
            return this.entries
                .filter((entry) => !['failed', 'skipped', 'canceled', 'removed'].includes(entry.status))
                .reduce((total, entry) => total + entry.size, 0);
        },

        get uploadedBytes() {
            return this.entries.reduce((total, entry) => {
                if (['failed', 'skipped', 'canceled', 'removed'].includes(entry.status)) {
                    return total;
                }

                if (entry.status === 'completed' || entry.status === 'processed') {
                    return total + entry.size;
                }

                return total + Math.round((entry.progress / 100) * entry.size);
            }, 0);
        },

        get percent() {
            if (this.totalBytes === 0) {
                return 0;
            }

            return Math.min(100, Math.round((this.uploadedBytes / this.totalBytes) * 100));
        },

        get bytesLabel() {
            return `${formatBytes(this.uploadedBytes)} / ${formatBytes(this.totalBytes)}`;
        },

        get hasPendingUploads() {
            return this.pendingEntries.length > 0;
        },

        get showChip() {
            return !this.open && this.hasPendingUploads;
        },

        get allDone() {
            return this.entries.length > 0 && this.pendingEntries.length === 0 && this.completedAt !== null;
        },

        /** Files that have not reached the server yet. */
        get remainingCount() {
            return this.entries.filter((entry) =>
                ['checking', 'queued', 'uploading', 'conflict'].includes(entry.status),
            ).length;
        },

        get uploadedCount() {
            return this.entries.filter((entry) => entry.photoId !== null).length;
        },

        get successGallery() {
            const entry = [...this.entries].reverse().find((candidate) => candidate.photoId !== null && candidate.galleryId);

            return entry ? this.galleries[entry.galleryId] ?? null : null;
        },

        get successLabel() {
            return this.uploadedCount === 1
                ? label('photoUploaded', { count: this.uploadedCount, gallery: this.successGallery?.name ?? '' })
                : label('photosUploaded', { count: this.uploadedCount, gallery: this.successGallery?.name ?? '' });
        },

        get closeWarning() {
            return this.remainingCount === 1
                ? label('closeWarningOne')
                : label('closeWarning', { count: this.remainingCount });
        },

        get etaLabel() {
            if (this.allDone) {
                return '';
            }

            if (this.activeCount > 0) {
                if (this.eta !== null) {
                    return label('remaining', { time: formatDuration(this.eta) });
                }

                return this.estimating ? label('estimating') : label('calculating');
            }

            if (this.processingCount > 0) {
                return this.processingCount === 1
                    ? label('finalizingOne')
                    : label('finalizingCount', { count: this.processingCount });
            }

            if (this.entries.length > 0 && this.pendingEntries.length === 0) {
                return label('allReady');
            }

            return '';
        },

        get conflictLabel() {
            const conflicts = this.entries.filter((entry) => entry.status === 'conflict').length;

            return conflicts === 1
                ? label('conflictOne')
                : label('conflictMany', { count: conflicts });
        },

        isResolved(entry) {
            return RESOLVED_STATUSES.includes(entry.status);
        },

        isPhotoStatusTerminal(status) {
            return TERMINAL_PHOTO_STATUSES.includes(status);
        },

        statusLabel(entry) {
            if (entry.status === 'failed') {
                return label('failed');
            }

            if (entry.status === 'conflict') {
                return label('alreadyExists');
            }

            if (entry.status === 'completed') {
                return label(entry.photoStatus || 'finalizing');
            }

            return label(entry.status);
        },

        progressLabel(entry) {
            if (entry.status === 'completed' || entry.status === 'processed') {
                return '100%';
            }

            return `${entry.progress}%`;
        },

        sizeLabel(entry) {
            return formatBytes(entry.size);
        },

        /**
         * Hand a selection of files to the uploader. Called by the gallery
         * page (file input and full-page drop zone).
         */
        enqueue(fileList, context = {}) {
            const files = Array.from(fileList ?? []);
            const galleryId = context.galleryId ? String(context.galleryId) : null;

            if (files.length === 0 || !galleryId) {
                return;
            }

            if (this.entries.length > 0 && this.pendingEntries.length === 0) {
                this.resetBatch();
            }

            this.open = true;
            this.collapsed = false;
            this.completedAt = null;
            this.oversized = [];
            this.unsupported = [];
            this.galleries[galleryId] = {
                name: context.galleryName ?? '',
                url: this.galleryUrl(galleryId),
            };

            const extensions = config().extensions ?? [];
            const maxBytes = config().maxBytes ?? 5 * 1024 * 1024 * 1024;
            const added = [];

            files.forEach((file) => {
                const extension = extensionOf(file.name);

                if (!extensions.includes(extension)) {
                    this.unsupported.push(file.name);

                    return;
                }

                if (file.size > maxBytes) {
                    this.oversized.push(file.name);

                    return;
                }

                const entry = {
                    id: ++nextId,
                    name: file.name,
                    size: file.size,
                    galleryId,
                    progress: 0,
                    status: 'checking',
                    error: null,
                    photoId: null,
                    photoStatus: null,
                    conflictAction: null,
                    previewUrl: PREVIEW_EXTENSIONS.includes(extension) ? URL.createObjectURL(file) : null,
                };

                this.entries.push(entry);

                // Read the entry back from the reactive array: Alpine wraps it
                // in a proxy, and every later status change must go through
                // that proxy or the dock will not re-render.
                const stored = this.entries[this.entries.length - 1];
                rawFiles.set(stored.id, file);
                added.push(stored);
            });

            if (added.length === 0) {
                return;
            }

            if (! this.queueTimer) {
                this.queueTimer = window.setInterval(() => this.processQueue(), 1000);
            }

            this.checkBatch(added, galleryId);
        },

        async checkBatch(batch, galleryId) {
            const results = await Promise.all(this.chunk(batch, 500).map(async (chunk) => {
                try {
                    const response = await fetch(this.url('check', galleryId), {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config().csrfToken ?? '',
                        },
                        body: JSON.stringify({ names: chunk.map((entry) => entry.name) }),
                        credentials: 'same-origin',
                    });

                    if (response.status === 419) {
                        this.failBatch(chunk, label('sessionExpired'));

                        return null;
                    }

                    if (! response.ok) {
                        throw new Error(`check failed: ${response.status}`);
                    }

                    if (! (response.headers.get('content-type') ?? '').includes('application/json')) {
                        throw new Error('check returned a non-JSON response');
                    }

                    return await response.json();
                } catch (error) {
                    // Fail open: the upload endpoint still validates every
                    // file. A network hiccup must not strand the batch.
                    console.error('Upload pre-check failed', error);
                    chunk.forEach((entry) => {
                        if (entry.status === 'checking') {
                            entry.status = 'queued';
                        }
                    });

                    return null;
                }
            }));

            const duplicates = new Set();
            const rawExtensions = config().rawExtensions ?? [];

            results.filter(Boolean).forEach((payload) => {
                (payload.duplicates ?? []).forEach((name) => duplicates.add(name));
                this.remainingByGallery[galleryId] = payload.remaining_storage_bytes ?? null;
                this.fileVersionsEnabled = Boolean(payload.file_versions_enabled);
            });

            batch.forEach((entry) => {
                if (entry.status !== 'checking') {
                    return;
                }

                entry.status = 'queued';

                if (duplicates.has(entry.name)) {
                    entry.status = rawExtensions.includes(extensionOf(entry.name)) ? 'queued' : 'conflict';
                    entry.conflictAction = rawExtensions.includes(extensionOf(entry.name)) ? 'version' : null;
                }
            });

            this.applyStoragePrecheck(batch, galleryId);
            this.processQueue();
        },

        chunk(items, size) {
            const chunks = [];

            for (let index = 0; index < items.length; index += size) {
                chunks.push(items.slice(index, index + size));
            }

            return chunks;
        },

        failBatch(batch, message) {
            batch.forEach((entry) => {
                entry.status = 'failed';
                entry.error = message;
                entry.progress = 0;
            });

            this.processQueue();
            this.maybeFinish();
        },

        applyStoragePrecheck(batch, galleryId) {
            const remaining = this.remainingByGallery[galleryId];

            if (remaining === null || remaining === undefined) {
                return;
            }

            let reserved = this.entries
                .filter(
                    (entry) => !batch.includes(entry) &&
                        entry.galleryId === galleryId &&
                        ['checking', 'queued', 'uploading', 'conflict'].includes(entry.status),
                )
                .reduce((total, entry) => total + entry.size, 0);

            batch.forEach((entry) => {
                if (! ['queued', 'checking'].includes(entry.status)) {
                    return;
                }

                const available = Math.max(0, remaining - reserved);

                if (entry.size > available) {
                    entry.status = 'failed';
                    entry.error = label('storageQuota');
                    entry.progress = 0;

                    return;
                }

                reserved += entry.size;
            });
        },

        url(kind, galleryId) {
            const key = `${kind}Url`;

            return String(config()[key] ?? '').replace('__gallery__', galleryId);
        },

        galleryUrl(galleryId) {
            return String(config().galleryUrl ?? `/galleries/${galleryId}`).replace('__gallery__', galleryId);
        },

        processQueue() {
            this.pruneRateLimit();

            while (this.activeUploads < this.maxParallelUploads && this.canUpload()) {
                const entry = this.entries.find((candidate) => candidate.status === 'queued');

                if (! entry) {
                    break;
                }

                this.startUpload(entry);
            }

            if (! this.entries.some((entry) => entry.status === 'queued') && this.queueTimer) {
                window.clearInterval(this.queueTimer);
                this.queueTimer = null;
            }
        },

        canUpload() {
            return this.uploadTimestamps.length < this.maxUploadsPerMinute;
        },

        pruneRateLimit() {
            const oneMinuteAgo = Date.now() - 60000;

            this.uploadTimestamps = this.uploadTimestamps.filter((timestamp) => timestamp > oneMinuteAgo);
        },

        startUpload(entry) {
            const file = rawFiles.get(entry.id);

            if (! file) {
                entry.status = 'failed';
                entry.error = label('uploadFailed');

                return;
            }

            entry.status = 'uploading';
            entry.progress = 0;
            entry.error = null;
            this.activeUploads++;
            this.uploadTimestamps.push(Date.now());
            this.startSampling();

            const form = new FormData();
            form.append('file', file, entry.name);
            form.append('action', entry.conflictAction || 'version');

            const xhr = new XMLHttpRequest();
            xhrs.set(entry.id, xhr);

            xhr.open('POST', this.url('upload', entry.galleryId));
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', config().csrfToken ?? '');

            xhr.upload.addEventListener('progress', (event) => {
                if (! event.lengthComputable) {
                    return;
                }

                entry.progress = Math.min(99, Math.round((event.loaded / event.total) * 100));
            });

            xhr.addEventListener('load', () => {
                let payload = {};

                try {
                    payload = JSON.parse(xhr.responseText || '{}');
                } catch (error) {
                    payload = {};
                }

                this.finishUpload(entry);

                if (xhr.status >= 200 && xhr.status < 300) {
                    entry.progress = 100;
                    entry.status = 'completed';
                    entry.photoId = payload.photo_id ?? null;
                    entry.photoStatus = payload.status ?? null;
                    entry.error = null;

                    if (payload.remaining_storage_bytes !== undefined) {
                        this.remainingByGallery[entry.galleryId] = payload.remaining_storage_bytes;
                    }

                    if (! entry.photoId) {
                        entry.status = 'processed';
                        entry.photoStatus = 'processed';
                    }

                    this.startPolling();
                    this.maybeFinish();

                    return;
                }

                const message = xhr.status === 419
                    ? label('sessionExpired')
                    : payload.errors?.file?.[0] || payload.message || label('uploadFailed');

                this.failEntry(entry, message);
            });

            xhr.addEventListener('error', () => {
                this.finishUpload(entry);
                this.failEntry(entry, label('uploadFailed'));
            });

            xhr.addEventListener('timeout', () => {
                this.finishUpload(entry);
                this.failEntry(entry, label('uploadFailed'));
            });

            xhr.send(form);
        },

        finishUpload(entry) {
            xhrs.delete(entry.id);
            this.activeUploads = Math.max(0, this.activeUploads - 1);
            this.processQueue();
        },

        failEntry(entry, message) {
            entry.status = 'failed';
            entry.error = message;
            entry.progress = 0;
            this.processQueue();
            this.maybeFinish();
        },

        retry(entry) {
            if (! rawFiles.has(entry.id)) {
                return;
            }

            entry.status = 'queued';
            entry.progress = 0;
            entry.error = null;
            entry.photoId = null;
            entry.photoStatus = null;
            this.startSampling();
            this.processQueue();
        },

        resolveConflict(entry, action) {
            if (action === 'skip') {
                entry.status = 'skipped';
                this.maybeFinish();

                return;
            }

            entry.conflictAction = action === 'replace' ? 'replace' : 'version';
            entry.status = 'queued';
            entry.error = null;
            this.processQueue();
        },

        resolveAllConflicts(action) {
            this.entries
                .filter((entry) => entry.status === 'conflict')
                .forEach((entry) => this.resolveConflict(entry, action));
        },

        remove(entry) {
            const xhr = xhrs.get(entry.id);

            if (xhr) {
                xhr.onload = null;
                xhr.onerror = null;
                xhr.ontimeout = null;
                xhr.abort();
                xhrs.delete(entry.id);
                this.activeUploads = Math.max(0, this.activeUploads - 1);
            }

            entry.status = entry.status === 'completed' ? 'removed' : 'canceled';

            if (entry.previewUrl) {
                URL.revokeObjectURL(entry.previewUrl);
                entry.previewUrl = null;
            }

            rawFiles.delete(entry.id);
            this.processQueue();
            this.maybeFinish();
        },

        /**
         * Poll the server for photos that finished uploading but are still
         * processing or warming the CDN. Runs even after an in-app navigation.
         */
        startPolling() {
            if (! this.pollTimer) {
                this.pollTimer = window.setInterval(() => this.pollStatuses(), 2000);
            }

            this.pollStatuses();
        },

        stopPolling() {
            if (this.pollTimer) {
                window.clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async pollStatuses() {
            if (this.pollInFlight) {
                return;
            }

            const tracked = this.entries.filter(
                (entry) => entry.photoId &&
                    ! this.isPhotoStatusTerminal(entry.photoStatus) &&
                    ! ['removed', 'canceled'].includes(entry.status),
            );

            if (tracked.length === 0) {
                this.stopPolling();
                this.maybeFinish();

                return;
            }

            this.pollInFlight = true;

            try {
                const groups = tracked.reduce((groups, entry) => {
                    (groups[entry.galleryId] ??= []).push(entry);

                    return groups;
                }, {});

                await Promise.all(
                    Object.entries(groups).flatMap(([galleryId, entries]) =>
                        this.chunk(entries, 200).map(async (chunk) => {
                            const params = new URLSearchParams();
                            chunk.forEach((entry) => params.append('ids[]', entry.photoId));

                            const response = await fetch(`${this.url('status', galleryId)}?${params.toString()}`, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });

                            if (! response.ok) {
                                return;
                            }

                            if (! (response.headers.get('content-type') ?? '').includes('application/json')) {
                                return;
                            }

                            const payload = await response.json();
                            const statuses = payload.statuses ?? {};

                            chunk.forEach((entry) => {
                                const status = statuses[entry.photoId];

                                if (! status) {
                                    return;
                                }

                                entry.photoStatus = status;

                                if (status === 'failed') {
                                    entry.status = 'failed';
                                    entry.error = label('processingFailed');
                                }

                                if (this.isPhotoStatusTerminal(status)) {
                                    entry.status = 'processed';
                                }
                            });
                        }),
                    ),
                );
            } catch (error) {
                console.error('Upload status poll failed', error);
            } finally {
                this.pollInFlight = false;
            }

            if (this.entries.every((entry) => ! entry.photoId || this.isPhotoStatusTerminal(entry.photoStatus))) {
                this.stopPolling();
            }

            this.maybeFinish();
        },

        maybeFinish() {
            if (this.entries.length === 0) {
                return;
            }

            if (this.pendingEntries.length > 0) {
                this.completedAt = null;

                return;
            }

            if (this.completedAt !== null) {
                return;
            }

            // Nothing reached the server (for example, every file was
            // dismissed). Do not show an empty "0 photos uploaded" summary.
            if (this.uploadedCount === 0) {
                this.dismiss();

                return;
            }

            this.completedAt = Date.now();
            this.open = true;
            this.collapsed = false;
            this.stopSampling();
            this.stopPolling();

            const galleries = [...new Set(this.entries.map((entry) => entry.galleryId))];
            galleries.forEach((galleryId) => {
                window.dispatchEvent(new CustomEvent('gallery-upload-finished', { detail: { galleryId } }));
            });

        },

        startSampling() {
            if (this.sampleTimer) {
                return;
            }

            this.samples = [];
            this.estimating = false;
            this.sampleTimer = window.setInterval(() => this.sample(), 500);
        },

        stopSampling() {
            if (this.sampleTimer) {
                window.clearInterval(this.sampleTimer);
                this.sampleTimer = null;
            }

            this.samples = [];
            this.speed = 0;
            this.eta = null;
            this.estimating = false;
        },

        /**
         * Rolling throughput over the last ~10 seconds. Uploads run in
         * parallel, so the ETA has to use aggregate bytes, not per-file speed.
         */
        sample() {
            if (this.activeUploads === 0) {
                const stillQueued = this.entries.some((entry) => ['queued', 'checking'].includes(entry.status));

                if (! stillQueued) {
                    this.stopSampling();
                }

                return;
            }

            const now = performance.now();
            this.samples.push({ at: now, bytes: this.uploadedBytes });

            while (this.samples.length > 2 && now - this.samples[0].at > 10000) {
                this.samples.shift();
            }

            if (this.samples.length < 2) {
                return;
            }

            const first = this.samples[0];
            const last = this.samples[this.samples.length - 1];
            const elapsed = (last.at - first.at) / 1000;

            if (elapsed < 1) {
                return;
            }

            this.speed = Math.max(0, (last.bytes - first.bytes) / elapsed);

            const remaining = Math.max(0, this.totalBytes - this.uploadedBytes);

            if (this.speed > 1024 && remaining > 0) {
                this.eta = Math.max(1, Math.round(remaining / this.speed));
                this.estimating = false;
            } else {
                this.eta = null;
                this.estimating = now - first.at >= 5000;
            }
        },

        minimize() {
            this.open = false;
        },

        show() {
            this.open = true;
        },

        /**
         * The X button cancels whatever is still queued or uploading. Photos
         * already stored on the server stay in the gallery.
         */
        requestClose() {
            if (this.remainingCount === 0) {
                this.dismiss();

                return;
            }

            this.closePrompt = true;
        },

        cancelClose() {
            this.closePrompt = false;
        },

        confirmClose() {
            this.closePrompt = false;
            this.dismiss();
        },

        toggleCollapsed() {
            this.collapsed = ! this.collapsed;
        },

        dismiss() {
            this.resetBatch();
            this.open = false;
            this.collapsed = false;
        },

        resetBatch() {
            this.entries.forEach((entry) => {
                const xhr = xhrs.get(entry.id);

                if (xhr) {
                    xhr.abort();
                    xhrs.delete(entry.id);
                }

                if (entry.previewUrl) {
                    URL.revokeObjectURL(entry.previewUrl);
                }

                rawFiles.delete(entry.id);
            });

            this.entries = [];
            this.oversized = [];
            this.unsupported = [];
            this.activeUploads = 0;
            this.completedAt = null;
            this.eta = null;
            this.estimating = false;
            this.samples = [];
            this.stopSampling();
            this.stopPolling();

            if (this.queueTimer) {
                window.clearInterval(this.queueTimer);
                this.queueTimer = null;
            }
        },

        requestLeave(url) {
            if (! this.hasPendingUploads) {
                window.location.href = url;

                return;
            }

            this.exitPromptUrl = url;
        },

        cancelLeave() {
            this.exitPromptUrl = null;
        },

        leaveAnyway() {
            const url = this.exitPromptUrl;
            this.exitPromptUrl = null;
            this.allowUnload = true;

            if (url) {
                window.location.href = url;

                return;
            }

            window.location.reload();
        },

        installGlobalListeners() {
            if (this.listenersInstalled) {
                return;
            }

            this.listenersInstalled = true;

            window.addEventListener('beforeunload', (event) => {
                if (! this.hasPendingUploads || this.allowUnload) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });

            document.addEventListener('click', (event) => {
                if (! this.hasPendingUploads || this.allowUnload) {
                    return;
                }

                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                const link = event.target.closest?.('a[href]');

                if (! link || link.hasAttribute('download')) {
                    return;
                }

                // `wire:navigate.hover` is a different attribute name than
                // `wire:navigate`, so check the prefix instead of one name.
                if (Array.from(link.attributes).some((attribute) => attribute.name.startsWith('wire:navigate'))) {
                    return;
                }

                const protocol = new URL(link.href, window.location.href).protocol;

                if (! ['http:', 'https:'].includes(protocol)) {
                    return;
                }

                if (link.target === '_blank' || link.getAttribute('href')?.startsWith('#')) {
                    return;
                }

                const url = new URL(link.href, window.location.href);

                if (url.origin === window.location.origin && url.pathname === window.location.pathname && url.search === window.location.search) {
                    return;
                }

                event.preventDefault();
                this.requestLeave(url.href);
            });
        },
    };
}

const uploader = createUploader();

// Before Alpine boots this is a plain object so the gallery page can be wired
// up; `bootUploads()` swaps in the reactive store proxy, which is what makes
// updates from XHR callbacks reach the dock.
window.PicstomeUploads = uploader;

function bootUploads() {
    if (! window.Alpine || window.PicstomeUploadsBooted) {
        return;
    }

    window.PicstomeUploadsBooted = true;

    window.Alpine.store('uploads', uploader);

    // `Alpine.store(name, value)` is a setter with no return value. The getter
    // hands back the reactive proxy, which is what XHR callbacks must mutate
    // so the dock re-renders.
    const store = window.Alpine.store('uploads');

    window.PicstomeUploads = store;
    store.installGlobalListeners();
}

if (window.Alpine) {
    bootUploads();
} else {
    document.addEventListener('alpine:init', bootUploads, { once: true });
}
