<?php
/**
 * User Management Page
 */

require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/admin/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-people"></i> User Management</h2>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="showCreateUserModal()">
                            <i class="bi bi-person-plus"></i> Add User
                        </button>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <input type="text" class="form-control" id="searchInput" placeholder="Search by phone, name, or email..." onkeyup="loadUsers()">
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="badge bg-info" id="userCount">0 users</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">System Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Phone</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>OTP Enabled</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div id="pageInfo"></div>
                            <nav>
                                <ul class="pagination mb-0" id="pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm">
                        <input type="hidden" id="userId">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="phone" placeholder="254712345678" required>
                            <small class="text-muted">Format: 254712345678</small>
                        </div>
                        <div class="mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" placeholder="John">
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" placeholder="Doe">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" placeholder="john@example.com">
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="otpEnabled">
                                <label class="form-check-label" for="otpEnabled">
                                    Enable OTP for this user
                                </label>
                            </div>
                        </div>
                        <div class="mb-3" id="activeToggleContainer">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveUser()">Save User</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = 0;
        const pageSize = 50;
        let userModal;

        document.addEventListener('DOMContentLoaded', function() {
            userModal = new bootstrap.Modal(document.getElementById('userModal'));
            loadUsers();
        });

        function loadUsers() {
            const search = document.getElementById('searchInput').value;
            const offset = currentPage * pageSize;
            
            let url = `/api/admin-users-api.php?action=list&limit=${pageSize}&offset=${offset}`;
            if (search) {
                url += `&search=${encodeURIComponent(search)}`;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    displayUsers(data.users);
                    updatePagination(data.total);
                    document.getElementById('userCount').textContent = `${data.total} users`;
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    document.getElementById('usersTableBody').innerHTML = 
                        '<tr><td colspan="9" class="text-center text-danger">Error loading users</td></tr>';
                });
        }

        function displayUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            
            if (users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No users found</td></tr>';
                return;
            }

            // Get current user ID and type
            const currentUserId = <?php echo json_encode(Auth::getUserId()); ?>;
            const currentUserType = <?php echo json_encode($_SESSION['user_type'] ?? null); ?>;
            
            tbody.innerHTML = users.map(user => {
                // Hide delete button if user is trying to delete themselves
                const canDelete = !(currentUserType === 'system_user' && currentUserId == user.id);
                
                return `
                <tr>
                    <td>${user.id}</td>
                    <td>${user.phone}</td>
                    <td>${(() => {
                        const firstName = user.first_name || '';
                        const lastName = user.last_name || '';
                        const fullName = (firstName + ' ' + lastName).trim();
                        return fullName || '-';
                    })()}</td>
                    <td>${user.email || '-'}</td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   ${user.otp_enabled ? 'checked' : ''} 
                                   onchange="toggleUserOTP(${user.id}, this.checked)">
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-${user.is_active ? 'success' : 'secondary'}">
                            ${user.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>${user.created_by_username || 'System'}</td>
                    <td>${new Date(user.created_at).toLocaleString()}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="generateCredentials(${user.id})" title="Generate & Send Credentials">
                            <i class="bi bi-key"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        ${canDelete ? `<button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>` : '<span class="text-muted" title="You cannot delete your own account">-</span>'}
                    </td>
                </tr>
            `;
            }).join('');
        }

        function updatePagination(total) {
            const totalPages = Math.ceil(total / pageSize);
            const pagination = document.getElementById('pagination');
            const pageInfo = document.getElementById('pageInfo');
            
            pageInfo.textContent = `Page ${currentPage + 1} of ${totalPages} (${total} total)`;
            
            pagination.innerHTML = '';
            
            if (totalPages <= 1) return;
            
            // Previous button
            pagination.innerHTML += `
                <li class="page-item ${currentPage === 0 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Previous</a>
                </li>
            `;
            
            // Page numbers
            for (let i = 0; i < totalPages; i++) {
                pagination.innerHTML += `
                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i + 1}</a>
                    </li>
                `;
            }
            
            // Next button
            pagination.innerHTML += `
                <li class="page-item ${currentPage >= totalPages - 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Next</a>
                </li>
            `;
        }

        function changePage(page) {
            currentPage = page;
            loadUsers();
        }

        function showCreateUserModal() {
            document.getElementById('userModalTitle').textContent = 'Add User';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('activeToggleContainer').style.display = 'none';
            userModal.show();
        }

        function editUser(userId) {
            fetch(`/api/admin-users-api.php?action=list&limit=1000`)
                .then(response => response.json())
                .then(data => {
                    const user = data.users.find(u => u.id === userId);
                    if (!user) {
                        alert('User not found');
                        return;
                    }
                    
                    document.getElementById('userModalTitle').textContent = 'Edit User';
                    document.getElementById('userId').value = user.id;
                    document.getElementById('phone').value = user.phone;
                    document.getElementById('phone').readOnly = true;
                    document.getElementById('firstName').value = user.first_name || '';
                    document.getElementById('lastName').value = user.last_name || '';
                    document.getElementById('email').value = user.email || '';
                    document.getElementById('otpEnabled').checked = user.otp_enabled == 1;
                    document.getElementById('isActive').checked = user.is_active == 1;
                    document.getElementById('activeToggleContainer').style.display = 'block';
                    userModal.show();
                });
        }

        function saveUser() {
            const userId = document.getElementById('userId').value;
            const phone = document.getElementById('phone').value;
            const firstName = document.getElementById('firstName').value;
            const lastName = document.getElementById('lastName').value;
            const email = document.getElementById('email').value;
            const otpEnabled = document.getElementById('otpEnabled').checked ? 1 : 0;
            const isActive = document.getElementById('isActive').checked ? 1 : 0;

            if (!phone) {
                alert('Phone number is required');
                return;
            }

            const formData = new FormData();
            if (userId) {
                formData.append('action', 'update');
                formData.append('user_id', userId);
                formData.append('first_name', firstName);
                formData.append('last_name', lastName);
                formData.append('email', email);
                formData.append('otp_enabled', otpEnabled);
                formData.append('is_active', isActive);
            } else {
                formData.append('action', 'create');
                formData.append('phone', phone);
                formData.append('first_name', firstName);
                formData.append('last_name', lastName);
                formData.append('email', email);
                formData.append('otp_enabled', otpEnabled);
            }

            fetch('/api/admin-users-api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userModal.hide();
                        loadUsers();
                        alert(data.message);
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving user');
                });
        }

        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('user_id', userId);

            fetch('/api/admin-users-api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadUsers();
                        alert(data.message);
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting user');
                });
        }

        function toggleUserOTP(userId, enabled) {
            const formData = new FormData();
            formData.append('action', 'toggle_otp');
            formData.append('user_id', userId);
            formData.append('enabled', enabled ? 1 : 0);

            fetch('/api/admin-users-api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error: ' + (data.error || 'Unknown error'));
                        loadUsers(); // Reload to reset toggle
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    loadUsers(); // Reload to reset toggle
                });
        }


        function generateCredentials(userId) {
            if (!confirm('Generate and send login credentials to this user via SMS?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'generate_credentials');
            formData.append('user_id', userId);

            fetch('/api/admin-users-api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Credentials generated and sent successfully via SMS!');
                        loadUsers(); // Reload to show updated status
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error generating credentials');
                });
        }
    </script>
</body>
</html>
