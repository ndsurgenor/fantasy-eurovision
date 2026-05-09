document.addEventListener('DOMContentLoaded', () => {
    const fileInput       = document.getElementById('avatar');
    const avatarImg       = document.getElementById('avatar-img');
    const placeholder     = document.getElementById('avatar-placeholder');
    const addControls     = document.getElementById('add-controls');
    const previewControls = document.getElementById('preview-controls');
    const clearBtn        = document.getElementById('clear-preview-btn');

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = e => {
                if (avatarImg) {
                    avatarImg.src = e.target.result;
                    avatarImg.classList.remove('hidden');
                }
                if (placeholder)     placeholder.classList.add('hidden');
                if (addControls)     addControls.classList.add('hidden');
                if (previewControls) previewControls.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            fileInput.value = '';
            if (avatarImg)       { avatarImg.src = ''; avatarImg.classList.add('hidden'); }
            if (placeholder)     placeholder.classList.remove('hidden');
            if (addControls)     addControls.classList.remove('hidden');
            if (previewControls) previewControls.classList.add('hidden');
        });
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
