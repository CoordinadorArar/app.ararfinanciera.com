document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('password-confirm');
    
    // Función para validar la contraseña
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
    
    // Función para obtener mensajes de error
    function obtenerErrores(requisitos) {
        const errores = [];
        
        if (!requisitos.longitud) {
            errores.push('Debe tener al menos 8 caracteres');
        }
        if (!requisitos.mayuscula) {
            errores.push('Debe contener al menos una letra mayúscula');
        }
        if (!requisitos.minuscula) {
            errores.push('Debe contener al menos una letra minúscula');
        }
        if (!requisitos.numero) {
            errores.push('Debe contener al menos un número');
        }
        
        return errores;
    }
    
    // Función para mostrar errores
    function mostrarErrores(input, errores) {
        // Remover errores existentes
        const errorExistente = input.parentNode.querySelector('.password-errors');
        if (errorExistente) {
            errorExistente.remove();
        }
        
        // Agregar clase de error
        input.classList.add('is-invalid');
        
        // Crear contenedor de errores
        const errorDiv = document.createElement('div');
        errorDiv.className = 'password-errors invalid-feedback';
        errorDiv.style.display = 'block';
        
        const errorList = document.createElement('ul');
        errorList.style.margin = '5px 0';
        errorList.style.paddingLeft = '20px';
        
        errores.forEach(error => {
            const li = document.createElement('li');
            li.textContent = error;
            li.style.fontSize = '0.875em';
            errorList.appendChild(li);
        });
        
        errorDiv.appendChild(errorList);
        input.parentNode.appendChild(errorDiv);
    }
    
    // Función para limpiar errores
    function limpiarErrores(input) {
        const errorExistente = input.parentNode.querySelector('.password-errors');
        if (errorExistente) {
            errorExistente.remove();
        }
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    }
    
    // Validación en tiempo real mientras se escribe
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        
        if (password.length === 0) {
            // Si está vacío, no mostrar errores
            limpiarErrores(this);
            this.classList.remove('is-valid');
            return;
        }
        
        const validacion = validarPassword(password);
        
        if (validacion.valida) {
            limpiarErrores(this);
        } else {
            const errores = obtenerErrores(validacion.requisitos);
            mostrarErrores(this, errores);
        }
        
        // También validar confirmación si ya tiene contenido
        if (confirmPasswordInput.value.length > 0) {
            validarConfirmacion();
        }
    });
    
    // Función para validar confirmación de contraseña
    function validarConfirmacion() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        // Limpiar errores existentes de confirmación
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
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'confirm-error invalid-feedback';
            errorDiv.style.display = 'block';
            errorDiv.innerHTML = '<strong>Las contraseñas no coinciden</strong>';
            
            confirmPasswordInput.parentNode.appendChild(errorDiv);
            return false;
        } else {
            confirmPasswordInput.classList.remove('is-invalid');
            confirmPasswordInput.classList.add('is-valid');
            return true;
        }
    }
    
    // Validar confirmación en tiempo real
    confirmPasswordInput.addEventListener('input', validarConfirmacion);
    
    // Validación al enviar el formulario
    form.addEventListener('submit', function(e) {
        const password = passwordInput.value;
        const validacion = validarPassword(password);
        const confirmacionValida = validarConfirmacion();
        
        // Validar contraseña
        if (!validacion.valida) {
            e.preventDefault();
            const errores = obtenerErrores(validacion.requisitos);
            mostrarErrores(passwordInput, errores);
            
            // Mostrar alerta
            Swal.fire('Error!', 'Por favor, corrija los errores en la contraseña antes de continuar.', 'warning');
            passwordInput.focus();
            return false;
        }
        
        // Validar confirmación
        if (!confirmacionValida) {
            e.preventDefault();
            Swal.fire('Error!', 'Las contraseñas no coinciden.', 'warning');
            confirmPasswordInput.focus();
            return false;
        }
        
        // Si todo está bien, permitir el envío
        return true;
    });
    
    // Limpiar validaciones cuando se enfoca en un campo
    passwordInput.addEventListener('focus', function() {
        if (this.value.length === 0) {
            limpiarErrores(this);
            this.classList.remove('is-valid');
        }
    });
    
    confirmPasswordInput.addEventListener('focus', function() {
        if (this.value.length === 0) {
            const errorExistente = this.parentNode.querySelector('.confirm-error');
            if (errorExistente) {
                errorExistente.remove();
            }
            this.classList.remove('is-invalid', 'is-valid');
        }
    });
});