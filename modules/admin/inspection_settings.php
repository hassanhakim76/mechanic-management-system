<?php
/**
 * Admin - Inspection Settings
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$inspectionModel = new VehicleInspection();

if (isPost()) {
    if (!verifyCSRFToken(post('csrf_token'))) {
        Session::setFlashMessage('error', 'Security token expired. Refresh and try again.');
        redirect('inspection_settings.php');
    }

    $action = post('action', '');
    $message = '';
    $ok = false;

    if ($action === 'save_category') {
        $ok = $inspectionModel->saveCategory([
            'category_id' => post('category_id', 0),
            'category_code' => post('category_code', 0),
            'category_name' => post('category_name', ''),
            'display_order' => post('display_order', 0),
            'active' => post('active') === '1'
        ]);
        $message = $ok ? 'Inspection category saved.' : ($inspectionModel->getLastError() ?: 'Unable to save inspection category.');
    } elseif ($action === 'set_category_active') {
        $active = post('active') === '1';
        $ok = $inspectionModel->setCategoryActive(post('category_id', 0), $active);
        $message = $ok ? ($active ? 'Inspection category activated.' : 'Inspection category deactivated.') : ($inspectionModel->getLastError() ?: 'Unable to update inspection category.');
    } elseif ($action === 'save_item') {
        $ok = $inspectionModel->saveTemplateItem([
            'master_item_id' => post('master_item_id', 0),
            'category_id' => post('category_id', 0),
            'item_code' => post('item_code', 0),
            'item_label' => post('item_label', ''),
            'check_description' => post('check_description', ''),
            'display_order' => post('display_order', 0),
            'active' => post('active') === '1'
        ]);
        $message = $ok ? 'Inspection item saved.' : ($inspectionModel->getLastError() ?: 'Unable to save inspection item.');
    } elseif ($action === 'set_item_active') {
        $active = post('active') === '1';
        $ok = $inspectionModel->setTemplateItemActive(post('master_item_id', 0), $active);
        $message = $ok ? ($active ? 'Inspection item activated.' : 'Inspection item deactivated.') : ($inspectionModel->getLastError() ?: 'Unable to update inspection item.');
    }

    if ($message !== '') {
        Session::setFlashMessage($ok ? 'success' : 'error', $message);
    }

    redirect('inspection_settings.php');
}

$categories = $inspectionModel->getTemplateCategoriesWithItems(true);
$flatCategories = [];
$activeCategoryCount = 0;
$activeItemCount = 0;
$inactiveItemCount = 0;

foreach ($categories as $category) {
    if (!empty($category['active'])) {
        $activeCategoryCount++;
    }
    $flatCategories[] = [
        'category_id' => (int)$category['category_id'],
        'category_code' => (int)$category['category_code'],
        'category_name' => (string)$category['category_name'],
        'active' => !empty($category['active']),
        'next_item_code' => $inspectionModel->getNextItemCodeForCategory((int)$category['category_id']),
        'next_display_order' => $inspectionModel->getNextDisplayOrderForCategory((int)$category['category_id'])
    ];
    foreach ($category['items'] as $item) {
        if (!empty($category['active']) && !empty($item['active'])) {
            $activeItemCount++;
        } else {
            $inactiveItemCount++;
        }
    }
}

$nextCategoryCode = $inspectionModel->getNextCategoryCode();
$defaultItemCategory = $flatCategories[0] ?? null;
$defaultItemCode = $defaultItemCategory ? (int)$defaultItemCategory['next_item_code'] : 1;
$defaultItemOrder = $defaultItemCategory ? (int)$defaultItemCategory['next_display_order'] : 10;

$flash = Session::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspection Settings - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <style>
        .inspection-settings-shell {
            display: grid;
            gap: 14px;
        }

        .inspection-settings-panel {
            background: #fff;
            border: 1px solid #d9e0e8;
            border-radius: 8px;
            padding: 14px;
        }

        .inspection-settings-panel h2,
        .inspection-settings-panel h3 {
            margin-top: 0;
            color: #173a6a;
        }

        .inspection-settings-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(140px, 1fr));
            gap: 10px;
        }

        .inspection-settings-stat {
            border: 1px solid #d9e0e8;
            border-radius: 8px;
            padding: 12px;
            background: #f8fafc;
        }

        .inspection-settings-stat strong {
            display: block;
            color: #173a6a;
            font-size: 22px;
            line-height: 1.1;
        }

        .inspection-settings-form {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) 100px 120px 90px auto;
            gap: 10px;
            align-items: end;
        }

        .inspection-settings-item-form {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) 90px minmax(180px, 1fr) minmax(360px, 2.2fr) 80px 90px 70px auto;
            gap: 8px;
            align-items: end;
        }

        .inspection-settings-category-head {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 100px 110px 90px auto auto;
            gap: 8px;
            align-items: end;
            padding-bottom: 12px;
            margin-bottom: 12px;
            border-bottom: 1px solid #d9e0e8;
        }

        .inspection-settings-category-head .form-group label {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .inspection-settings-muted {
            color: #667085;
            font-size: 12px;
        }

        .inspection-settings-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .inspection-code-preview {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #c7d0dc;
            background: #f8fafc;
            color: #173a6a;
            font-weight: 700;
        }

        .inspection-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 70px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .inspection-status-pill-active {
            background: #e6f4ea;
            color: #1f6b37;
        }

        .inspection-status-pill-inactive {
            background: #eef1f5;
            color: #667085;
        }

        .inspection-settings-items-table {
            table-layout: fixed;
        }

        .inspection-settings-items-table select,
        .inspection-settings-items-table input[type="text"],
        .inspection-settings-items-table input[type="number"] {
            width: 100%;
            min-width: 0;
        }

        .inspection-settings-items-table .inspection-code-cell {
            font-weight: 700;
            color: #173a6a;
            white-space: nowrap;
        }

        @media (max-width: 1100px) {
            .inspection-settings-summary,
            .inspection-settings-form,
            .inspection-settings-item-form,
            .inspection-settings-category-head {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Inspection Settings</h1>
        <div class="user-info">
            <a href="settings.php">Settings</a> |
            <a href="work_orders.php">Work Orders</a>
        </div>
    </div>

    <div class="container-fluid">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="inspection-settings-shell">
            <section class="inspection-settings-panel">
                <h2>Template Summary</h2>
                <div class="inspection-settings-summary">
                    <div class="inspection-settings-stat">
                        <strong><?php echo (int)$activeCategoryCount; ?></strong>
                        Active Categories
                    </div>
                    <div class="inspection-settings-stat">
                        <strong><?php echo (int)$activeItemCount; ?></strong>
                        Active Items
                    </div>
                    <div class="inspection-settings-stat">
                        <strong><?php echo (int)$inactiveItemCount; ?></strong>
                        Inactive Items
                    </div>
                </div>
                <p class="inspection-settings-muted">Changes affect new inspections and in-progress inspections when they are opened. Completed inspections keep their historical snapshot.</p>
            </section>

            <section class="inspection-settings-panel">
                <h2>Add Category</h2>
                <form method="POST" class="inspection-settings-form">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="save_category">
                    <input type="hidden" name="category_id" value="0">
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label>Code</label>
                        <input type="number" name="category_code" value="<?php echo (int)$nextCategoryCode; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Order</label>
                        <input type="number" name="display_order" value="<?php echo (int)($nextCategoryCode * 10); ?>">
                    </div>
                    <label>
                        <input type="checkbox" name="active" value="1" checked>
                        Active
                    </label>
                    <button type="submit" class="btn btn-success">Add Category</button>
                </form>
            </section>

            <section class="inspection-settings-panel">
                <h2>Add Item</h2>
                <form method="POST" class="inspection-settings-item-form">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="save_item">
                    <input type="hidden" name="master_item_id" value="0">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" required data-add-item-category>
                            <?php foreach ($flatCategories as $category): ?>
                                <option
                                    value="<?php echo (int)$category['category_id']; ?>"
                                    data-category-code="<?php echo (int)$category['category_code']; ?>"
                                    data-next-item-code="<?php echo (int)$category['next_item_code']; ?>"
                                    data-next-display-order="<?php echo (int)$category['next_display_order']; ?>"
                                >
                                    <?php echo e($category['category_name']); ?><?php echo !$category['active'] ? ' (inactive)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Item Code</label>
                        <input type="number" name="item_code" value="<?php echo (int)$defaultItemCode; ?>" required data-add-item-code>
                    </div>
                    <div class="form-group">
                        <label>Item Name</label>
                        <input type="text" name="item_label" required>
                    </div>
                    <div class="form-group">
                        <label>What to Check</label>
                        <input type="text" name="check_description">
                    </div>
                    <div class="form-group">
                        <label>Order</label>
                        <input type="number" name="display_order" value="<?php echo (int)$defaultItemOrder; ?>" data-add-item-order>
                    </div>
                    <div class="form-group">
                        <label>Preview</label>
                        <input type="text" class="inspection-code-preview" value="" readonly data-add-item-preview>
                    </div>
                    <label>
                        <input type="checkbox" name="active" value="1" checked>
                        Active
                    </label>
                    <button type="submit" class="btn btn-success">Add Item</button>
                </form>
            </section>

            <?php foreach ($categories as $category): ?>
                <?php $categoryFormId = 'categoryForm' . (int)$category['category_id']; ?>
                <?php $categoryActive = !empty($category['active']); ?>
                <section class="inspection-settings-panel">
                    <form method="POST" id="<?php echo e($categoryFormId); ?>">
                        <?php csrfField(); ?>
	                        <input type="hidden" name="action" value="save_category">
	                        <input type="hidden" name="category_id" value="<?php echo (int)$category['category_id']; ?>">
	                    </form>

                    <div class="inspection-settings-category-head">
                        <div class="form-group">
                            <label>
                                <span>Category Name</span>
                                <span class="inspection-settings-muted">ID <?php echo (int)$category['category_id']; ?></span>
                            </label>
	                            <input form="<?php echo e($categoryFormId); ?>" type="text" name="category_name" value="<?php echo e($category['category_name']); ?>" required>
	                        </div>
	                        <div class="form-group">
	                            <label>Code</label>
	                            <input form="<?php echo e($categoryFormId); ?>" type="number" name="category_code" value="<?php echo (int)$category['category_code']; ?>" required>
	                        </div>
	                        <div class="form-group">
	                            <label>Order</label>
	                            <input form="<?php echo e($categoryFormId); ?>" type="number" name="display_order" value="<?php echo (int)$category['display_order']; ?>">
                        </div>
                        <label>
                            <input form="<?php echo e($categoryFormId); ?>" type="checkbox" name="active" value="1" <?php echo $categoryActive ? 'checked' : ''; ?>>
                            Active
                        </label>
                        <button form="<?php echo e($categoryFormId); ?>" type="submit" class="btn btn-small">Save Category</button>
                        <form method="POST" class="inspection-settings-actions">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="set_category_active">
                            <input type="hidden" name="category_id" value="<?php echo (int)$category['category_id']; ?>">
                            <input type="hidden" name="active" value="<?php echo $categoryActive ? '0' : '1'; ?>">
                            <button type="submit" class="btn btn-small <?php echo $categoryActive ? 'btn-danger' : 'btn-success'; ?>"><?php echo $categoryActive ? 'Deactivate' : 'Reactivate'; ?></button>
                        </form>
                    </div>

                    <table class="data-grid inspection-settings-items-table">
                        <colgroup>
                            <col style="width: 48px;">
                            <col style="width: 74px;">
                            <col style="width: 92px;">
                            <col style="width: 34%;">
                            <col style="width: 42%;">
                            <col style="width: 86px;">
                            <col style="width: 122px;">
                            <col style="width: 190px;">
                        </colgroup>
                        <thead>
	                            <tr>
	                                <th>ID</th>
	                                <th>Code</th>
	                                <th>Item Code</th>
	                                <th>Item Name</th>
	                                <th>What to Check</th>
	                                <th>Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
	                            <?php if (empty($category['items'])): ?>
	                                <tr>
	                                    <td colspan="8">No items in this category.</td>
	                                </tr>
	                            <?php endif; ?>

                            <?php foreach ($category['items'] as $item): ?>
                                <?php $itemFormId = 'inspectionItemForm' . (int)$item['master_item_id']; ?>
                                <?php $itemActive = !empty($item['active']); ?>
                                <tr>
                                    <td>
	                                        <form method="POST" id="<?php echo e($itemFormId); ?>">
	                                            <?php csrfField(); ?>
		                                            <input type="hidden" name="action" value="save_item">
		                                            <input type="hidden" name="master_item_id" value="<?php echo (int)$item['master_item_id']; ?>">
		                                            <input type="hidden" name="category_id" value="<?php echo (int)$item['category_id']; ?>">
		                                            <input type="hidden" name="active" value="0">
		                                        </form>
		                                        <?php echo (int)$item['master_item_id']; ?>
		                                    </td>
		                                    <td class="inspection-code-cell"><?php echo e(VehicleInspection::formatItemCode($item)); ?></td>
		                                    <td><input form="<?php echo e($itemFormId); ?>" type="number" name="item_code" value="<?php echo (int)$item['item_code']; ?>"></td>
		                                    <td>
	                                        <input form="<?php echo e($itemFormId); ?>" type="text" name="item_label" value="<?php echo e($item['item_label']); ?>" required>
	                                    </td>
                                    <td><input form="<?php echo e($itemFormId); ?>" type="text" name="check_description" value="<?php echo e($item['check_description']); ?>"></td>
	                                    <td><input form="<?php echo e($itemFormId); ?>" type="number" name="display_order" value="<?php echo (int)$item['display_order']; ?>"></td>
                                    <td>
                                        <span class="inspection-status-pill <?php echo $itemActive ? 'inspection-status-pill-active' : 'inspection-status-pill-inactive'; ?>">
                                            <?php echo $itemActive ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <label style="display: block; margin-top: 5px;">
                                            <input form="<?php echo e($itemFormId); ?>" type="checkbox" name="active" value="1" <?php echo $itemActive ? 'checked' : ''; ?>>
                                            Active
                                        </label>
                                    </td>
                                    <td>
                                        <div class="inspection-settings-actions">
                                            <button form="<?php echo e($itemFormId); ?>" type="submit" class="btn btn-small">Save</button>
                                            <form method="POST">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="action" value="set_item_active">
                                                <input type="hidden" name="master_item_id" value="<?php echo (int)$item['master_item_id']; ?>">
                                                <input type="hidden" name="active" value="<?php echo $itemActive ? '0' : '1'; ?>">
                                                <button type="submit" class="btn btn-small <?php echo $itemActive ? 'btn-danger' : 'btn-success'; ?>"><?php echo $itemActive ? 'Deactivate' : 'Reactivate'; ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        (function () {
            const categorySelect = document.querySelector('[data-add-item-category]');
            const itemCodeInput = document.querySelector('[data-add-item-code]');
            const orderInput = document.querySelector('[data-add-item-order]');
            const previewInput = document.querySelector('[data-add-item-preview]');

            if (!categorySelect || !itemCodeInput || !orderInput || !previewInput) {
                return;
            }

            function selectedOption() {
                return categorySelect.options[categorySelect.selectedIndex];
            }

            function formatCode(categoryCode, itemCode) {
                const numericItemCode = parseInt(itemCode, 10);
                if (!categoryCode || !numericItemCode) {
                    return '';
                }
                return categoryCode + '.' + String(numericItemCode).padStart(2, '0');
            }

            function refreshPreview() {
                const option = selectedOption();
                previewInput.value = formatCode(option ? option.dataset.categoryCode : '', itemCodeInput.value);
            }

            categorySelect.addEventListener('change', function () {
                const option = selectedOption();
                if (!option) {
                    return;
                }
                itemCodeInput.value = option.dataset.nextItemCode || '1';
                orderInput.value = option.dataset.nextDisplayOrder || '10';
                refreshPreview();
            });

            itemCodeInput.addEventListener('input', refreshPreview);
            refreshPreview();
        })();
    </script>
</body>
</html>
