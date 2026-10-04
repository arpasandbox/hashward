function copyInviteInput(input) {
    if (!input) return;
    input.select();
    input.setSelectionRange(0, input.value.length);
    navigator.clipboard.writeText(input.value).catch(() => {});
}

export function initInvitesPage() {
    const inviteLinkDialog = document.getElementById('invite-link-dialog');
    const inviteLinkInput = document.getElementById('invite-link-modal-input');
    const inviteLinkError = document.getElementById('invite-link-dialog-error');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    document.getElementById('copy-invite-url')?.addEventListener('click', () => {
        copyInviteInput(document.getElementById('invite-url'));
    });

    document.getElementById('invite-link-dialog-close')?.addEventListener('click', () => {
        inviteLinkDialog?.close();
    });

    document.getElementById('invite-link-modal-copy')?.addEventListener('click', () => {
        copyInviteInput(inviteLinkInput);
    });

    document.querySelectorAll('.invite-view-link-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            const endpoint = button.dataset.linkEndpoint;
            if (!endpoint || !inviteLinkDialog) return;

            inviteLinkError.hidden = true;
            inviteLinkError.textContent = '';
            button.disabled = true;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    credentials: 'same-origin',
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    if (inviteLinkInput) {
                        inviteLinkInput.value = '';
                    }
                    inviteLinkError.textContent = data.message || 'Could not load invite link.';
                    inviteLinkError.hidden = false;
                    inviteLinkDialog.showModal();
                    return;
                }

                inviteLinkError.hidden = true;
                inviteLinkError.textContent = '';
                if (inviteLinkInput) {
                    inviteLinkInput.value = data.url || '';
                }
                inviteLinkDialog.showModal();
            } catch {
                if (inviteLinkInput) {
                    inviteLinkInput.value = '';
                }
                inviteLinkError.textContent = 'Could not load invite link.';
                inviteLinkError.hidden = false;
                inviteLinkDialog.showModal();
            } finally {
                button.disabled = false;
            }
        });
    });
}
