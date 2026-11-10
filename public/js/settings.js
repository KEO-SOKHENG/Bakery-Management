const SETTINGS_TOAST_ID = 'settings_toast';
document.addEventListener('DOMContentLoaded', function() {
    // ── Toast Notification Helper ──
    let toast = document.getElementById(SETTINGS_TOAST_ID);
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.id = SETTINGS_TOAST_ID;
        toast.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span id="toast_message">Settings saved successfully!</span>
        `;
        document.body.appendChild(toast);
    }

    function showToast(message) {
        const toastMsg = document.getElementById('toast_message');
        if (toastMsg) toastMsg.textContent = message;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
    window.showToast = showToast;

    // ── Top Auto-Save Status Badge ──
    const autoSaveBadge = document.getElementById('settings_autosave_badge');
    let badgeTimeout = null;

    function setAutoSaveStatus(status, text) {
        if (!autoSaveBadge) return;
        clearTimeout(badgeTimeout);

        if (status === 'saving') {
            autoSaveBadge.style.opacity = '1';
            autoSaveBadge.style.background = 'rgba(216, 155, 85, 0.15)';
            autoSaveBadge.style.color = 'var(--ios-accent, #d89b55)';
            autoSaveBadge.innerHTML = `
                <svg class="spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                <span>Saving...</span>
            `;
        } else if (status === 'saved') {
            autoSaveBadge.style.opacity = '1';
            autoSaveBadge.style.background = 'rgba(16, 185, 129, 0.15)';
            autoSaveBadge.style.color = '#10b981';
            autoSaveBadge.innerHTML = `
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span>${text || 'Saved automatically'}</span>
            `;
            badgeTimeout = setTimeout(() => {
                autoSaveBadge.style.opacity = '0';
            }, 2500);
        } else if (status === 'error') {
            autoSaveBadge.style.opacity = '1';
            autoSaveBadge.style.background = 'rgba(239, 68, 68, 0.15)';
            autoSaveBadge.style.color = '#ef4444';
            autoSaveBadge.innerHTML = `
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>${text || 'Failed to save'}</span>
            `;
            badgeTimeout = setTimeout(() => {
                autoSaveBadge.style.opacity = '0';
            }, 3500);
        }
    }

    // ── Auto-Save Engine ──
    const debounceTimers = {};

    function performFormAutoSave(form, label) {
        if (!form) return;
        const formId = form.id || 'default_form';
        if (debounceTimers[formId]) {
            clearTimeout(debounceTimers[formId]);
            delete debounceTimers[formId];
        }

        const actionUrl = form.getAttribute('action') || '/admin/settings/update';
        const formData = new FormData(form);

        // Ensure unchecked checkboxes send "0"
        form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (!cb.checked && cb.name) {
                formData.set(cb.name, '0');
            }
        });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        setAutoSaveStatus('saving');

        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const errorMsg = data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Validation failed.');
                throw new Error(errorMsg);
            }
            return data;
        })
        .then(data => {
            setAutoSaveStatus('saved', label ? `${label} saved` : 'Saved automatically');

            // Handle avatar URL only — null means "unchanged", '' means "removed".
            // The bakery logo must never be mirrored into the user avatar pill.
            if (data.avatar_url !== undefined && data.avatar_url !== null) {
                const newUrl = data.avatar_url;
                if (newUrl) {
                    if (logoPreviewBox) {
                        logoPreviewBox.innerHTML = `<img src="${newUrl}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" alt="Avatar / Logo">`;
                    }
                    if (resetLogoBtn) {
                        resetLogoBtn.style.display = 'inline-flex';
                    }
                } else {
                    if (logoPreviewBox) {
                        logoPreviewBox.innerHTML = `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;
                    }
                    if (resetLogoBtn) {
                        resetLogoBtn.style.display = 'none';
                    }
                }
                const pill = document.getElementById('user_profile_pill');
                if (pill) {
                    pill.innerHTML = newUrl 
                        ? `<img src="${newUrl}" alt="Avatar" class="user-avatar-circle-img" id="header_user_avatar_img">`
                        : `<div class="user-avatar-circle" id="header_user_avatar_default"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>`;
                }
                const dropdownAvatar = document.querySelector('.user-dropdown-header img, .user-dropdown-avatar-wrap img, .user-dropdown-header div');
                if (dropdownAvatar && newUrl) {
                    dropdownAvatar.outerHTML = `<img src="${newUrl}" alt="User" class="user-dropdown-avatar-img">`;
                }
            }

            if (data.user_name) {
                document.querySelectorAll('.user-dropdown-name').forEach(el => el.textContent = data.user_name);
            }
            if (data.user_email) {
                document.querySelectorAll('.user-dropdown-email').forEach(el => {
                    el.textContent = data.user_email;
                    el.setAttribute('title', data.user_email);
                });
            }
            if (formData.has('language') && window.applyLanguage) {
                window.applyLanguage(formData.get('language'));
            }
        })
        .catch(err => {
            setAutoSaveStatus('error', err.message || 'Auto-save failed');
            showToast(err.message || 'Auto-save failed');
        });
    }

    function triggerDebouncedAutoSave(form, label) {
        if (!form) return;
        const formId = form.id || 'default_form';
        if (debounceTimers[formId]) {
            clearTimeout(debounceTimers[formId]);
        }
        debounceTimers[formId] = setTimeout(() => {
            performFormAutoSave(form, label);
        }, 600);
    }

    // ── Wire Auto-Save to Secondary Settings Forms (excluding Bakery, Business & Password) ──
    const autoSaveForms = document.querySelectorAll('.settings-form:not(#form_security):not(#form_bakery):not(#form_business):not(#form_profile_business)');
    autoSaveForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            performFormAutoSave(this);
        });

        // Text, number inputs and textareas (debounced input, immediate blur)
        const textInputs = form.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="password"]), textarea');
        textInputs.forEach(input => {
            input.addEventListener('input', () => {
                triggerDebouncedAutoSave(form);
            });
            input.addEventListener('blur', () => {
                const formId = form.id || 'default_form';
                if (debounceTimers[formId]) {
                    performFormAutoSave(form);
                }
            });
        });

        // Selects, checkboxes, time inputs (immediate change)
        const immediateInputs = form.querySelectorAll('select, input[type="checkbox"], input[type="time"]');
        immediateInputs.forEach(input => {
            input.addEventListener('change', () => {
                performFormAutoSave(form);
            });
        });
    });

    // ── Profile & Business Unified Form: Bakery Logo, Admin Avatar & Explicit Save ──
    const bakeryLogoInput = document.getElementById('bakery_logo_file_input');
    const bakeryLogoBox = document.getElementById('bakery_logo_preview_box') || document.getElementById('bakery_profile_logo_box');
    const bakeryLogoText = document.getElementById('btn_change_logo_text');
    const bakeryResetLogoBtn = document.getElementById('btn_reset_bakery_logo');
    const bakeryRemoveLogoInput = document.getElementById('input_remove_bakery_logo');

    const adminAvatarInput = document.getElementById('admin_avatar_file_input');
    const adminAvatarBox = document.getElementById('admin_avatar_preview_box');
    const adminAvatarText = document.getElementById('btn_change_avatar_text');
    const adminResetAvatarBtn = document.getElementById('btn_reset_admin_avatar');
    const adminRemoveAvatarInput = document.getElementById('input_remove_admin_avatar');

    const bakeryShopNameInput = document.getElementById('input_shop_name');
    const bakeryProfileDisplayName = document.getElementById('bakery_profile_display_name');
    const formProfileBusiness = document.getElementById('form_profile_business') || document.getElementById('form_bakery');
    const btnSaveProfileBusiness = document.getElementById('btn_save_profile_business') || document.getElementById('btn_save_bakery');
    const profileBusinessSaveStatus = document.getElementById('profile_business_save_status') || document.getElementById('bakery_save_status');

    // Live display name synchronisation
    if (bakeryShopNameInput && bakeryProfileDisplayName) {
        bakeryShopNameInput.addEventListener('input', function() {
            bakeryProfileDisplayName.textContent = this.value.trim() || 'Sweet Delights Bakery';
        });
    }

    // 1. Bakery Logo file picker and preview
    if (bakeryLogoInput) {
        bakeryLogoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    showToast('Bakery logo file is too large! Maximum image size is 2MB.');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    if (bakeryLogoBox) {
                        bakeryLogoBox.innerHTML = `<img src="${event.target.result}" alt="Bakery Logo" id="bakery_logo_img">`;
                    }
                    if (bakeryLogoText) {
                        bakeryLogoText.textContent = file.name.length > 16 ? file.name.substring(0, 13) + '...' : file.name;
                    }
                    if (bakeryResetLogoBtn) {
                        bakeryResetLogoBtn.style.display = 'inline-flex';
                    }
                    if (bakeryRemoveLogoInput) {
                        bakeryRemoveLogoInput.value = '0';
                    }
                    if (adminRemoveAvatarInput) {
                        adminRemoveAvatarInput.value = '0';
                    }
                };
                reader.readAsDataURL(file);
                // One picker feeds both the bakery logo and the account avatar
                if (adminAvatarInput && window.DataTransfer) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    adminAvatarInput.files = dt.files;
                }
            }
        });
    }

    // Bakery Logo reset / remove
    if (bakeryResetLogoBtn) {
        bakeryResetLogoBtn.addEventListener('click', function() {
            if (bakeryLogoInput) {
                bakeryLogoInput.value = '';
            }
            if (bakeryRemoveLogoInput) {
                bakeryRemoveLogoInput.value = '1';
            }
            if (adminAvatarInput) {
                adminAvatarInput.value = '';
            }
            if (adminRemoveAvatarInput) {
                adminRemoveAvatarInput.value = '1';
            }
            const resetPill = document.getElementById('user_profile_pill');
            if (resetPill) {
                resetPill.innerHTML = `<div class="user-avatar-circle" id="header_user_avatar_default"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>`;
            }
            if (bakeryLogoBox) {
                bakeryLogoBox.innerHTML = `
                    <div class="profile-avatar-placeholder" id="bakery_logo_placeholder">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </div>`;
            }
            if (bakeryLogoText) {
                bakeryLogoText.textContent = 'Upload Photo';
            }
            bakeryResetLogoBtn.style.display = 'none';
        });
    }

    // 2. Admin Avatar file picker and preview
    if (adminAvatarInput) {
        adminAvatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    showToast('Profile photo is too large! Maximum image size is 2MB.');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    if (adminAvatarBox) {
                        adminAvatarBox.innerHTML = `<img src="${event.target.result}" alt="Admin Avatar" id="admin_avatar_img">`;
                    }
                    if (adminAvatarText) {
                        adminAvatarText.textContent = file.name.length > 16 ? file.name.substring(0, 13) + '...' : file.name;
                    }
                    if (adminResetAvatarBtn) {
                        adminResetAvatarBtn.style.display = 'inline-flex';
                    }
                    if (adminRemoveAvatarInput) {
                        adminRemoveAvatarInput.value = '0';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Admin Avatar reset / remove
    if (adminResetAvatarBtn) {
        adminResetAvatarBtn.addEventListener('click', function() {
            if (adminAvatarInput) {
                adminAvatarInput.value = '';
            }
            if (adminRemoveAvatarInput) {
                adminRemoveAvatarInput.value = '1';
            }
            if (adminAvatarBox) {
                adminAvatarBox.innerHTML = `
                    <div class="profile-avatar-placeholder" id="admin_avatar_placeholder">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>`;
            }
            if (adminAvatarText) {
                adminAvatarText.textContent = 'Upload Photo';
            }
            adminResetAvatarBtn.style.display = 'none';
        });
    }

    // 3. Save Profile & Business Form
    if (formProfileBusiness) {
        formProfileBusiness.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                this.reportValidity();
                return;
            }

            // Password change is optional: only validated when any password field is filled
            const pwCurrent = this.querySelector('#input_current_password');
            const pwNew = this.querySelector('#input_new_password');
            const pwConfirm = this.querySelector('#input_new_password_confirmation');
            if (pwCurrent && pwNew && pwConfirm && (pwCurrent.value || pwNew.value || pwConfirm.value)) {
                let pwError = '';
                if (!pwCurrent.value) pwError = 'Enter your current password to change it.';
                else if (pwNew.value.length < 6) pwError = 'New password must be at least 6 characters.';
                else if (pwNew.value !== pwConfirm.value) pwError = 'New password and confirmation do not match.';
                if (pwError) {
                    showToast(pwError);
                    (pwError.startsWith('Enter') ? pwCurrent : (pwError.includes('6') ? pwNew : pwConfirm)).focus();
                    return;
                }
            }

            const submitBtn = btnSaveProfileBusiness || this.querySelector('button[type="submit"]');
            const spinner = submitBtn ? submitBtn.querySelector('.btn-spinner') : null;
            const btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
            const originalText = btnText ? btnText.textContent : 'Save Changes';

            if (submitBtn) {
                submitBtn.disabled = true;
                if (spinner) spinner.style.display = 'inline-block';
                if (btnText) btnText.textContent = 'Saving...';
            }

            if (profileBusinessSaveStatus) {
                profileBusinessSaveStatus.className = 'profile-business-save-status';
                profileBusinessSaveStatus.textContent = '';
            }

            const formData = new FormData(this);

            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to save profile & business settings');
                    throw new Error(msg);
                }
                return data;
            })
            .then(data => {
                if (profileBusinessSaveStatus) {
                    profileBusinessSaveStatus.className = 'profile-business-save-status visible';
                    profileBusinessSaveStatus.innerHTML = `
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Saved successfully</span>`;
                    setTimeout(() => {
                        profileBusinessSaveStatus.classList.remove('visible');
                    }, 3500);
                }

                showToast('Profile & business settings saved successfully');

                // Clear the optional password fields after a successful save
                ['#input_current_password', '#input_new_password', '#input_new_password_confirmation'].forEach(sel => {
                    const input = formProfileBusiness.querySelector(sel);
                    if (input) input.value = '';
                });

                // Update bakery logo only if it changed (null = unchanged, '' = removed)
                if (data.logo_url !== undefined && data.logo_url !== null && bakeryLogoBox) {
                    if (data.logo_url) {
                        bakeryLogoBox.innerHTML = `<img src="${data.logo_url}" alt="Bakery Logo" id="bakery_logo_img">`;
                        if (bakeryResetLogoBtn) bakeryResetLogoBtn.style.display = 'inline-flex';
                        if (bakeryLogoText) bakeryLogoText.textContent = 'Change Photo';
                    } else {
                        bakeryLogoBox.innerHTML = `
                            <div class="profile-avatar-placeholder" id="bakery_logo_placeholder">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                            </div>`;
                        if (bakeryResetLogoBtn) bakeryResetLogoBtn.style.display = 'none';
                        if (bakeryLogoText) bakeryLogoText.textContent = 'Upload Photo';
                    }
                }

                // Update admin avatar only if it changed (null = unchanged, '' = removed)
                if (data.avatar_url !== undefined && data.avatar_url !== null) {
                    if (data.avatar_url) {
                        if (adminAvatarBox) adminAvatarBox.innerHTML = `<img src="${data.avatar_url}" alt="Admin Avatar" id="admin_avatar_img">`;
                        if (adminResetAvatarBtn) adminResetAvatarBtn.style.display = 'inline-flex';
                        if (adminAvatarText) adminAvatarText.textContent = 'Change Photo';

                        // Sync with top navbar pill
                        const pill = document.getElementById('user_profile_pill');
                        if (pill) {
                            pill.innerHTML = `<img src="${data.avatar_url}" alt="Avatar" class="user-avatar-circle-img" id="header_user_avatar_img">`;
                        }
                    } else {
                        if (adminAvatarBox) adminAvatarBox.innerHTML = `
                            <div class="profile-avatar-placeholder" id="admin_avatar_placeholder">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>`;
                        if (adminResetAvatarBtn) adminResetAvatarBtn.style.display = 'none';
                        if (adminAvatarText) adminAvatarText.textContent = 'Upload Photo';

                        const pill = document.getElementById('user_profile_pill');
                        if (pill) {
                            pill.innerHTML = `<div class="user-avatar-circle" id="header_user_avatar_default"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>`;
                        }
                    }
                }

                // Clear pending file/remove state so a repeat save does not re-upload
                if (bakeryLogoInput) bakeryLogoInput.value = '';
                if (adminAvatarInput) adminAvatarInput.value = '';
                if (bakeryRemoveLogoInput) bakeryRemoveLogoInput.value = '0';
                if (adminRemoveAvatarInput) adminRemoveAvatarInput.value = '0';

                if (data.shop_name && bakeryProfileDisplayName) {
                    bakeryProfileDisplayName.textContent = data.shop_name;
                }
                if (data.user_name) {
                    document.querySelectorAll('.user-dropdown-name').forEach(el => el.textContent = data.user_name);
                }
                if (data.user_email) {
                    document.querySelectorAll('.user-dropdown-email').forEach(el => {
                        el.textContent = data.user_email;
                        el.setAttribute('title', data.user_email);
                    });
                }
            })
            .catch(err => {
                if (profileBusinessSaveStatus) {
                    profileBusinessSaveStatus.className = 'profile-business-save-status visible error';
                    profileBusinessSaveStatus.innerHTML = `
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>${err.message || 'Error saving changes'}</span>`;
                    setTimeout(() => {
                        profileBusinessSaveStatus.classList.remove('visible');
                    }, 4000);
                }
                showToast(err.message || 'Error saving changes');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (spinner) spinner.style.display = 'none';
                    if (btnText) btnText.textContent = originalText;
                }
            });
        });
    }

    // Business Settings Form Save Button
    const formBusiness = document.getElementById('form_business');
    if (formBusiness) {
        formBusiness.addEventListener('submit', function(e) {
            e.preventDefault();
            const btnSave = document.getElementById('btn_save_business');
            const bizSpinner = btnSave ? btnSave.querySelector('.btn-spinner') : null;
            const bizText = btnSave ? btnSave.querySelector('.btn-text') : null;
            const bizStatus = document.getElementById('business_save_status');
            const originalText = bizText ? bizText.textContent : 'Save Business Settings';
            if (btnSave) {
                btnSave.disabled = true;
                if (bizSpinner) bizSpinner.style.display = 'inline-block';
                if (bizText) bizText.textContent = 'Saving...';
            }
            if (bizStatus) { bizStatus.className = 'profile-business-save-status'; bizStatus.textContent = ''; }

            const formData = new FormData(this);
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to save business settings');
                }
                return data;
            })
            .then(() => {
                showToast('Business settings saved successfully');
                setAutoSaveStatus('saved', 'Business settings saved');
                if (bizStatus) {
                    bizStatus.className = 'profile-business-save-status visible';
                    bizStatus.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Saved successfully</span>';
                    setTimeout(() => bizStatus.classList.remove('visible'), 3500);
                }
            })
            .catch(err => {
                showToast(err.message || 'Failed to save business settings');
                setAutoSaveStatus('error', err.message || 'Error saving');
                if (bizStatus) {
                    bizStatus.className = 'profile-business-save-status visible error';
                    bizStatus.textContent = err.message || 'Error saving changes';
                    setTimeout(() => bizStatus.classList.remove('visible'), 4000);
                }
            })
            .finally(() => {
                if (btnSave) {
                    btnSave.disabled = false;
                    if (bizSpinner) bizSpinner.style.display = 'none';
                    if (bizText) bizText.textContent = originalText;
                }
            });
        });
    }

    // ── Password Form (Manual Submit with Confirmation) ──
    const securityForm = document.getElementById('form_security');
    if (securityForm) {
        securityForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const actionUrl = this.getAttribute('action') || '/admin/settings/update';
            const formData = new FormData(this);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            setAutoSaveStatus('saving');

            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const errorMsg = data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Password update failed.');
                    throw new Error(errorMsg);
                }
                return data;
            })
            .then(data => {
                setAutoSaveStatus('saved', 'Password updated');
                showToast(data.message || 'Password updated successfully!');
                securityForm.reset();
            })
            .catch(err => {
                setAutoSaveStatus('error', err.message || 'Password update failed');
                showToast(err.message || 'Password update failed');
            });
        });
    }

    // ── Logo Upload Live Image Preview & Immediate Auto-Save ──
    const logoFileInput = document.getElementById('shop_logo_input');
    const logoPreviewBox = document.getElementById('logo_preview_box');
    const logoLabel = document.getElementById('btn_upload_logo_text');
    const resetLogoBtn = document.getElementById('btn_reset_default_logo');
    const removeLogoInput = document.getElementById('input_remove_shop_logo');

    if (logoFileInput) {
        logoFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    showToast('File is too large! Maximum image size is 2MB.');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    if (logoPreviewBox) {
                        logoPreviewBox.innerHTML = `<img src="${event.target.result}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" alt="Avatar / Logo">`;
                    }
                    if (logoLabel) {
                        logoLabel.textContent = file.name.length > 18 ? file.name.substring(0, 15) + '...' : file.name;
                    }
                    if (resetLogoBtn) {
                        resetLogoBtn.style.display = 'inline-flex';
                    }
                    if (removeLogoInput) {
                        removeLogoInput.value = '0';
                    }

                    // Auto-save the new logo immediately
                    const shopForm = document.getElementById('form_shop_info');
                    if (shopForm) {
                        performFormAutoSave(shopForm, 'Logo updated');
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (resetLogoBtn) {
        resetLogoBtn.addEventListener('click', function() {
            if (logoFileInput) {
                logoFileInput.value = '';
            }
            if (removeLogoInput) {
                removeLogoInput.value = '1';
            }
            if (logoPreviewBox) {
                logoPreviewBox.innerHTML = `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;
            }
            if (logoLabel) {
                logoLabel.textContent = 'Upload Logo Image';
            }
            resetLogoBtn.style.display = 'none';

            // Auto-save the reset immediately
            const shopForm = document.getElementById('form_shop_info');
            if (shopForm) {
                performFormAutoSave(shopForm, 'Logo reset to default');
            }
        });
    }

    // ── Backup & Restore: explicit confirmation before restoring ──
    (function initRestoreConfirm() {
        const form = document.getElementById('form_restore');
        if (!form) return;
        const fileInput = document.getElementById('input_backup_file');
        const check = document.getElementById('checkbox_confirm_restore');
        const btn = document.getElementById('btn_restore_settings');
        const modal = document.getElementById('restore_confirm_modal');
        const nameEl = document.getElementById('restore_confirm_filename');
        const btnCancel = document.getElementById('btn_restore_cancel');
        const btnConfirm = document.getElementById('btn_restore_confirm');
        let confirmed = false;

        function sync() {
            if (btn) btn.disabled = !(fileInput && fileInput.files.length && check && check.checked);
        }
        if (fileInput) fileInput.addEventListener('change', sync);
        if (check) check.addEventListener('change', sync);
        sync();

        function closeModal() { if (modal) modal.hidden = true; }

        form.addEventListener('submit', function (e) {
            if (confirmed) return;
            e.preventDefault();
            if (!form.checkValidity()) { form.reportValidity(); return; }
            if (nameEl && fileInput.files[0]) nameEl.textContent = fileInput.files[0].name;
            if (modal) modal.hidden = false;
            if (btnConfirm) btnConfirm.focus();
        });
        if (btnCancel) btnCancel.addEventListener('click', closeModal);
        if (modal) modal.addEventListener('click', function (e) { if (e.target.getAttribute('data-close')) closeModal(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
        if (btnConfirm) btnConfirm.addEventListener('click', function () {
            confirmed = true;
            closeModal();
            form.submit();
        });
    })();

    // ── Settings Nav: liquid orange pill (same hover behaviour as the main sidebar) ──
    (function initSettingsNavPill() {
        const pane = document.getElementById('apple_nav_pane') || document.querySelector('.apple-nav-pane');
        if (!pane) return;
        pane.style.position = 'relative';
        pane.classList.add('has-liquid-pill');

        let pill = pane.querySelector('.apple-nav-pill');
        if (!pill) {
            pill = document.createElement('div');
            pill.className = 'apple-nav-pill';
            pane.prepend(pill);
        }

        const items = Array.from(pane.querySelectorAll('.apple-nav-item'));
        const activeItem = () => pane.querySelector('.apple-nav-item.active') || items[0];

        function moveTo(item, instant) {
            if (!item || !item.offsetWidth) { pill.style.opacity = '0'; return; }
            if (instant) pill.style.transition = 'none';
            pill.style.width = item.offsetWidth + 'px';
            pill.style.height = item.offsetHeight + 'px';
            pill.style.transform = `translate3d(${item.offsetLeft}px, ${item.offsetTop}px, 0)`;
            pill.style.opacity = '1';
            items.forEach(i => i.classList.toggle('pill-highlighted', i === item));
            if (instant) { void pill.offsetWidth; pill.style.transition = ''; }
        }

        items.forEach(item => {
            item.addEventListener('mouseenter', () => moveTo(item, false));
            item.addEventListener('focus', () => moveTo(item, false));
            item.addEventListener('click', () => requestAnimationFrame(() => moveTo(item, false)));
        });
        pane.addEventListener('mouseleave', () => moveTo(activeItem(), false));
        window.addEventListener('resize', () => moveTo(activeItem(), true));
        window.addEventListener('hashchange', () => setTimeout(() => moveTo(activeItem(), true), 50));
        requestAnimationFrame(() => moveTo(activeItem(), true));
        setTimeout(() => moveTo(activeItem(), true), 300);
    })();

    // ── Apple Segmented Control (Light / Dark Theme Picker) ──
    const currentTheme = localStorage.getItem('bakery_theme') || 'light';
    const segmentedButtons = document.querySelectorAll('.apple-segment-btn, .segmented-option');
    segmentedButtons.forEach(btn => {
        if (btn.getAttribute('data-theme-value') === currentTheme) {
            btn.classList.add('active');
        }
        btn.addEventListener('click', function() {
            segmentedButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const theme = this.getAttribute('data-theme-value');
            const hiddenThemeInput = document.getElementById('theme_mode_hidden_input');
            if (hiddenThemeInput) hiddenThemeInput.value = theme;

            if (typeof window.applyTheme === 'function') {
                window.applyTheme(theme);
            } else {
                document.documentElement.setAttribute('data-theme', theme);
                if (document.body) {
                    document.body.classList.toggle('dark-mode', theme === 'dark');
                    document.body.classList.toggle('theme-dark', theme === 'dark');
                }
                localStorage.setItem('bakery_theme', theme);
            }
            setAutoSaveStatus('saved', `Theme switched to ${theme.toUpperCase()}`);
            showToast(`Theme switched to ${theme.toUpperCase()} mode!`);

            // Sync with backend
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch('/admin/settings/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ theme_mode: theme })
            }).catch(() => {});
        });
    });

    // ── Apple Two-Panel Navigation & Tab Switching ──
    const shell = document.getElementById('apple_settings_shell') || document.getElementById('telegram_settings_shell');
    const navItems = document.querySelectorAll('.apple-nav-item, .telegram-item');
    const panels = document.querySelectorAll('.apple-panel, .telegram-panel');
    const mobileBackButtons = document.querySelectorAll('.apple-mobile-back-btn, #apple_mobile_back, .telegram-back-btn');

    function switchAppleTab(targetId, updateHash = true) {
        const targetItem = document.querySelector(`.apple-nav-item[data-target="${targetId}"], .telegram-item[data-target="${targetId}"]`);
        const targetPanel = document.getElementById(targetId);
        if (!targetItem || !targetPanel) return;

        // 1. Update navigation items active state
        navItems.forEach(item => {
            const isSelected = (item === targetItem);
            item.classList.toggle('active', isSelected);
            item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });

        // 2. Switch visible panel
        panels.forEach(p => p.classList.remove('active'));
        targetPanel.classList.add('active');

        // 3. Update URL hash
        if (updateHash) {
            const shortHash = targetId.replace('panel_', '');
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, null, `#${shortHash}`);
            } else {
                window.location.hash = shortHash;
            }
        }

        // 4. On mobile, transition to detail view
        if (shell) {
            shell.classList.add('show-detail');
        }

        // 5. Scroll detail body to top
        const detailBody = document.getElementById('apple_detail_body') || document.getElementById('telegram_detail_body');
        if (detailBody) {
            detailBody.scrollTop = 0;
        }

        // 6. Handle Storage panel polling activation/deactivation
        if (targetId === 'panel_storage') {
            if (!storagePollInterval) {
                storagePollInterval = setInterval(() => {
                    fetchStorageDiagnostics(false);
                }, 60000);
            }
        } else {
            if (storagePollInterval) {
                clearInterval(storagePollInterval);
                storagePollInterval = null;
            }
        }
    }

    // Attach click listeners to all nav items
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            switchAppleTab(targetId);
        });
    });

    // Mobile Back Button Handler
    mobileBackButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            if (shell) shell.classList.remove('show-detail');
        });
    });

    // ── Live Simulators (Receipt & Tax Preview) ──
    const receiptHeaderInput = document.getElementById('input_receipt_header');
    const receiptFooterInput = document.getElementById('input_receipt_footer');
    const receiptHeaderPreview = document.getElementById('receipt_preview_header');
    const receiptFooterPreview = document.getElementById('receipt_preview_footer');

    if (receiptHeaderInput && receiptHeaderPreview) {
        receiptHeaderInput.addEventListener('input', function() {
            receiptHeaderPreview.textContent = this.value || "Sweet Delights Bakery & Cafe\nFresh Daily Artisan Breads";
        });
    }

    if (receiptFooterInput && receiptFooterPreview) {
        receiptFooterInput.addEventListener('input', function() {
            receiptFooterPreview.textContent = this.value || "Thank you for visiting!\nFollow us @sweetdelights.bakery";
        });
    }

    // Live Tax & Discount Simulator
    const taxRateInput = document.getElementById('input_tax_rate');
    const discountInput = document.getElementById('input_discount');
    const simDiscountPct = document.getElementById('sim_discount_pct');
    const simDiscountVal = document.getElementById('sim_discount_val');
    const simNetVal = document.getElementById('sim_net_val');
    const simTaxPct = document.getElementById('sim_tax_pct');
    const simTaxVal = document.getElementById('sim_tax_val');
    const simTotalVal = document.getElementById('sim_total_val');

    function updateTaxSimulator() {
        if (!taxRateInput || !discountInput) return;
        const subtotal = 100.00;
        const discRate = Math.max(0, parseFloat(discountInput.value) || 0);
        const taxRate = Math.max(0, parseFloat(taxRateInput.value) || 0);

        const discAmount = (subtotal * discRate) / 100.00;
        const netAmount = Math.max(0, subtotal - discAmount);
        const taxAmount = (netAmount * taxRate) / 100.00;
        const total = netAmount + taxAmount;

        if (simDiscountPct) simDiscountPct.textContent = discRate.toFixed(1);
        if (simDiscountVal) simDiscountVal.textContent = discAmount.toFixed(2);
        if (simNetVal) simNetVal.textContent = netAmount.toFixed(2);
        if (simTaxPct) simTaxPct.textContent = taxRate.toFixed(1);
        if (simTaxVal) simTaxVal.textContent = taxAmount.toFixed(2);
        if (simTotalVal) simTotalVal.textContent = total.toFixed(2);
    }

    if (taxRateInput) taxRateInput.addEventListener('input', updateTaxSimulator);
    if (discountInput) discountInput.addEventListener('input', updateTaxSimulator);

    // ── Hash Routing & Initial Tab Activation ──
    // ── Production Storage & Media Assets Real-Time Refresh ──
    const btnRefreshStorage = document.getElementById('btn_refresh_storage');
    const storageSpinner = document.getElementById('storage_refresh_spinner');
    const btnRefreshText = document.getElementById('btn_refresh_storage_text');
    let storagePollInterval = null;

    async function fetchStorageDiagnostics(isManual = false) {
        if (!btnRefreshStorage) return;

        if (isManual) {
            if (storageSpinner) storageSpinner.classList.add('spin');
            if (btnRefreshText) btnRefreshText.textContent = 'Syncing...';
            btnRefreshStorage.disabled = true;
        }

        try {
            const url = '/admin/settings/storage-status' + (isManual ? '?refresh=1' : '');
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const data = await response.json();
            if (!data || !data.success) {
                throw new Error(data.message || 'Storage telemetry query failed.');
            }

            updateStorageUI(data);

            if (isManual) {
                showToast('Storage telemetry synchronized successfully!');
            }
        } catch (err) {
            console.warn('Storage diagnostics update failed:', err);
            renderStorageError();
            if (isManual) {
                showToast('Unable to synchronize storage data. Please retry.');
            }
        } finally {
            if (isManual) {
                if (storageSpinner) storageSpinner.classList.remove('spin');
                if (btnRefreshText) btnRefreshText.textContent = 'Sync Now';
                btnRefreshStorage.disabled = false;
            }
        }
    }

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function renderStorageError() {
        const panel = document.getElementById('storage_status_panel');
        if (panel) panel.className = 'storage-status-panel is-unavailable';
        setText('storage_status_headline', 'Data unavailable');
        setText('storage_status_subtitle', 'Could not reach the storage service. Please try again.');
        setText('storage_status_badge', 'Data unavailable');
    }

    function updateStorageUI(data) {
        const prov = data.provider || {};
        const conn = data.connection || {};
        const usage = data.usage || {};
        const disp = data.display || {};
        const usageOk = !!disp.usage_available;
        const recentFiles = (usage.recent_files || []).slice(0, 3);

        // Connection panel
        const state = disp.state || 'unavailable';
        const panel = document.getElementById('storage_status_panel');
        if (panel) panel.className = 'storage-status-panel is-' + state;
        const icons = {
            connected: '<polyline points="20 6 9 17 4 12"/>',
            disconnected: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            unavailable: '<line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
        };
        const iconBox = document.getElementById('storage_status_icon');
        if (iconBox) {
            iconBox.innerHTML = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">${icons[state] || icons.unavailable}</svg>`;
        }
        setText('storage_status_headline', disp.headline || 'Data unavailable');
        setText('storage_status_subtitle', disp.subtitle || 'Data unavailable');
        setText('storage_status_badge', disp.badge || 'Data unavailable');
        const detail = document.getElementById('storage_status_detail');
        if (detail) {
            detail.textContent = disp.detail || '';
            detail.hidden = !disp.detail;
        }

        // Stat cards (real values only)
        setText('kpi_total_files', usageOk ? Number(usage.total_files || 0).toLocaleString() : 'Data unavailable');
        setText('kpi_total_bytes', usageOk ? (usage.total_bytes_formatted || 'Data unavailable') : 'Data unavailable');
        if (data.synced_at_formatted) setText('storage_last_synced_text', 'Updated ' + data.synced_at_formatted);

        // Recent uploads (latest 3)
        const list = document.getElementById('storage_recent_list');
        if (list) {
            if (recentFiles.length > 0) {
                list.innerHTML = recentFiles.map(file => {
                    const isImage = String(file.mime_type || '').indexOf('image/') === 0 && file.url;
                    const thumb = isImage
                        ? `<img src="${escapeHtml(file.url)}" alt="" loading="lazy">`
                        : '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
                    const action = file.url
                        ? `<a href="${escapeHtml(file.url)}" target="_blank" rel="noopener noreferrer" class="storage-open-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><span>Open</span></a>`
                        : '<span class="storage-file-private">Private</span>';
                    return `<div class="storage-file-row">
                        <div class="storage-file-thumb">${thumb}</div>
                        <div class="storage-file-main"><span class="storage-file-name" title="${escapeHtml(file.path)}">${escapeHtml(file.name)}</span><span class="storage-file-tag">${escapeHtml(file.category)}</span></div>
                        <span class="storage-file-size">${escapeHtml(file.size_formatted)}</span>
                        <span class="storage-file-date">${escapeHtml(file.last_modified_formatted)}</span>
                        ${action}
                    </div>`;
                }).join('');
            } else {
                list.innerHTML = `<div class="storage-empty">${usageOk ? 'No files have been uploaded yet.' : 'Data unavailable'}</div>`;
            }
        }

        // Advanced details (verified config only; hostname only, never credentials)
        const code = v => `<code>${escapeHtml(v || 'Data unavailable')}</code>`;
        const setHtml = (id, html) => { const el = document.getElementById(id); if (el) el.innerHTML = html; };
        setText('storage_info_provider', prov.provider || 'Data unavailable');
        setHtml('storage_info_driver', code(prov.driver) + (prov.disk_name ? ` <span class="storage-adv-muted">(disk: ${escapeHtml(prov.disk_name)})</span>` : ''));
        setHtml('storage_info_bucket', code(prov.bucket));
        setHtml('storage_info_region', code(prov.region));
        setHtml('storage_info_endpoint', code(prov.endpoint_host));
        setHtml('storage_info_diag', escapeHtml((conn.status || 'Data unavailable') + (conn.latency_ms != null ? ' · ' + conn.latency_ms + ' ms' : '')) + (conn.message ? `<span class="storage-adv-note">${escapeHtml(conn.message)}</span>` : ''));
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    if (btnRefreshStorage) {
        btnRefreshStorage.addEventListener('click', function(e) {
            e.preventDefault();
            fetchStorageDiagnostics(true);
        });
    }

    function handleInitialHash() {
        const hash = window.location.hash.replace('#', '').toLowerCase();
        if (!hash) return;

        const hashMap = {
            // Profile & Business (Unified Navigation & Panel)
            'profile_business': 'panel_profile_business',
            'profile-business': 'panel_profile_business',

            // Legacy hash aliases & deep links for backwards compatibility
            'bakery': 'panel_profile_business',
            'account': 'panel_profile_business',
            'general': 'panel_profile_business',
            'shop': 'panel_profile_business',
            'shop_info': 'panel_profile_business',
            'profile': 'panel_profile_business',
            'profile_info': 'panel_profile_business',
            'personal_profile': 'panel_profile_business',
            'contact': 'panel_profile_business',
            'contact_info': 'panel_profile_business',
            'security': 'panel_profile_business',
            'security_info': 'panel_profile_business',
            'password': 'panel_profile_business',

            // Preferences & Management Panels
            'business': 'panel_business',
            'notifications': 'panel_notifications',
            'appearance': 'panel_appearance',
            'storage': 'panel_storage',
            'backup': 'panel_backup',

            // Other deep link aliases
            'preferences': 'panel_business',
            'system': 'panel_business',
            'operations': 'panel_business',
            'hours': 'panel_business',
            'tax': 'panel_business',
            'tax_discount': 'panel_business',
            'discount': 'panel_business',
            'receipt': 'panel_business',
            'alerts': 'panel_notifications',
            'display': 'panel_appearance',
            'theme': 'panel_appearance',
            'media': 'panel_storage',
            'files': 'panel_storage',
            'restore': 'panel_backup',
            'card_backup_restore': 'panel_backup'
        };

        const targetPanel = hashMap[hash];
        if (targetPanel) {
            switchAppleTab(targetPanel, false);
            const detailBody = document.getElementById('apple_detail_body') || document.getElementById('telegram_detail_body');
            const targetElId = (hash === 'profile_info' || hash === 'profile' || hash === 'personal_profile') ? 'profile_info'
                : (hash === 'security' || hash === 'password' || hash === 'security_info') ? 'security_info'
                : (hash === 'contact' || hash === 'contact_info') ? 'section_contact_info'
                : (hash === 'bakery' || hash === 'shop' || hash === 'section_bakery_profile') ? 'section_bakery_profile'
                : null;

            if (targetElId) {
                const el = document.getElementById(targetElId);
                if (el) {
                    if (detailBody) {
                        detailBody.scrollTop = el.offsetTop - 15;
                    } else {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    el.classList.add('highlight-card');
                    setTimeout(() => el.classList.remove('highlight-card'), 3000);
                }
            }
        }
    }

    handleInitialHash();
    window.addEventListener('hashchange', handleInitialHash);
});
