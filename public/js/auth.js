document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    const profileForm = document.getElementById('profileForm');
    const messageDiv = document.getElementById('message');

    // Create notification popup element
    function createNotification() {
        const popup = document.createElement('div');
        popup.className = 'notification-popup';
        popup.innerHTML = `
            <svg class="notification-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span class="notification-message"></span>
            <button class="notification-close" aria-label="Close">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        `;
        document.body.appendChild(popup);
        
        // Close button functionality
        const closeBtn = popup.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => hideNotification());
        
        return popup;
    }

    function showNotification(msg, isError = false) {
        let popup = document.querySelector('.notification-popup');
        if (!popup) {
            popup = createNotification();
        }
        
        const messageEl = popup.querySelector('.notification-message');
        const iconEl = popup.querySelector('.notification-icon');
        
        messageEl.textContent = msg;
        
        if (isError) {
            popup.classList.add('error');
            iconEl.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            `;
        } else {
            popup.classList.remove('error');
            iconEl.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            `;
        }
        
        popup.classList.add('show');
        
        // Auto-hide after 4 seconds
        setTimeout(() => hideNotification(), 4000);
    }

    function hideNotification() {
        const popup = document.querySelector('.notification-popup');
        if (popup) {
            popup.classList.remove('show');
        }
    }

    function showMessage(msg, isError = false) {
        if (messageDiv) {
            messageDiv.textContent = msg;
            messageDiv.className = isError ? 'message error' : 'message success';
        }
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(registerForm);
            try {
                const response = await fetch('../php/register_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Server response was not JSON:', text);
                    showMessage('Server error: Invalid response format.', true);
                    return;
                }

                if (result.status === 'success') {
                    showNotification(result.message + ' Redirecting to login...');
                    setTimeout(() => window.location.href = 'login.php', 2000);
                } else {
                    showNotification(result.message || 'Registration failed', true);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showNotification('Network error or server is offline.', true);
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(loginForm);
            try {
                const response = await fetch('../php/login_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Server response was not JSON:', text);
                    showMessage('Server error: Invalid response format.', true);
                    return;
                }

                if (result.status === 'success') {
                    showNotification(result.message + ' Redirecting...');
                    const redirectUrl = result.redirect || 'dashboard.php';
                    setTimeout(() => window.location.href = redirectUrl, 1500);
                } else {
                    showNotification(result.message || 'Login failed', true);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showNotification('Network error or server is offline.', true);
            }
        });
    }
    
    if (profileForm) {
        // Fetch existing data
        fetch('../php/profile_action.php')
            .then(res => res.json())
            .then(result => {
                if (result.status === 'success' && result.data) {
                    document.getElementById('business_name').value = result.data.business_name;
                    document.getElementById('industry').value = result.data.industry;
                    document.getElementById('description').value = result.data.description;
                }
            });

        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(profileForm);
            try {
                const response = await fetch('../php/profile_action.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.status === 'success') {
                    showNotification(result.message + ' Redirecting to dashboard...');
                    setTimeout(() => window.location.href = 'dashboard.php', 1500);
                } else {
                    showNotification(result.message || 'Saving profile failed', true);
                }
            } catch (error) {
                showNotification('An error occurred. Please try again.', true);
            }
        });
    }
});
