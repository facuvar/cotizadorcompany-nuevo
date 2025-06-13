// Archivo JavaScript principal

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar el cotizador si existe
    const cotizadorForm = document.getElementById('cotizadorForm');
    if (cotizadorForm) {
        initCotizador();
    }

    // Inicializar el formulario de carga de archivos si existe
    const fileUploadForm = document.getElementById('fileUploadForm');
    if (fileUploadForm) {
        initFileUpload();
    }

    // Inicializar el formulario de Google Sheets si existe
    const googleSheetsForm = document.getElementById('googleSheetsForm');
    if (googleSheetsForm) {
        initGoogleSheetsForm();
    }
});

/**
 * Inicializar el cotizador
 */
function initCotizador() {
    const optionInputs = document.querySelectorAll('input[type="radio"]');
    const resumenItems = document.getElementById('resumenItems');
    const totalPresupuesto = document.getElementById('totalPresupuesto');
    
    // Agregar eventos a todas las opciones
    optionInputs.forEach(input => {
        input.addEventListener('change', function() {
            updateResumen();
        });
    });
    
    // Actualizar el resumen del presupuesto
    function updateResumen() {
        resumenItems.innerHTML = '';
        let total = 0;
        
        // Obtener todas las opciones seleccionadas
        const selectedOptions = document.querySelectorAll('input[type="radio"]:checked');
        
        selectedOptions.forEach(option => {
            const optionItem = option.closest('.option-item');
            const optionTitle = optionItem.querySelector('.option-title label').textContent.trim();
            const precio = parseFloat(option.dataset.precio);
            
            // Agregar la opción al resumen
            const item = document.createElement('div');
            item.className = 'summary-item';
            item.innerHTML = `
                <div class="item-name">${optionTitle}</div>
                <div class="item-price">${formatNumber(precio)} €</div>
            `;
            resumenItems.appendChild(item);
            
            // Sumar al total
            total += precio;
        });
        
        // Actualizar el total
        totalPresupuesto.textContent = formatNumber(total);
    }
    
    // Formato de números
    function formatNumber(number) {
        return number.toFixed(2).replace('.', ',');
    }
}

/**
 * Inicializar el formulario de carga de archivos
 */
function initFileUpload() {
    const fileInput = document.getElementById('excelFile');
    const fileName = document.getElementById('fileName');
    const progressBar = document.getElementById('progressBar');
    const progressBarFill = document.getElementById('progressBarFill');
    
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileName.textContent = this.files[0].name;
            fileName.style.color = '#333';
        } else {
            fileName.textContent = 'Ningún archivo seleccionado';
            fileName.style.color = '#999';
        }
    });
    
    document.getElementById('fileUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (fileInput.files.length === 0) {
            alert('Por favor, selecciona un archivo Excel.');
            return;
        }
        
        // Mostrar progreso
        progressBar.style.display = 'block';
        progressBarFill.style.width = '0%';
        
        const formData = new FormData(this);
        const xhr = new XMLHttpRequest();
        
        xhr.open('POST', 'upload_excel.php', true);
        
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressBarFill.style.width = percentComplete + '%';
            }
        };
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Archivo cargado y procesado correctamente.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Ocurrió un error al procesar el archivo.'));
                    }
                } catch (e) {
                    console.error('Error al procesar la respuesta:', e);
                    console.log('Respuesta del servidor:', xhr.responseText);
                    alert('Error al procesar la respuesta del servidor. Verifica la consola para más detalles.');
                }
            } else {
                alert('Error HTTP ' + xhr.status + ': ' + xhr.statusText);
            }
            
            // Ocultar barra de progreso
            progressBar.style.display = 'none';
        };
        
        xhr.onerror = function() {
            alert('Error de conexión. Por favor, verifica tu conexión a internet.');
            progressBar.style.display = 'none';
        };
        
        xhr.send(formData);
    });
}

/**
 * Inicializar el formulario de Google Sheets
 */
function initGoogleSheetsForm() {
    document.getElementById('googleSheetsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const sheetsUrl = document.getElementById('sheetsUrl').value;
        
        if (!sheetsUrl) {
            alert('Por favor, ingresa la URL de Google Sheets.');
            return;
        }
        
        const formData = new FormData(this);
        const xhr = new XMLHttpRequest();
        
        xhr.open('POST', 'process_sheets.php', true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Google Sheets conectado correctamente.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Ocurrió un error al conectar con Google Sheets.'));
                    }
                } catch (e) {
                    console.error('Error al procesar la respuesta:', e);
                    console.log('Respuesta del servidor:', xhr.responseText);
                    alert('Error al procesar la respuesta del servidor. Verifica la consola para más detalles.');
                }
            } else {
                alert('Error HTTP ' + xhr.status + ': ' + xhr.statusText);
            }
        };
        
        xhr.onerror = function() {
            alert('Error de conexión. Por favor, verifica tu conexión a internet.');
        };
        
        xhr.send(formData);
    });
} 