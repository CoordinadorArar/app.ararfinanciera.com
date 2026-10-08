document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('password-confirm');

    function validarPassword(password) {
        const requisitos = {
            longitud: password.length >= 8,
            mayuscula: /[A-Z]/.test(password),
            minuscula: /[a-z]/.test(password),
            numero: /[0-9]/.test(password)
        };

        return {
            valida: requisitos.longitud && requisitos.mayuscula && requisitos.minuscula && requisitos.numero,
            requisitos: requisitos
        };
    }

    function actualizarRequisitos(requisitos) {
        document.querySelectorAll('.auth-requisitos [data-req]').forEach(li => {
            li.classList.toggle('is-ok', requisitos[li.dataset.req]);
        });
    }

    function mostrarErrores(input) {
        limpiarErrores(input);
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'password-errors invalid-feedback';
        errorDiv.innerHTML = '<i class="fas fa-circle-exclamation" aria-hidden="true"></i> La contraseña no cumple los requisitos';
        input.parentNode.appendChild(errorDiv);
    }

    function limpiarErrores(input) {
        const errorExistente = input.parentNode.querySelector('.password-errors');
        if (errorExistente) {
            errorExistente.remove();
        }
        const errorServidor = document.getElementById('password-error');
        if (errorServidor) {
            errorServidor.remove();
        }
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        input.setAttribute('aria-describedby', 'password-requisitos');
    }

    passwordInput.addEventListener('input', function() {
        const validacion = validarPassword(this.value);
        actualizarRequisitos(validacion.requisitos);

        if (validacion.valida) {
            limpiarErrores(this);
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
        }

        if (confirmPasswordInput.value.length > 0) {
            validarConfirmacion();
        }
    });

    function validarConfirmacion() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        const errorExistente = confirmPasswordInput.parentNode.querySelector('.confirm-error');
        if (errorExistente) {
            errorExistente.remove();
        }

        if (confirmPassword.length === 0) {
            confirmPasswordInput.classList.remove('is-invalid', 'is-valid');
            return true;
        }

        if (password !== confirmPassword) {
            confirmPasswordInput.classList.add('is-invalid');
            confirmPasswordInput.classList.remove('is-valid');
            confirmPasswordInput.setAttribute('aria-invalid', 'true');

            const errorDiv = document.createElement('div');
            errorDiv.className = 'confirm-error invalid-feedback';
            errorDiv.innerHTML = '<i class="fas fa-circle-exclamation" aria-hidden="true"></i> Las contraseñas no coinciden';

            confirmPasswordInput.parentNode.appendChild(errorDiv);
            return false;
        } else {
            confirmPasswordInput.classList.remove('is-invalid');
            confirmPasswordInput.classList.add('is-valid');
            confirmPasswordInput.removeAttribute('aria-invalid');
            return true;
        }
    }

    confirmPasswordInput.addEventListener('input', validarConfirmacion);

    form.addEventListener('submit', function(e) {
        const validacion = validarPassword(passwordInput.value);
        const confirmacionValida = validarConfirmacion();

        if (!validacion.valida) {
            e.preventDefault();
            mostrarErrores(passwordInput);
            passwordInput.focus();
            return false;
        }

        if (!confirmacionValida) {
            e.preventDefault();
            confirmPasswordInput.focus();
            return false;
        }

        return true;
    });
});
