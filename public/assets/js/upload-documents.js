document.addEventListener('DOMContentLoaded', () => {
    const maxFileSize = 20 * 1024 * 1024;

    const formatBytes = (bytes) => {
        if (!bytes) {
            return '0 KB';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / (1024 ** index);

        return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
    };

    const pendingListFor = (input) => input
        ?.closest('[data-upload-documents]')
        ?.querySelector('[data-pending-list]');

    const emptyStateFor = (input) => input
        ?.closest('[data-upload-form], .upload-side')
        ?.querySelector('[data-empty-state]');

    const submitButtonFor = (input) => input
        ?.closest('[data-upload-form]')
        ?.querySelector('[data-submit-button]');

    const syncClassicUploadState = (input, hasValidFiles) => {
        const submitButton = submitButtonFor(input);
        const emptyState = emptyStateFor(input);

        if (submitButton) {
            submitButton.disabled = !hasValidFiles;
        }

        if (emptyState) {
            emptyState.hidden = hasValidFiles;
        }
    };

    const uploadErrorPanelFor = (input) => {
        const pendingList = pendingListFor(input);

        if (!pendingList) {
            return null;
        }

        let panel = pendingList.parentElement.querySelector('[data-client-upload-error]');

        if (!panel) {
            panel = document.createElement('div');
            panel.className = 'error-panel client-upload-error';
            panel.setAttribute('data-client-upload-error', '');
            panel.setAttribute('role', 'alert');
            pendingList.insertAdjacentElement('afterend', panel);
        }

        return panel;
    };

    const hideUploadError = (input) => {
        const panel = uploadErrorPanelFor(input);

        if (panel) {
            panel.hidden = true;
            panel.textContent = '';
        }
    };

    const showUploadError = (input, message) => {
        const panel = uploadErrorPanelFor(input);

        if (!panel) {
            return;
        }

        panel.hidden = false;
        panel.textContent = message;
    };

    const allowedExtensionsFor = (input) => {
        if (input.id === 'organic-documents') {
            return ['pdf'];
        }

        if (input.id === 'documents') {
            return ['xls', 'xlsx'];
        }

        return (input.accept || '')
            .split(',')
            .map((item) => item.trim().replace(/^\./, '').toLowerCase())
            .filter((item) => item && !item.includes('/'));
    };

    const validateSelectedFiles = (input) => {
        const files = Array.from(input.files || []);
        const allowedExtensions = allowedExtensionsFor(input);
        const invalidFiles = files.filter((file) => {
            const extension = (file.name.split('.').pop() || '').toLowerCase();

            return !allowedExtensions.includes(extension);
        });
        const oversizedFiles = files.filter((file) => file.size > maxFileSize);

        if (invalidFiles.length > 0) {
            const labels = allowedExtensions.map((extension) => extension.toUpperCase()).join(', ');
            showUploadError(input, `Formato no permitido. Solo puedes subir archivos: ${labels}.`);
            input.value = '';
            syncClassicUploadState(input, false);
            return false;
        }

        if (oversizedFiles.length > 0) {
            showUploadError(input, 'El archivo supera los 20 MB permitidos. Selecciona un archivo más liviano.');
            input.value = '';
            syncClassicUploadState(input, false);
            return false;
        }

        hideUploadError(input);
        syncClassicUploadState(input, files.length > 0);
        return true;
    };

    const createPendingRow = (file, index, removable = false) => {
        const row = document.createElement('li');
        const extension = file.name.split('.').pop() || 'file';
        const isExcel = ['xls', 'xlsx'].includes(extension.toLowerCase());

        row.className = 'file-row';
        row.innerHTML = `
            <span class="file-icon">${extension.slice(0, 4)}</span>
            <span class="file-meta">
                <span class="file-name">${file.name}</span>
                <span class="file-size" data-progress-label>${isExcel ? 'Subiendo Excel' : 'Subiendo archivo'} · ${formatBytes(file.size)}</span>
                <span class="progress-track">
                    <span class="progress-bar" style="--progress: 8%"></span>
                </span>
            </span>
            ${removable ? `
                <button class="remove-button" type="button" data-remove-file="${index}" aria-label="Eliminar ${file.name}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            ` : '<span></span>'}
        `;

        return row;
    };

    const renderPendingFiles = (input) => {
        const pendingList = pendingListFor(input);

        if (!pendingList) {
            return;
        }

        pendingList.innerHTML = '';

        Array.from(input.files || []).forEach((file, index) => {
            pendingList.appendChild(createPendingRow(file, index, Boolean(submitButtonFor(input))));
        });
    };

    const updateProgress = (input, progress) => {
        const pendingList = pendingListFor(input);

        if (!pendingList) {
            return;
        }

        pendingList.querySelectorAll('.progress-bar').forEach((bar) => {
            bar.style.setProperty('--progress', `${progress}%`);
        });

        pendingList.querySelectorAll('[data-progress-label]').forEach((label) => {
            const baseText = label.textContent.split(' · ').pop();
            label.textContent = `${progress >= 100 ? 'Carga completa' : `Subiendo ${progress}%`} · ${baseText}`;
        });
    };

    const clearPendingFiles = (input) => {
        const pendingList = pendingListFor(input);

        if (pendingList) {
            pendingList.innerHTML = '';
        }

        syncClassicUploadState(input, false);
    };

    const removeFileFromInput = (input, indexToRemove) => {
        const dataTransfer = new DataTransfer();

        Array.from(input.files || []).forEach((file, index) => {
            if (index !== indexToRemove) {
                dataTransfer.items.add(file);
            }
        });

        input.files = dataTransfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    document.addEventListener('dragover', (event) => {
        const dropzone = event.target.closest?.('[data-dropzone]');

        if (!dropzone) {
            return;
        }

        event.preventDefault();
        dropzone.classList.add('is-dragging');
    });

    document.addEventListener('dragleave', (event) => {
        event.target.closest?.('[data-dropzone]')?.classList.remove('is-dragging');
    });

    document.addEventListener('drop', (event) => {
        const dropzone = event.target.closest?.('[data-dropzone]');

        if (!dropzone) {
            return;
        }

        event.preventDefault();
        dropzone.classList.remove('is-dragging');

        const inputId = dropzone.getAttribute('for');
        const input = inputId ? document.getElementById(inputId) : null;

        if (!input || !event.dataTransfer?.files?.length) {
            return;
        }

        input.files = event.dataTransfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    document.addEventListener('change', (event) => {
        if (!event.target.matches?.('[data-file-input]')) {
            return;
        }

        if (!validateSelectedFiles(event.target)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            clearPendingFiles(event.target);
            return;
        }

        renderPendingFiles(event.target);
        updateProgress(event.target, 12);
    }, true);

    document.addEventListener('click', (event) => {
        const removeButton = event.target.closest?.('[data-remove-file]');

        if (!removeButton) {
            return;
        }

        const form = removeButton.closest('[data-upload-form]');
        const input = form?.querySelector('[data-file-input]');
        const index = Number(removeButton.dataset.removeFile);

        if (!input || Number.isNaN(index)) {
            return;
        }

        removeFileFromInput(input, index);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest?.('[data-upload-form]');

        if (!form) {
            return;
        }

        const submitButton = form.querySelector('[data-submit-button]');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando PDFs...';
        }
    });

    document.addEventListener('livewire-upload-start', (event) => {
        if (event.target.matches?.('[data-file-input]')) {
            updateProgress(event.target, 18);
        }
    });

    document.addEventListener('livewire-upload-progress', (event) => {
        if (event.target.matches?.('[data-file-input]')) {
            updateProgress(event.target, event.detail.progress);
        }
    });

    document.addEventListener('livewire-upload-finish', (event) => {
        if (!event.target.matches?.('[data-file-input]')) {
            return;
        }

        hideUploadError(event.target);
        updateProgress(event.target, 100);

        window.setTimeout(() => clearPendingFiles(event.target), 650);
    });

    document.addEventListener('livewire-upload-error', (event) => {
        if (!event.target.matches?.('[data-file-input]')) {
            return;
        }

        clearPendingFiles(event.target);
        showUploadError(
            event.target,
            'No se pudo completar la subida. Revisa que el archivo sea válido, pese 20 MB o menos y que PHP esté configurado para aceptar archivos de 20 MB.'
        );
    });
});
