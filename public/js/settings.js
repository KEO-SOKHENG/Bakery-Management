/* Bakery Management System - Settings Page JavaScript Interactivity */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Toast Notification Helper
    let toast = document.getElementById('settings_toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.id = 'settings_toast';
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

    // 2. Intercept All Settings Forms for Clean AJAX Submission & Feedback
    const settingsForms = document.querySelectorAll('.settings-form');
    settingsForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const actionUrl = this.getAttribute('action') || '/admin/settings/update';
            const formData = new FormData(this);
            const cardTitle = this.closest('.card, .settings-glass-card')?.querySelector('.card-title, .card-title-text h3')?.textContent?.trim() || 'Settings';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

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
                showToast(`${cardTitle} updated successfully!`);
                if (data.avatar_url || data.logo_url) {
                    const newUrl = data.avatar_url || data.logo_url;
                    const pill = document.getElementById('user_profile_pill');
                    if (pill) {
                        pill.innerHTML = `<img src="${newUrl}" alt="Avatar" class="user-avatar-circle-img" id="header_user_avatar_img">`;
                    }
                    const dropdownAvatar = document.querySelector('.user-dropdown-header img, .user-dropdown-avatar-wrap img, .user-dropdown-header div');
                    if (dropdownAvatar) {
                        dropdownAvatar.outerHTML = `<img src="${newUrl}" alt="User" class="user-dropdown-avatar-img">`;
                    }
                }
                if (data.user_name) {
                    const nameEls = document.querySelectorAll('.user-dropdown-name');
                    nameEls.forEach(el => el.textContent = data.user_name);
                }
                if (data.user_email) {
                    const emailEls = document.querySelectorAll('.user-dropdown-email');
                    emailEls.forEach(el => {
                        el.textContent = data.user_email;
                        el.setAttribute('title', data.user_email);
                    });
                }
                if (formData.has('language') && window.applyLanguage) {
                    window.applyLanguage(formData.get('language'));
                }
            })
            .catch(err => {
                showToast(err.message || `Failed to save ${cardTitle}`);
            });
        });
    });

    // 4. Logo Upload Live Image Preview
    const logoFileInput = document.getElementById('shop_logo_input');
    const logoPreviewBox = document.getElementById('logo_preview_box');

    if (logoFileInput && logoPreviewBox) {
        logoFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    logoPreviewBox.innerHTML = `<img src="${event.target.result}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" alt="Avatar / Logo">`;
                    showToast('New logo selected! Click Save Changes to apply.');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 5. iOS Segmented Control (Light/Dark Theme Picker)
    const currentTheme = localStorage.getItem('bakery_theme') || 'light';
    const segmentedButtons = document.querySelectorAll('.segmented-option');
    segmentedButtons.forEach(btn => {
        if (btn.getAttribute('data-theme') === currentTheme) {
            btn.classList.add('active');
        }
        btn.addEventListener('click', function() {
            segmentedButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const theme = this.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('bakery_theme', theme);
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
            }).catch(e => {});
        });
    });

    // 6. Notifications Toggle Switch Feedback
    const notifSwitch = document.getElementById('notifications_toggle');
    if (notifSwitch) {
        notifSwitch.addEventListener('change', function() {
            const status = this.checked ? 'ON' : 'OFF';
            showToast(`System notifications turned ${status}`);
        });
    }

    // 7. Profile Password Accordion Toggle
    const pwdToggleBtn = document.getElementById('btn_toggle_profile_pwd');
    const pwdFields = document.getElementById('profile_pwd_fields');
    const pwdToggleText = document.getElementById('pwd_toggle_text');
    const pwdToggleArrow = document.getElementById('pwd_toggle_arrow');

    if (pwdToggleBtn && pwdFields) {
        pwdToggleBtn.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT') return;
            const isHidden = pwdFields.style.display === 'none' || !pwdFields.style.display;
            pwdFields.style.display = isHidden ? 'block' : 'none';
            if (pwdToggleText) pwdToggleText.textContent = isHidden ? 'Cancel' : 'Set Password';
            if (pwdToggleArrow) pwdToggleArrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
        });
    }

    // 8. Hash Anchor Smooth Scroll & Highlight (#profile_info)
    function handleProfileHash() {
        const hash = window.location.hash;
        if (hash === '#profile_info' || hash === '#profile') {
            const profileCard = document.getElementById('profile_info');
            if (profileCard) {
                profileCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                profileCard.classList.remove('highlight-card');
                void profileCard.offsetWidth; // Trigger reflow for animation
                profileCard.classList.add('highlight-card');
                setTimeout(() => {
                    profileCard.classList.remove('highlight-card');
                }, 3500);
            }
        }
    }

    handleProfileHash();
    window.addEventListener('hashchange', handleProfileHash);
});

