import {
    escapeHtml,
    prompt,
    toast,
} from './shared.js';

export const mediaMethods = {
    async openMediaPicker(propName, altTarget = '') {
        const content = this.selectedContent();
        if (!propName || !content) {
            return;
        }

        let selected = null;
        const currentUrl = String(content.props?.[propName] || '');
        const mediaItems = await this.fetchMediaItems();
        const visibilityOptions = this.media.visibilityOptions || {
            public: this.labels.public,
            registered: this.labels.registered,
            private: this.labels.private,
        };

        const promptResult = prompt(`
            <div class="media-picker-modal notify-form">
                <div>
                    <div>${escapeHtml(this.labels.mediaPickerTitle)}</div>
                    <p class="notify-form-intro">${escapeHtml(this.labels.mediaPickerIntro)}</p>
                </div>
                <div class="notify-form-grid">
                    <label class="notify-field">
                        <span class="notify-field-label">${escapeHtml(this.labels.visibility)}</span>
                        <select data-page-media-visibility>
                            ${Object.entries(visibilityOptions).map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('')}
                        </select>
                    </label>
                    <label class="notify-field">
                        <span class="notify-field-label">${escapeHtml(this.labels.altText)}</span>
                        <input type="text" value="${escapeHtml(String(content.props?.[altTarget] || ''))}" data-page-media-alt>
                    </label>
                </div>
                <div class="media-picker-upload ${this.media.canUpload ? '' : 'hidden'}">
                    <input class="hidden" type="file" accept="${escapeHtml(this.mediaAcceptAttribute())}" data-page-media-file>
                    <button class="media-picker-drop" type="button" data-page-media-upload>
                        <span class="media-picker-icon" aria-hidden="true">↑</span>
                        <span>${escapeHtml(this.labels.mediaChooseFile)}</span>
                    </button>
                </div>
                <div class="media-picker-selected" data-page-media-selected>${currentUrl ? escapeHtml(currentUrl) : escapeHtml(this.labels.mediaEmptyUrl)}</div>
                <div>
                    <div class="notify-section-head"><span>${escapeHtml(this.labels.mediaLibrary)}</span><span>${mediaItems.length}</span></div>
                    <div class="media-picker-grid" data-page-media-library>
                        ${this.renderMediaPickerItems(mediaItems, currentUrl)}
                    </div>
                </div>
            </div>
        `, {
            confirmButtonText: this.labels.mediaUse,
            cancelButtonText: this.labels.cancel,
            showCancelButton: true,
            style: {width: 'min(920px, calc(100vw - 32px))', maxWidth: 'min(920px, calc(100vw - 32px))'},
            confirmCallback: (promptInstance) => {
                const container = promptInstance.container;
                const url = String(selected?.public_url || currentUrl || '').trim();
                if (url === '') {
                    toast(this.labels.mediaNoSelection);
                    return true;
                }

                this.setBlockProp(propName, url, {renderSettings: true});
                if (altTarget !== '') {
                    const alt = String(container.querySelector('[data-page-media-alt]')?.value || '').trim();
                    if (alt !== '') {
                        this.setBlockProp(altTarget, alt, {renderSettings: true});
                    }
                }

                promptInstance.promise.resolve(true);
                promptInstance.remove();
                return true;
            },
        });

        window.setTimeout(() => {
            const modal = document.querySelector('.media-picker-modal');
            if (!(modal instanceof HTMLElement)) {
                return;
            }

            const notify = modal.closest('.notify');
            const selectedLabel = modal.querySelector('[data-page-media-selected]');
            const setSelected = (media) => {
                selected = media;
                if (selectedLabel) {
                    selectedLabel.textContent = String(media?.public_url || this.labels.mediaEmptyUrl);
                }
                modal.querySelectorAll('[data-page-media-choice]').forEach((item) => {
                    item.classList.toggle('is-selected', String(item.getAttribute('data-page-media-choice') || '') === String(media?.public_url || ''));
                });
                const altInput = modal.querySelector('[data-page-media-alt]');
                if (altInput instanceof HTMLInputElement && String(altInput.value || '') === '') {
                    altInput.value = String(media?.alt_text || '');
                }
            };

            const bindChoices = () => {
                modal.querySelectorAll('[data-page-media-choice]').forEach((item) => {
                    item.addEventListener('click', () => {
                        setSelected({
                            public_url: String(item.getAttribute('data-page-media-choice') || ''),
                            alt_text: String(item.getAttribute('data-page-media-alt-value') || ''),
                        });
                    });
                });
            };

            const fileInput = modal.querySelector('[data-page-media-file]');
            const uploadButton = modal.querySelector('[data-page-media-upload]');
            uploadButton?.addEventListener('click', () => {
                if (fileInput instanceof HTMLInputElement) {
                    fileInput.click();
                }
            });

            fileInput?.addEventListener('change', async () => {
                if (!(fileInput instanceof HTMLInputElement) || !fileInput.files?.[0]) {
                    return;
                }

                const file = fileInput.files[0];
                if (!this.validateMediaUpload(file)) {
                    fileInput.value = '';
                    return;
                }

                uploadButton?.setAttribute('disabled', 'disabled');
                try {
                    const formData = new FormData();
                    formData.append('file', file, file.name);
                    formData.append('visibility', String(modal.querySelector('[data-page-media-visibility]')?.value || 'public'));
                    formData.append('alt_text', String(modal.querySelector('[data-page-media-alt]')?.value || ''));
                    formData.append('collection', 'pages');

                    const response = await (window.NixCMS?.fetch || fetch)(String(this.media.urls?.upload || ''), {
                        method: 'POST',
                        headers: {'Accept': 'application/json'},
                        body: formData,
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || data?.success === false || !data?.media) {
                        toast(data?.reason || this.labels.mediaUploadFailed);
                        return;
                    }

                    setSelected(data.media);
                    const library = modal.querySelector('[data-page-media-library]');
                    if (library) {
                        mediaItems.unshift(data.media);
                        library.innerHTML = this.renderMediaPickerItems(mediaItems, String(data.media.public_url || ''));
                        bindChoices();
                    }
                } catch (error) {
                    toast(this.labels.mediaUploadFailed);
                } finally {
                    uploadButton?.removeAttribute('disabled');
                    fileInput.value = '';
                }
            });

            bindChoices();
            if (currentUrl !== '') {
                setSelected({public_url: currentUrl, alt_text: ''});
            }
            if (notify instanceof HTMLElement) {
                notify.classList.add('media-picker-notify');
            }
        }, 0);

        return await promptResult;
    },

    validateMediaUpload(file) {
        const maxUploadSize = Number(this.media.maxUploadSize || 0);
        if (maxUploadSize > 0 && file.size > maxUploadSize) {
            toast(this.labels.mediaFileTooLarge);
            return false;
        }

        const extension = String(file.name || '').split('.').pop().toLowerCase();
        const allowed = Array.isArray(this.media.acceptedExtensions) ? this.media.acceptedExtensions : [];
        const imageAllowed = allowed.filter((item) => ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(String(item).toLowerCase()));
        if (imageAllowed.length > 0 && !imageAllowed.includes(extension)) {
            toast(this.labels.mediaInvalidExtension);
            return false;
        }

        return true;
    },

    renderMediaPickerItems(items, currentUrl = '') {
        if (!Array.isArray(items) || items.length === 0) {
            return `<div class="media-picker-empty">${escapeHtml(this.labels.mediaNoFiles)}</div>`;
        }

        return items
            .filter((item) => item.kind === 'image')
            .map((item) => {
                const url = String(item.public_url || '');
                const title = String(item.original_name || item.stored_name || url);
                const active = url !== '' && url === currentUrl;
                return `
                    <button class="media-picker-item ${active ? 'is-selected' : ''}" type="button" data-page-media-choice="${escapeHtml(url)}" data-page-media-alt-value="${escapeHtml(item.alt_text || '')}">
                        <span class="media-picker-thumb">${url ? `<img src="${escapeHtml(url)}" alt="">` : ''}</span>
                        <span class="media-picker-name">${escapeHtml(title)}</span>
                    </button>
                `;
            })
            .join('') || `<div class="media-picker-empty">${escapeHtml(this.labels.mediaNoFiles)}</div>`;
    },

    async fetchMediaItems() {
        const url = String(this.media.urls?.list || '');
        if (url === '') {
            return [];
        }

        try {
            const response = await (window.NixCMS?.fetch || fetch)(url);
            const data = await response.json();
            return Array.isArray(data?.rows) ? data.rows : [];
        } catch (error) {
            return [];
        }
    },

    mediaAcceptAttribute() {
        const extensions = Array.isArray(this.media.acceptedExtensions) ? this.media.acceptedExtensions : [];
        const imageExtensions = extensions.filter((extension) => ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(String(extension).toLowerCase()));
        return imageExtensions.length > 0 ? imageExtensions.map((extension) => `.${extension}`).join(',') : 'image/*';
    },

};
