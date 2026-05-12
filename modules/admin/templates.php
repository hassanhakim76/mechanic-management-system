<?php
/**
 * Admin - Template Manager
 * Manage email/letter templates
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$templateModel = new LetterTemplate();
$templates = $templateModel->getAll();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['tid'] ?? 0;
    
    $data = [
        'name' => $_POST['name'],
        'type' => $_POST['type'],
        'subject' => $_POST['subject'],
        'content' => $_POST['content'],
        'status' => 'A'
    ];
    
    if ($action === 'create') {
        if ($templateModel->create($data)) {
            Session::setFlashMessage('success', 'Template created successfully');
        } else {
            Session::setFlashMessage('error', 'Failed to create template');
        }
    } elseif ($action === 'update' && $id) {
        if ($templateModel->update($id, $data)) {
            Session::setFlashMessage('success', 'Template updated successfully');
        } else {
            Session::setFlashMessage('error', 'Failed to update template');
        }
    } elseif ($action === 'delete' && $id) {
        if ($templateModel->delete($id)) {
            Session::setFlashMessage('success', 'Template deleted successfully');
        } else {
            Session::setFlashMessage('error', 'Failed to delete template');
        }
    }
    
    redirect('templates.php');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Templates - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 60%; max-width: 800px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Letter Templates</h1>
        <div class="user-info">
            <a href="work_orders.php">Back to Work Orders</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
        <?php endif; ?>
        
        <div class="toolbar">
            <button class="btn btn-primary" onclick="openModal('create')">New Template</button>
            <button class="btn" onclick="window.location.reload()">Refresh</button>
        </div>
        
        <table class="data-grid">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td><?php echo $t['tid']; ?></td>
                    <td><?php echo e($t['name']); ?></td>
                    <td><?php echo e($t['type']); ?></td>
                    <td><?php echo e($t['subject']); ?></td>
                    <td>
                        <button class="btn" onclick='editTemplate(<?php echo json_encode($t); ?>)'>Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this template?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tid" value="<?php echo $t['tid']; ?>">
                            <button class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Modal -->
    <div id="templateModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">New Template</h2>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="tid" id="templateId" value="">
                
                <div class="form-group">
                    <label>Template Name</label>
                    <input type="text" name="name" id="name" required>
                </div>
                
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" id="type">
                        <option value="Email">Email</option>
                        <option value="Letter">Letter</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" id="subject" required>
                </div>
                
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" id="content" class="large" style="height: 300px;" required></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const modal = document.getElementById('templateModal');
        
        function openModal(mode) {
            modal.style.display = 'block';
            if (mode === 'create') {
                document.getElementById('modalTitle').innerText = 'New Template';
                document.getElementById('formAction').value = 'create';
                document.getElementById('templateId').value = '';
                document.getElementById('name').value = '';
                document.getElementById('subject').value = '';
                document.getElementById('content').value = '';
            }
        }
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        function editTemplate(t) {
            openModal('edit');
            document.getElementById('modalTitle').innerText = 'Edit Template';
            document.getElementById('formAction').value = 'update';
            document.getElementById('templateId').value = t.tid;
            document.getElementById('name').value = t.name;
            document.getElementById('type').value = t.type;
            document.getElementById('subject').value = t.subject;
            document.getElementById('content').value = t.content;
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
