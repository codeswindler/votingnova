<?php
/**
 * Manage Categories and Nominees
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
Auth::requireLogin();

$db = getDB();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            try {
                $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$name]);
                $message = "Category '$name' added successfully!";
            } catch (Exception $e) {
                $error = "Error adding category: " . $e->getMessage();
            }
        } else {
            $error = "Category name is required";
        }
    }
    
    if ($action === 'add_nominee') {
        $name = trim($_POST['nominee_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $gender = $_POST['gender'] ?? '';
        
        if ($name && $categoryId && $gender) {
            try {
                $stmt = $db->prepare("INSERT INTO nominees (category_id, name, gender) VALUES (?, ?, ?)");
                $stmt->execute([$categoryId, $name, $gender]);
                $message = "Nominee '$name' added successfully!";
            } catch (Exception $e) {
                $error = "Error adding nominee: " . $e->getMessage();
            }
        } else {
            $error = "All fields are required";
        }
    }
    
    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                // Delete nominees first
                $stmt = $db->prepare("DELETE FROM nominees WHERE category_id = ?");
                $stmt->execute([$id]);
                // Delete category
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Category deleted successfully!";
            } catch (Exception $e) {
                $error = "Error deleting category: " . $e->getMessage();
            }
        }
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
                                            <tr>
                                                <td><?php echo $cat['id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                                <td><?php echo $cat['male_count']; ?></td>
                                                <td><?php echo $cat['female_count']; ?></td>
                                                <td><?php echo $cat['male_count'] + $cat['female_count']; ?></td>
                                                <td>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category and all its nominees?');">
                                                        <input type="hidden" name="action" value="delete_category">
                                                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                    </form>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
