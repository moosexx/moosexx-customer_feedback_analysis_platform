document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    const profileForm = document.getElementById('profileForm');
    const messageDiv = document.getElementById('message');

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
                    showMessage(result.message);
                    setTimeout(() => window.location.href = 'login.php', 2000);
                } else {
                    showMessage(result.message || 'Registration failed', true);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('Network error or server is offline.', true);
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
                    showMessage(result.message);
                    setTimeout(() => window.location.href = 'profile.php', 1000);
                } else {
                    showMessage(result.message || 'Login failed', true);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('Network error or server is offline.', true);
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
                    showMessage(result.message);
                } else {
                    showMessage(result.message || 'Saving profile failed', true);
                }
            } catch (error) {
                showMessage('An error occurred. Please try again.', true);
            }
        });
    }
});
