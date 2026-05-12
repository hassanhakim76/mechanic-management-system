/**
 * AutoShop Main JavaScript
 * Client-side functionality
 */

// Utility Functions
function showModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function hideModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

function confirmAction(message) {
    return confirm(message);
}

// Form Validation
function validateWorkOrderForm() {
    const customerName = document.getElementById('customer_name');
    if (customerName && !customerName.value.trim()) {
        alert('Customer name is required');
        return false;
    }
    
    const cvid = document.getElementById('cvid');
    if (cvid && !cvid.value) {
        alert('Vehicle must be selected');
        return false;
    }
    
    return true;
}

function validateCustomerForm() {
    const firstName = document.getElementById('FirstName');
    if (firstName && !firstName.value.trim()) {
        alert('First name is required');
        firstName.focus();
        return false;
    }
    
    const phone = document.getElementById('Phone');
    const cell = document.getElementById('Cell');
    const email = document.getElementById('Email');
    
    if ((!phone || !phone.value.trim()) && 
        (!cell || !cell.value.trim()) && 
        (!email || !email.value.trim())) {
        alert('At least one contact method (Phone, Cell, or Email) is required');
        return false;
    }
    
    return true;
}

// Grid Functions
function selectRow(row) {
    // Remove selected class from all rows
    const rows = document.querySelectorAll('.data-grid tr.selected');
    rows.forEach(r => r.classList.remove('selected'));
    
    // Add selected class to clicked row
    row.classList.add('selected');
}

function sortTable(table, column, asc = true) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aVal = a.children[column].textContent.trim();
        const bVal = b.children[column].textContent.trim();
        
        if (!isNaN(aVal) && !isNaN(bVal)) {
            return asc ? aVal - bVal : bVal - aVal;
        }
        
        return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Auto-refresh functionality
let autoRefreshInterval = null;

function startAutoRefresh(seconds = 30) {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    
    autoRefreshInterval = setInterval(() => {
        location.reload();
    }, seconds * 1000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Search functionality
function performSearch(searchField, searchOperator, searchValue) {
    if (!searchValue.trim()) {
        alert('Please enter a search value');
        return;
    }
    
    const params = new URLSearchParams({
        search_field: searchField,
        search_operator: searchOperator,
        search_value: searchValue
    });
    
    window.location.href = '?' + params.toString();
}

// AJAX Helper
function ajaxRequest(url, method, data, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    
    if (method === 'POST') {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    callback(null, response);
                } catch (e) {
                    callback(null, xhr.responseText);
                }
            } else {
                callback(new Error('Request failed: ' + xhr.status));
            }
        }
    };
    
    if (method === 'POST' && data) {
        const params = new URLSearchParams(data).toString();
        xhr.send(params);
    } else {
        xhr.send();
    }
}

// Format phone number as digits only
function formatPhoneInput(input) {
    input.value = input.value.replace(/[^0-9]/g, '');
}

// Format postal code to uppercase
function formatPostalCodeInput(input) {
    input.value = input.value.toUpperCase();
}

// Title case for names
function titleCaseInput(input) {
    const words = input.value.toLowerCase().split(' ');
    for (let i = 0; i < words.length; i++) {
        if (words[i].length > 0) {
            words[i] = words[i][0].toUpperCase() + words[i].substring(1);
        }
    }
    input.value = words.join(' ');
}

// Registration workflow
function openRegistrationDialog() {
    showModal('registrationModal');
}

function searchVehicle() {
    const plateOrVin = document.getElementById('plate_or_vin').value.trim();
    
    if (!plateOrVin) {
        alert('Please enter a plate or VIN');
        return;
    }
    
    // Redirect to intake handler (shared for admin/mechanic)
    const path = window.location.pathname;
    let basePath = '';
    if (path.includes('/modules/')) {
        basePath = path.split('/modules/')[0];
    } else if (path.includes('/public/')) {
        basePath = path.split('/public/')[0];
    } else {
        basePath = path.replace(/\/[^/]*$/, '');
    }
    window.location.href = basePath + '/public/intake.php';
}

// Work Order functions
function openWorkOrder(woid) {
    window.location.href = 'work_order_detail.php?woid=' + woid;
}

function shouldOpenRowOnTap(row) {
    if (!row || row.dataset.openOnTap !== '1') {
        return false;
    }

    if (!window.matchMedia) {
        return false;
    }

    return window.matchMedia('(hover: none), (pointer: coarse)').matches;
}

function printWorkOrder(woid) {
    window.open('print_work_order.php?woid=' + woid, '_blank');
}

function deleteWorkOrder(woid) {
    if (confirmAction('Are you sure you want to delete this work order?')) {
        window.location.href = 'work_order_delete.php?woid=' + woid;
    }
}

// Customer functions
function openCustomer(customerId) {
    window.location.href = 'customer_detail.php?id=' + customerId;
}

// Vehicle functions
function openVehicle(cvid) {
    window.location.href = 'vehicle_detail.php?cvid=' + cvid;
}

// Filter functions
function applyFilters() {
    const form = document.getElementById('filterForm');
    if (form) {
        form.submit();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Setup auto-refresh checkbox
    const autoRefreshCheckbox = document.getElementById('auto_refresh');
    if (autoRefreshCheckbox) {
        autoRefreshCheckbox.addEventListener('change', function() {
            if (this.checked) {
                startAutoRefresh(30);
            } else {
                stopAutoRefresh();
            }
        });
        
        // Start if already checked
        if (autoRefreshCheckbox.checked) {
            startAutoRefresh(30);
        }
    }
    
    // Setup grid row clicks
    const gridRows = document.querySelectorAll('.data-grid tbody tr');
    gridRows.forEach(row => {
        row.addEventListener('click', function() {
            selectRow(this);

            const woid = this.dataset.woid;
            if (woid && shouldOpenRowOnTap(this)) {
                openWorkOrder(woid);
            }
        });
        
        row.addEventListener('dblclick', function() {
            const woid = this.dataset.woid;
            if (woid) {
                openWorkOrder(woid);
            }
        });

        row.addEventListener('keydown', function(event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            const woid = this.dataset.woid;
            if (woid) {
                event.preventDefault();
                openWorkOrder(woid);
            }
        });
    });
    
    // Setup modal close buttons
    const modalCloseButtons = document.querySelectorAll('[data-dismiss="modal"]');
    modalCloseButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('show');
            }
        });
    });
    
    // Close modal on outside click
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    });
    
    // Setup form input formatters
    document.querySelectorAll('input[name="Phone"], input[name="Cell"]').forEach(input => {
        input.addEventListener('blur', function() {
            formatPhoneInput(this);
        });
    });
    
    document.querySelectorAll('input[name="PostalCode"]').forEach(input => {
        input.addEventListener('blur', function() {
            formatPostalCodeInput(this);
        });
    });
    
    document.querySelectorAll('input[name="FirstName"], input[name="LastName"], input[name="City"]').forEach(input => {
        input.addEventListener('blur', function() {
            titleCaseInput(this);
        });
    });
});

// Export functions for inline use
window.AutoShop = {
    showModal,
    hideModal,
    confirmAction,
    validateWorkOrderForm,
    validateCustomerForm,
    selectRow,
    sortTable,
    performSearch,
    openRegistrationDialog,
    searchVehicle,
    openWorkOrder,
    shouldOpenRowOnTap,
    printWorkOrder,
    deleteWorkOrder,
    openCustomer,
    openVehicle,
    applyFilters
};
