document.addEventListener('DOMContentLoaded', () => {
    const fileInput       = document.getElementById('avatar');
    const avatarImg       = document.getElementById('avatar-img');
    const placeholder     = document.getElementById('avatar-placeholder');
    const addControls     = document.getElementById('add-controls');
    const previewControls = document.getElementById('preview-controls');
    const clearBtn        = document.getElementById('clear-preview-btn');

    function showPreview(url) {
        if (avatarImg) {
            avatarImg.src = url;
            avatarImg.style.display = 'block';
        }
        if (placeholder)     placeholder.style.display = 'none';
        if (addControls)     addControls.style.display = 'none';
        if (previewControls) previewControls.style.display = 'block';
    }

    function clearPreview() {
        fileInput.value = '';
        if (avatarImg) {
            URL.revokeObjectURL(avatarImg.src);
            avatarImg.src = '';
            avatarImg.style.display = 'none';
        }
        if (placeholder)     placeholder.style.display = '';
        if (addControls)     addControls.style.display = '';
        if (previewControls) previewControls.style.display = 'none';
    }

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) return;
            showPreview(URL.createObjectURL(file));
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', clearPreview);
    }

    const removeBtn     = document.getElementById('remove-avatar-btn');
    const removeFlag    = document.getElementById('remove-avatar-flag');
    const removeDialog  = document.getElementById('remove-avatar-dialog');
    const removeCancel  = document.getElementById('remove-avatar-cancel');
    const removeConfirm = document.getElementById('remove-avatar-confirm');

    if (removeBtn && removeDialog) {
        removeBtn.addEventListener('click', () => removeDialog.showModal());
        removeCancel.addEventListener('click', () => removeDialog.close());
        removeConfirm.addEventListener('click', () => {
            removeFlag.value = '1';
            removeFlag.closest('form').submit();
        });
        removeDialog.addEventListener('click', e => {
            if (e.target === removeDialog) removeDialog.close();
        });
    }
});
