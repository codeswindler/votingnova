<?php
/**
 * Manage Categories and Nominees
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
Auth::requireLogin();

// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDB();
$message = '';
$error = '';

// Get flash messages from session
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            try {
                $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$name]);
                $_SESSION['flash_message'] = "Category '$name' added successfully!";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Error adding category: " . $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = "Category name is required";
        }
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if ($action === 'add_nominee') {
        $name = trim($_POST['nominee_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $gender = $_POST['gender'] ?? '';
        
        if ($name && $categoryId && $gender) {
            try {
                $stmt = $db->prepare("INSERT INTO nominees (category_id, name, gender) VALUES (?, ?, ?)");
                $stmt->execute([$categoryId, $name, $gender]);
                $_SESSION['flash_message'] = "Nominee '$name' added successfully!";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Error adding nominee: " . $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = "All fields are required";
        }
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                // Check if category has nominees
                $stmt = $db->prepare("SELECT COUNT(*) as nominee_count FROM nominees WHERE category_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch();
                
                if ($result && $result['nominee_count'] > 0) {
                    $_SESSION['flash_error'] = "Cannot delete category. This category has " . $result['nominee_count'] . " nominee(s). Please delete all nominees first.";
                } else {
                    // Delete category (no nominees exist)
                    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['flash_message'] = "Category deleted successfully!";
                }
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Error deleting category: " . $e->getMessage();
            }
        }
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get all categories
$stmt = $db->query("SELECT id, name FROM categories ORDER BY id");
$categories = $stmt->fetchAll();

// Get nominees count per category
$stmt = $db->query("
    SELECT c.id, c.name, 
           COUNT(DISTINCT CASE WHEN n.gender = 'Male' THEN n.id END) as male_count,
           COUNT(DISTINCT CASE WHEN n.gender = 'Female' THEN n.id END) as female_count
    FROM categories c
    LEFT JOIN nominees n ON c.id = n.category_id
    GROUP BY c.id, c.name
    ORDER BY c.id
");
$categoriesWithCounts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/admin/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Manage Categories & Nominees</h1>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Add Category -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Add Category</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_category">
                            <div class="mb-3">
                                <label for="name" class="form-label">Category Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Category</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Add Nominee -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Add Nominee</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_nominee">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="nominee_name" class="form-label">Nominee Name</label>
                                <input type="text" class="form-control" id="nominee_name" name="nominee_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Nominee</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Categories List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Categories (<?php echo count($categories); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <p class="text-muted">No categories yet. Add your first category above.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Male Nominees</th>
                                            <th>Female Nominees</th>
                                            <th>Total</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categoriesWithCounts as $cat): ?>
                                            <tr class="category-row" data-category-id="<?php echo $cat['id']; ?>" style="cursor: pointer;">
                                                <td><?php echo $cat['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                                                    <i class="bi bi-chevron-down ms-2" id="icon-<?php echo $cat['id']; ?>"></i>
                                                </td>
                                                <td><?php echo $cat['male_count']; ?></td>
                                                <td><?php echo $cat['female_count']; ?></td>
                                                <td><?php echo $cat['male_count'] + $cat['female_count']; ?></td>
                                                <td onclick="event.stopPropagation();">
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>', <?php echo $cat['male_count'] + $cat['female_count']; ?>)">
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class="nominees-row" id="nominees-<?php echo $cat['id']; ?>" style="display: none;">
                                                <td colspan="6">
                                                    <div class="p-3 bg-light">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <h6 class="mb-0">Nominees for <?php echo htmlspecialchars($cat['name']); ?></h6>
                                                            <button class="btn btn-sm btn-primary" onclick="showAddNomineeModal(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')">
                                                                <i class="bi bi-plus-circle"></i> Add Nominee
                                                            </button>
                                                        </div>
                                                        <div id="nominees-list-<?php echo $cat['id']; ?>">
                                                            <div class="text-center text-muted">Loading...</div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Nominee Modal -->
    <div class="modal fade" id="nomineeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nomineeModalTitle">Add Nominee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="nomineeForm">
                        <input type="hidden" id="nominee_id" name="nominee_id">
                        <input type="hidden" id="modal_category_id" name="category_id">
                        <div class="mb-3">
                            <label for="modal_nominee_name" class="form-label">Nominee Name</label>
                            <input type="text" class="form-control" id="modal_nominee_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="modal_gender" class="form-label">Gender</label>
                            <select class="form-select" id="modal_gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveNominee()">Save</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .undo-notification {
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .blink {
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
    <script>
        // Category row click handler
        document.querySelectorAll('.category-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON' || e.target.closest('form')) {
                    return; // Don't expand if clicking delete button
                }
                
                const categoryId = this.dataset.categoryId;
                const nomineesRow = document.getElementById('nominees-' + categoryId);
                const icon = document.getElementById('icon-' + categoryId);
                
                if (nomineesRow.style.display === 'none') {
                    nomineesRow.style.display = 'table-row';
                    icon.className = 'bi bi-chevron-up ms-2';
                    loadNominees(categoryId);
                } else {
                    nomineesRow.style.display = 'none';
                    icon.className = 'bi bi-chevron-down ms-2';
                }
            });
        });
        
        // Load nominees for a category
        function loadNominees(categoryId) {
            const listDiv = document.getElementById('nominees-list-' + categoryId);
            listDiv.innerHTML = '<div class="text-center text-muted">Loading...</div>';
            
            fetch(`/api/manage-nominees-api.php?action=list&category_id=${categoryId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        displayNominees(categoryId, data.nominees);
                    } else {
                        listDiv.innerHTML = `<div class="alert alert-danger">${data.error || 'Failed to load nominees'}</div>`;
                    }
                })
                .catch(err => {
                    listDiv.innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
                });
        }
        
        // Display nominees
        function displayNominees(categoryId, nominees) {
            const listDiv = document.getElementById('nominees-list-' + categoryId);
            
            if (nominees.length === 0) {
                listDiv.innerHTML = '<p class="text-muted">No nominees yet. Click "Add Nominee" to add one.</p>';
                return;
            }
            
            // Group by gender
            const male = nominees.filter(n => n.gender === 'Male');
            const female = nominees.filter(n => n.gender === 'Female');
            
            let html = '<div class="row">';
            
            // Male nominees
            if (male.length > 0) {
                html += '<div class="col-md-6 mb-3">';
                html += '<h6 class="text-primary">Male Nominees (' + male.length + ')</h6>';
                html += '<div class="list-group">';
                male.forEach(nominee => {
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${escapeHtml(nominee.name)}</strong>
                                <small class="text-muted d-block">Votes: ${nominee.votes_count || 0}</small>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editNominee(${nominee.id}, '${escapeHtml(nominee.name)}', '${nominee.gender}', ${categoryId})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteNominee(${nominee.id}, ${categoryId}, '${escapeHtml(nominee.name)}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div></div>';
            }
            
            // Female nominees
            if (female.length > 0) {
                html += '<div class="col-md-6 mb-3">';
                html += '<h6 class="text-danger">Female Nominees (' + female.length + ')</h6>';
                html += '<div class="list-group">';
                female.forEach(nominee => {
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${escapeHtml(nominee.name)}</strong>
                                <small class="text-muted d-block">Votes: ${nominee.votes_count || 0}</small>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editNominee(${nominee.id}, '${escapeHtml(nominee.name)}', '${nominee.gender}', ${categoryId})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteNominee(${nominee.id}, ${categoryId}, '${escapeHtml(nominee.name)}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div></div>';
            }
            
            html += '</div>';
            listDiv.innerHTML = html;
        }
        
        // Show add nominee modal
        function showAddNomineeModal(categoryId, categoryName) {
            document.getElementById('nomineeModalTitle').textContent = 'Add Nominee to ' + categoryName;
            document.getElementById('nominee_id').value = '';
            document.getElementById('modal_category_id').value = categoryId;
            document.getElementById('modal_nominee_name').value = '';
            document.getElementById('modal_gender').value = '';
            new bootstrap.Modal(document.getElementById('nomineeModal')).show();
        }
        
        // Edit nominee
        function editNominee(nomineeId, name, gender, categoryId) {
            document.getElementById('nomineeModalTitle').textContent = 'Edit Nominee';
            document.getElementById('nominee_id').value = nomineeId;
            document.getElementById('modal_category_id').value = categoryId;
            document.getElementById('modal_nominee_name').value = name;
            document.getElementById('modal_gender').value = gender;
            new bootstrap.Modal(document.getElementById('nomineeModal')).show();
        }
        
        // Save nominee (add or update)
        function saveNominee() {
            const form = document.getElementById('nomineeForm');
            const formData = new FormData(form);
            const nomineeId = formData.get('nominee_id');
            const action = nomineeId ? 'update' : 'add';
            
            if (nomineeId) {
                formData.append('nominee_id', nomineeId);
            }
            
            formData.append('action', action);
            
            fetch('/api/manage-nominees-api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('nomineeModal')).hide();
                    const categoryId = formData.get('category_id');
                    loadNominees(categoryId);
                    showAlert('success', data.message || 'Nominee saved successfully!');
                    // Reload page to update counts
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', data.error || 'Failed to save nominee');
                }
            })
            .catch(err => {
                showAlert('danger', 'Error: ' + err.message);
            });
        }
        
        // Pending deletions storage
        const pendingDeletions = new Map();
        
        // Delete category with 30-second undo
        function deleteCategory(categoryId, categoryName, nomineeCount) {
            const warning = nomineeCount > 0 
                ? `⚠️ WARNING: This will delete the category "${categoryName}" and ALL ${nomineeCount} nominees permanently!\n\nThis action cannot be undone after 30 seconds.`
                : `⚠️ WARNING: This will delete the category "${categoryName}" permanently!\n\nThis action cannot be undone after 30 seconds.`;
            
            if (!confirm(warning)) {
                return;
            }
            
            // Store pending deletion
            const deletionId = 'cat_' + categoryId + '_' + Date.now();
            const timer = setTimeout(() => {
                executeCategoryDeletion(categoryId);
                pendingDeletions.delete(deletionId);
            }, 30000);
            
            pendingDeletions.set(deletionId, {
                type: 'category',
                id: categoryId,
                name: categoryName,
                timer: timer,
                execute: () => executeCategoryDeletion(categoryId)
            });
            
            // Show undo notification
            showUndoNotification(deletionId, `Category "${categoryName}" will be deleted in 30 seconds...`, 'category');
        }
        
        // Execute category deletion
        function executeCategoryDeletion(categoryId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="id" value="${categoryId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Delete nominee with 30-second undo
        function deleteNominee(nomineeId, categoryId, nomineeName = '') {
            const warning = `⚠️ WARNING: This will delete the nominee "${nomineeName || 'this nominee'}" permanently!\n\nThis action cannot be undone after 30 seconds.`;
            
            if (!confirm(warning)) {
                return;
            }
            
            // Get nominee name if not provided
            if (!nomineeName) {
                fetch(`/api/manage-nominees-api.php?action=list&category_id=${categoryId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const nominee = data.nominees.find(n => n.id == nomineeId);
                            if (nominee) {
                                proceedWithNomineeDeletion(nomineeId, categoryId, nominee.name);
                            }
                        }
                    });
            } else {
                proceedWithNomineeDeletion(nomineeId, categoryId, nomineeName);
            }
        }
        
        // Proceed with nominee deletion
        function proceedWithNomineeDeletion(nomineeId, categoryId, nomineeName) {
            const deletionId = 'nom_' + nomineeId + '_' + Date.now();
            const timer = setTimeout(() => {
                executeNomineeDeletion(nomineeId, categoryId);
                pendingDeletions.delete(deletionId);
            }, 30000);
            
            pendingDeletions.set(deletionId, {
                type: 'nominee',
                id: nomineeId,
                categoryId: categoryId,
                name: nomineeName,
                timer: timer,
                execute: () => executeNomineeDeletion(nomineeId, categoryId)
            });
            
            // Show undo notification
            showUndoNotification(deletionId, `Nominee "${nomineeName}" will be deleted in 30 seconds...`, 'nominee');
        }
        
        // Execute nominee deletion
        function executeNomineeDeletion(nomineeId, categoryId) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('nominee_id', nomineeId);
            
            fetch('/api/manage-nominees-api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadNominees(categoryId);
                    showAlert('success', data.message || 'Nominee deleted successfully!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', data.error || 'Failed to delete nominee');
                }
            })
            .catch(err => {
                showAlert('danger', 'Error: ' + err.message);
            });
        }
        
        // Show undo notification with countdown
        function showUndoNotification(deletionId, message, type) {
            const container = document.getElementById('undoContainer') || createUndoContainer();
            
            let timeLeft = 30;
            const notificationId = 'undo-' + deletionId;
            
            const notification = document.createElement('div');
            notification.id = notificationId;
            notification.className = 'alert alert-warning alert-dismissible fade show undo-notification';
            notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
            
            const countdownSpan = document.createElement('span');
            countdownSpan.id = 'countdown-' + deletionId;
            countdownSpan.className = 'fw-bold';
            
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div class="flex-grow-1">
                        <div>${message}</div>
                        <div class="mt-1">
                            <small>Deleting in: <span id="countdown-${deletionId}" class="fw-bold text-danger">${timeLeft}</span> seconds</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-success ms-2" onclick="undoDeletion('${deletionId}')">
                        <i class="bi bi-arrow-counterclockwise"></i> Undo
                    </button>
                    <button type="button" class="btn-close ms-2" onclick="cancelDeletion('${deletionId}')"></button>
                </div>
            `;
            
            container.appendChild(notification);
            
            // Update countdown
            const countdownInterval = setInterval(() => {
                timeLeft--;
                const countdownEl = document.getElementById('countdown-' + deletionId);
                if (countdownEl) {
                    countdownEl.textContent = timeLeft;
                    if (timeLeft <= 5) {
                        countdownEl.className = 'fw-bold text-danger blink';
                    }
                }
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);
            
            // Store interval for cleanup
            if (!pendingDeletions.has(deletionId)) return;
            const deletion = pendingDeletions.get(deletionId);
            deletion.countdownInterval = countdownInterval;
            deletion.notificationElement = notification;
        }
        
        // Create undo container
        function createUndoContainer() {
            const container = document.createElement('div');
            container.id = 'undoContainer';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
            document.body.appendChild(container);
            return container;
        }
        
        // Undo deletion
        function undoDeletion(deletionId) {
            const deletion = pendingDeletions.get(deletionId);
            if (deletion) {
                clearTimeout(deletion.timer);
                if (deletion.countdownInterval) {
                    clearInterval(deletion.countdownInterval);
                }
                if (deletion.notificationElement) {
                    deletion.notificationElement.remove();
                }
                pendingDeletions.delete(deletionId);
                showAlert('success', `Deletion cancelled. ${deletion.type === 'category' ? 'Category' : 'Nominee'} "${deletion.name}" is safe.`);
            }
        }
        
        // Cancel deletion (close button)
        function cancelDeletion(deletionId) {
            undoDeletion(deletionId);
        }
        
        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid').firstChild);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>
</body>
</html>
