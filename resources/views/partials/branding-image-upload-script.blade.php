@once
    @script
        <script>
            Alpine.data('brandingImageUpload', (config = {}) => ({
                uploading: false,
                progress: 0,
                error: null,

                async select(event) {
                    const input = event.target;
                    const file = input.files?.[0];

                    if (! file) {
                        return;
                    }

                    this.uploading = true;
                    this.progress = 0;
                    this.error = null;
                    this.dispatchUpload('livewire-upload-start');

                    try {
                        const resized = await this.resize(file);

                        await this.upload(resized);

                        this.dispatchUpload('livewire-upload-finish');
                    } catch (exception) {
                        this.error = @js(__('The image could not be processed. Please try another file.'));
                        this.dispatchUpload('livewire-upload-error');
                    } finally {
                        this.uploading = false;
                        input.value = '';
                    }
                },

                dispatchUpload(name) {
                    window.dispatchEvent(new CustomEvent(name, {
                        detail: { id: this.$wire.$id, property: config.property },
                    }));
                },

                async resize(file) {
                    if (! file.type.startsWith('image/')) {
                        throw new Error('Invalid file type');
                    }

                    const image = await new Promise((resolve, reject) => {
                        const element = new Image();
                        const url = URL.createObjectURL(file);

                        element.onload = () => {
                            URL.revokeObjectURL(url);
                            resolve(element);
                        };

                        element.onerror = () => {
                            URL.revokeObjectURL(url);
                            reject(new Error('Invalid image'));
                        };

                        element.src = url;
                    });

                    const scale = Math.min(
                        1,
                        config.maxWidth ? config.maxWidth / image.naturalWidth : 1,
                        config.maxHeight ? config.maxHeight / image.naturalHeight : 1,
                    );
                    const width = Math.max(1, Math.round(image.naturalWidth * scale));
                    const height = Math.max(1, Math.round(image.naturalHeight * scale));
                    const size = Math.max(width, height);
                    const canvas = document.createElement('canvas');

                    canvas.width = config.square ? size : width;
                    canvas.height = config.square ? size : height;

                    canvas.getContext('2d').drawImage(
                        image,
                        config.square ? (canvas.width - width) / 2 : 0,
                        config.square ? (canvas.height - height) / 2 : 0,
                        width,
                        height,
                    );

                    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.9));

                    if (! blob) {
                        throw new Error('Could not resize image');
                    }

                    const extension = blob.type === 'image/webp' ? 'webp' : 'png';
                    const name = file.name.replace(/\.[^.]+$/, '') + '.' + extension;

                    return new File([blob], name, { type: blob.type });
                },

                upload(file) {
                    return new Promise((resolve, reject) => {
                        this.$wire.upload(
                            config.property,
                            file,
                            () => resolve(),
                            () => reject(new Error('Upload failed')),
                            (event) => {
                                this.progress = event.detail.progress;
                            },
                        );
                    });
                },
            }));
        </script>
    @endscript
@endonce
