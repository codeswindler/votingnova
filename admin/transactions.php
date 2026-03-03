<?php
/**
 * Transactions View
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
Auth::requireLogin();

$db = getDB();
$stmt = $db->query("SELECT id, name FROM categories ORDER BY id");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/admin/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Transactions</h1>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form id="filterForm" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category_id" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="completed">Completed</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" id="dateFrom">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" id="dateTo">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn w-100" onclick="loadTransactions()" style="background: linear-gradient(135deg, #6fb87f 0%, #5a9a6a 100%); border: none; color: white; font-weight: 600;">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transactions Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Transaction List</h5>
                        <span class="badge bg-info" id="totalCount">0 transactions</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Nominee</th>
                                        <th>Category</th>
                                        <th>Gender</th>
                                        <th>Phone</th>
                                        <th>Votes</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Ref</th>
                                    </tr>
                                </thead>
                                <tbody id="transactionsBody">
                                    <tr>
                                        <td colspan="10" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <button class="btn btn-sm btn-outline-primary" onclick="loadPrevious()" id="prevBtn" disabled>Previous</button>
                                <button class="btn btn-sm btn-outline-primary" onclick="loadNext()" id="nextBtn">Next</button>
                            </div>
                            <div>
                                <span id="pageInfo">Page 1</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = 0;
        const pageSize = 50;
        let totalTransactions = 0;
        
        function loadTransactions(resetPage = false) {
            if (resetPage) currentPage = 0;
            
            const params = new URLSearchParams();
            params.append('action', 'transactions');
            params.append('limit', pageSize);
            params.append('offset', currentPage * pageSize);
            
            const categoryId = document.getElementById('categoryFilter').value;
            const status = document.getElementById('statusFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            if (categoryId) params.append('category_id', categoryId);
            if (status) params.append('status', status);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            fetch(`/api/admin-api.php?${params.toString()}`)
                .then(r => r.json())
                .then(data => {
                    totalTransactions = data.total;
                    displayTransactions(data.transactions);
                    updatePagination();
                })
                .catch(err => {
                    console.error('Error loading transactions:', err);
                    document.getElementById('transactionsBody').innerHTML = 
                        '<tr><td colspan="10" class="text-center text-danger">Error loading transactions</td></tr>';
                });
        }
        
        function displayTransactions(transactions) {
            const tbody = document.getElementById('transactionsBody');
            
            if (transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">No transactions found</td></tr>';
                return;
            }
            
            tbody.innerHTML = transactions.map(t => `
                <tr>
                    <td>${t.id}</td>
                    <td>${new Date(t.created_at).toLocaleString()}</td>
                    <td>${t.nominee_name}</td>
                    <td>${t.category_name}</td>
                    <td><span class="badge bg-${t.gender === 'Male' ? 'primary' : 'success'}">${t.gender}</span></td>
                    <td>${t.phone}</td>
                    <td>${t.votes_count}</td>
                    <td>KES ${parseFloat(t.amount).toLocaleString()}</td>
                    <td><span class="badge bg-${getStatusColor(t.status)}">${t.status}</span></td>
                    <td>${t.mpesa_ref || t.transaction_id || '-'}</td>
                </tr>
            `).join('');
            
            document.getElementById('totalCount').textContent = `${totalTransactions} transactions`;
        }
        
        function getStatusColor(status) {
            const colors = {
                'completed': 'success',
                'pending': 'warning',
                'failed': 'danger',
                'cancelled': 'secondary'
            };
            return colors[status] || 'secondary';
        }
        
        function loadNext() {
            if ((currentPage + 1) * pageSize < totalTransactions) {
                currentPage++;
                loadTransactions();
            }
        }
        
        function loadPrevious() {
            if (currentPage > 0) {
                currentPage--;
                loadTransactions();
            }
        }
        
        function updatePagination() {
            document.getElementById('prevBtn').disabled = currentPage === 0;
            document.getElementById('nextBtn').disabled = (currentPage + 1) * pageSize >= totalTransactions;
            document.getElementById('pageInfo').textContent = 
                `Page ${currentPage + 1} (${totalTransactions} total)`;
        }
        
        // Load on page load
        loadTransactions();
        
        // Auto-refresh every 10 seconds
        setInterval(() => {
            loadTransactions();
        }, 10000);
    </script>
</body>
</html>
