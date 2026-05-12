<?php
require_once '../includes/bootstrap.php';
Session::requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Intake - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/css/style.css')); ?>">
    <style>
        /* Embedded updates for immediate effect if CSS cache issues */
        .kiosk-container { max-width: 800px; margin: 30px auto; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; }
        .kiosk-header { background: linear-gradient(135deg, #4472c4, #2e5090); color: white; padding: 25px; text-align: center; }
        .mode-selector { display: flex; gap: 20px; margin-bottom: 30px; }
        .mode-btn { flex: 1; padding: 20px; text-align: center; border: 2px solid #e0e0e0; cursor: pointer; border-radius: 8px; transition: all 0.2s; background: #fff; }
        .mode-btn:hover { border-color: #4472c4; background-color: #f0f8ff; }
        .mode-btn.active { border-color: #4472c4; background-color: #e0f0ff; box-shadow: 0 0 10px rgba(68, 114, 196, 0.2); }
    </style>
</head>
<body>

<div class="kiosk-container">
    <header class="kiosk-header">
        <h1><?php echo APP_NAME; ?></h1>
        <p> Front Desk Intake</p>
    </header>

    <div class="kiosk-body">
        
        <form id="intakeForm">
            <?php csrfField(); ?>

            <!-- Mode Selection -->
            <div class="mode-selector">
                <div class="mode-btn active" id="btnNewCustomer">
                    <h3>New Customer</h3>
                    <p>First time visiting?</p>
                </div>
                <div class="mode-btn" id="btnReturningCustomer">
                    <h3>Returning Customer</h3>
                    <p>Search your file</p>
                </div>
            </div>

            <!-- New Customer Section -->
            <div id="sectionNewCustomer" class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                </div>
            </div>

            <!-- Returning Customer Search -->
            <div id="sectionReturningSearch" class="form-section hidden">
                <div class="form-group">
                    <label>Search by Phone, Email, or Plate</label>
                    <input type="text" id="searchInput" placeholder="Enter last 3 digits of phone..." class="large-input">
                    <div id="searchResults" class="search-results"></div>
                </div>
            </div>

            <hr class="mb-10 mt-10">

            <!-- Vehicle Section -->
            <div id="sectionVehicle" class="form-section">
                <h4>Vehicle Information</h4>
                
                <div id="vehicleList" class="mb-10"></div>
                
                <button type="button" id="btnNewVehicle" class="btn btn-primary mb-10">+ Add New Vehicle</button>
                
                <div id="vehicleForm" class="form-container">
                    <div class="form-row">
                        <div class="form-group">
                            <label>VIN</label>
                            <input type="text" name="vin" id="vinInput" placeholder="17-character VIN" maxlength="17">
                        </div>
                        <div class="form-group" style="align-self: flex-end;">
                            <button type="button" id="btnDecodeVin" class="btn">Decode VIN</button>
                        </div>
                    </div>
                    <div id="decodeStatus" class="text-muted mb-10"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Plate Number</label>
                            <input type="text" name="plate" placeholder="ABC-1234">
                        </div>
                        <div class="form-group">
                            <label>Year</label>
                            <input type="number" name="year" placeholder="2020">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Make</label>
                            <input type="text" name="make" placeholder="Toyota">
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="model" placeholder="Corolla">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Color</label>
                            <input type="text" name="color">
                        </div>
                        <div class="form-group">
                            <label>Engine</label>
                            <input type="text" name="engine" id="engineInput" placeholder="2.0L I4">
                        </div>
                        <div class="form-group">
                            <label>Current Mileage</label>
                            <input type="number" name="mileage">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Detail</label>
                            <input type="text" name="detail" id="detailInput" placeholder="Trim / options / notes">
                        </div>
                    </div>
                </div>
            </div>

            <hr class="mb-10 mt-10">

            <!-- Work Order Section -->
            <div id="sectionWorkOrder" class="form-section">
                <h4>Service Request</h4>
                <div class="form-group">
                    <label>Describe the problem or service needed * (separate items with commas)</label>
                    <textarea name="description" class="large" required placeholder="E.g. oil change, tires, front and back noise, door"></textarea>
                </div>
                
                <div class="form-group mt-10">
                    <button type="submit" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 16px;">Create Work Order</button>
                </div>
            </div>

        </form>
    </div>
</div>

<script src="js/intake.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/js/intake.js')); ?>"></script>
</body>
</html>
