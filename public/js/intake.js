/**
 * Intake / Kiosk Module JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    const state = {
        mode: 'new', // 'new' or 'returning'
        customer: null,
        vehicle: null,
        vehicleMode: 'new' // 'new' or 'existing'
    };
    
    // DOM Elements
    const elements = {
        btnNewCustomer: document.getElementById('btnNewCustomer'),
        btnReturningCustomer: document.getElementById('btnReturningCustomer'),
        sectionNewCustomer: document.getElementById('sectionNewCustomer'),
        sectionReturningSearch: document.getElementById('sectionReturningSearch'),
        sectionVehicle: document.getElementById('sectionVehicle'),
        sectionWorkOrder: document.getElementById('sectionWorkOrder'),
        searchInput: document.getElementById('searchInput'),
        searchResults: document.getElementById('searchResults'),
        vehicleList: document.getElementById('vehicleList'),
        vehicleForm: document.getElementById('vehicleForm'),
        btnNewVehicle: document.getElementById('btnNewVehicle'),
        vinInput: document.getElementById('vinInput'),
        btnDecodeVin: document.getElementById('btnDecodeVin'),
        decodeStatus: document.getElementById('decodeStatus'),
        form: document.getElementById('intakeForm'),
        firstNameInput: document.querySelector('input[name="first_name"]'),
        yearInput: document.querySelector('input[name="year"]'),
        makeInput: document.querySelector('input[name="make"]'),
        modelInput: document.querySelector('input[name="model"]'),
        colorInput: document.querySelector('input[name="color"]'),
        engineInput: document.querySelector('input[name="engine"]'),
        detailInput: document.querySelector('input[name="detail"]')
    };
    
    // Initialize
    init();
    
    function init() {
        bindEvents();
    }
    
    function bindEvents() {
        // Mode switching
        elements.btnNewCustomer.addEventListener('click', () => setMode('new'));
        elements.btnReturningCustomer.addEventListener('click', () => setMode('returning'));
        
        // Search
        let searchTimeout;
        elements.searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            if (query.length >= 3) {
                searchTimeout = setTimeout(() => searchCustomer(query), 500);
            } else {
                elements.searchResults.style.display = 'none';
            }
        });
        
        // Toggle New Vehicle Form
        elements.btnNewVehicle.addEventListener('click', () => {
            state.vehicle = null;
            state.vehicleMode = 'new';
            elements.vehicleForm.classList.remove('hidden');
            document.querySelectorAll('.vehicle-card').forEach(el => el.classList.remove('selected'));
            setDecodeStatus('');
        });

        // VIN decode
        if (elements.vinInput) {
            elements.vinInput.addEventListener('blur', () => {
                elements.vinInput.value = normalizeVin(elements.vinInput.value);
            });
            elements.vinInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleVinDecode();
                }
            });
        }
        if (elements.btnDecodeVin) {
            elements.btnDecodeVin.addEventListener('click', handleVinDecode);
        }
        
        // Form Submission
        elements.form.addEventListener('submit', handleSubmit);
    }
    
    function setMode(mode) {
        state.mode = mode;
        state.customer = null;
        state.vehicle = null;
        
        // Reset UI
        elements.btnNewCustomer.classList.toggle('active', mode === 'new');
        elements.btnReturningCustomer.classList.toggle('active', mode === 'returning');
        
        if (mode === 'new') {
            elements.sectionNewCustomer.classList.remove('hidden');
            elements.sectionReturningSearch.classList.add('hidden');
            if (elements.firstNameInput) {
                elements.firstNameInput.required = true;
            }
            elements.sectionVehicle.classList.remove('hidden');
            elements.vehicleList.innerHTML = '';
            elements.vehicleForm.classList.remove('hidden');
            state.vehicleMode = 'new';
        } else {
            elements.sectionNewCustomer.classList.add('hidden');
            elements.sectionReturningSearch.classList.remove('hidden');
            if (elements.firstNameInput) {
                elements.firstNameInput.required = false;
            }
            elements.sectionVehicle.classList.add('hidden'); // Hide until customer found
            elements.vehicleForm.classList.add('hidden');
            elements.searchInput.value = '';
            elements.searchInput.focus();
            setDecodeStatus('');
        }
        
        elements.sectionWorkOrder.classList.remove('hidden');
    }
    
    function searchCustomer(query) {
        fetch(`api/customer_search.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                displaySearchResults(data);
            })
            .catch(err => console.error(err));
    }
    
    function displaySearchResults(results) {
        elements.searchResults.innerHTML = '';
        if (results.length > 0) {
            results.forEach(customer => {
                const div = document.createElement('div');
                div.className = 'search-result-item';
                div.innerHTML = `
                    <strong>${customer.FirstName} ${customer.LastName}</strong><br>
                    <small>Phone: ${customer.Phone || 'N/A'} | Email: ${customer.Email || 'N/A'}</small>
                `;
                div.addEventListener('click', () => selectCustomer(customer));
                elements.searchResults.appendChild(div);
            });
            elements.searchResults.style.display = 'block';
        } else {
            elements.searchResults.innerHTML = '<div class="search-result-item">No customers found</div>';
            elements.searchResults.style.display = 'block';
        }
    }
    
    function selectCustomer(customer) {
        state.customer = customer;
        elements.searchResults.style.display = 'none';
        elements.searchInput.value = `${customer.FirstName} ${customer.LastName}`;
        
        // Load Vehicles
        loadVehicles(customer.CustomerID);
        
        elements.sectionVehicle.classList.remove('hidden');
        elements.sectionWorkOrder.classList.remove('hidden');
    }
    
    function loadVehicles(customerId) {
        fetch(`api/vehicle_list.php?customer_id=${customerId}`)
            .then(res => res.json())
            .then(vehicles => {
                elements.vehicleList.innerHTML = '';
                if (vehicles.length > 0) {
                    vehicles.forEach(v => {
                        const div = document.createElement('div');
                        div.className = 'vehicle-card';
                        div.innerHTML = `
                            <span><span class="plate">${v.Plate}</span> ${v.Year} ${v.Make} ${v.Model}</span>
                            <small>${v.Color}</small>
                        `;
                        div.addEventListener('click', () => selectVehicle(v, div));
                        elements.vehicleList.appendChild(div);
                    });
                } else {
                    elements.vehicleList.innerHTML = '<p class="text-muted">No registered vehicles found.</p>';
                    // Auto-open new vehicle form
                    elements.vehicleForm.classList.remove('hidden');
                    state.vehicleMode = 'new';
                }
            });
    }
    
    function selectVehicle(vehicle, element) {
        state.vehicle = vehicle;
        state.vehicleMode = 'existing';
        
        document.querySelectorAll('.vehicle-card').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        
        elements.vehicleForm.classList.add('hidden');
        setDecodeStatus('');
    }

    function normalizeVin(vin) {
        return String(vin || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    }

    function setDecodeStatus(message, kind = 'info') {
        if (!elements.decodeStatus) return;
        elements.decodeStatus.textContent = message || '';
        if (!message) {
            elements.decodeStatus.style.color = '';
            return;
        }
        if (kind === 'error') {
            elements.decodeStatus.style.color = '#b00020';
        } else if (kind === 'success') {
            elements.decodeStatus.style.color = '#1b5e20';
        } else {
            elements.decodeStatus.style.color = '#333';
        }
    }

    function applyDecodedValue(input, value, force = false) {
        if (!input) return;
        const normalized = String(value || '').trim();
        if (!normalized) return;
        if (!force && String(input.value || '').trim() !== '') return;
        input.value = normalized;
    }

    function handleVinDecode() {
        if (!elements.vinInput) return;
        const vin = normalizeVin(elements.vinInput.value);
        elements.vinInput.value = vin;

        if (vin.length < 11) {
            setDecodeStatus('Enter at least 11 VIN characters to decode.', 'error');
            return;
        }

        if (elements.btnDecodeVin) {
            elements.btnDecodeVin.disabled = true;
        }
        setDecodeStatus('Decoding VIN...', 'info');

        fetch(`api/decode_vehicle.php?vin=${encodeURIComponent(vin)}`)
            .then(async (res) => {
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || !payload.success) {
                    throw new Error(payload.error || 'VIN decode failed');
                }
                return payload.data || {};
            })
            .then((data) => {
                applyDecodedValue(elements.yearInput, data.year);
                applyDecodedValue(elements.makeInput, data.make);
                applyDecodedValue(elements.modelInput, data.model);
                applyDecodedValue(elements.colorInput, data.color);
                applyDecodedValue(elements.engineInput, data.engine);

                const detailParts = [
                    data.trim,
                    data.body,
                    data.fuel,
                    data.transmission,
                    data.drivetrain
                ].filter(Boolean);
                if (detailParts.length > 0) {
                    applyDecodedValue(elements.detailInput, detailParts.join(' | '));
                }

                let status = 'VIN decoded. Verify values before creating draft.';
                if (String(data.color_source || '') === 'history') {
                    status += ' Color pulled from vehicle history.';
                } else if (String(data.color_source || '') === 'fallback') {
                    status += ' Color not provided by decoder; set to UNKNOWN.';
                }
                setDecodeStatus(status, 'success');
            })
            .catch((err) => {
                setDecodeStatus(err.message || 'VIN decode failed.', 'error');
            })
            .finally(() => {
                if (elements.btnDecodeVin) {
                    elements.btnDecodeVin.disabled = false;
                }
            });
    }
    
    function handleSubmit(e) {
        e.preventDefault();
        
        const fd = new FormData(elements.form);
        const data = Object.fromEntries(fd.entries());
        
        // Add state info
        data.mode = state.mode;
        data.vehicle_mode = state.vehicleMode;
        if (data.vin) {
            data.vin = normalizeVin(data.vin);
        }
        if (state.customer) data.customer_id = state.customer.CustomerID;
        if (state.vehicle) {
            data.cvid = state.vehicle.CVID;
            data.vehicle_id = state.vehicle.VehicleID;
        }
        
        // Validate
        if (state.mode === 'returning' && !state.customer) {
            alert('Please search and select a customer.');
            return;
        }
        
        if (state.vehicleMode === 'existing' && !state.vehicle) {
            alert('Please select a vehicle.');
            return;
        }
        
        // Submit
        fetch('api/submit_intake.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                const draftId = res.draft_wo_id;
                if (draftId) {
                    window.location.href = `../modules/intake/draft_view.php?draft_wo_id=${encodeURIComponent(draftId)}`;
                } else {
                    alert(res.message || 'Draft created.');
                    window.location.reload();
                }
            } else {
                alert('Error: ' + res.error);
            }
        })
        .catch(err => {
            console.error(err);
            alert('An unknown error occurred.');
        });
    }
});
