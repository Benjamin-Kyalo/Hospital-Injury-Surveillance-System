// Small bits of client-side help. The server still checks everything.

document.addEventListener('DOMContentLoaded', function () {
    var passwordForm = document.querySelector('[data-password-form]');
    if (passwordForm) {
        var password = document.getElementById('password');
        var confirm = document.getElementById('confirm_password');

        function checkMatch() {
            if (!password || !confirm) return;
            if (confirm.value && password.value !== confirm.value) {
                confirm.setCustomValidity('Passwords do not match.');
            } else {
                confirm.setCustomValidity('');
            }
        }

        password.addEventListener('input', checkMatch);
        confirm.addEventListener('input', checkMatch);
    }

    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (event) {
            if (!window.confirm(element.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
            });
        });
    });
});
