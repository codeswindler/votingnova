<?php
/**
 * Categories and Winners View
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
Auth::requireLogin();

$db = getDB();
$stmt = $db->query("SELECT id, name FROM categories ORDER BY id");
$categories = $stmt->fetchAll();

$selectedCategory = (int)($_GET['category'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories & Winners - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/admin/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Categories & Winners</h1>
            </div>
        </div>
        
        <!-- Category Filter -->
        <div class="row mb-4">
            <div class="col-md-4">
                <select class="form-select" id="categoryFilter" onchange="loadLeaderboard()">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $selectedCategory == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Winners Summary -->
        <div class="row mb-4" id="winnersSummary">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Winners by Category</h5>
                    </div>
                    <div class="card-body">
                        <div id="winnersContent">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Leaderboard -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Leaderboard</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6>Male Contestants</h6>
                                <canvas id="maleChart" height="400"></canvas>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6>Female Contestants</h6>
                                <canvas id="femaleChart" height="400"></canvas>
                            </div>
                        </div>
                        <div class="table-responsive mt-4">
                            <table class="table table-striped" id="leaderboardTable">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Gender</th>
                                        <th>Votes</th>
                                        <th>Percentage</th>
                                        <th>Transactions</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody id="leaderboardBody">
                                    <tr>
                                        <td colspan="7" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="/admin/js/charts.js"></script>
    <script>
        let maleChart, femaleChart;
        
        function loadLeaderboard() {
            const categoryId = document.getElementById('categoryFilter').value;
            fetch(`/api/admin-api.php?action=category-leaderboard&category_id=${categoryId}`)
                .then(r => r.json())
                .then(data => {
                    displayLeaderboard(data);
                    updateCharts(data);
                });
            // Also reload winners with the same filter
            loadWinners();
        }
        
        function loadWinners() {
            const categoryId = document.getElementById('categoryFilter').value;
            const url = categoryId > 0 
                ? `/api/admin-api.php?action=winners&category_id=${categoryId}`
                : '/api/admin-api.php?action=winners';
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    displayWinners(data);
                });
        }
        
        function displayLeaderboard(data) {
            const tbody = document.getElementById('leaderboardBody');
            tbody.innerHTML = '';
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No data available</td></tr>';
                return;
            }
            
            data.forEach((nominee, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${nominee.name}</td>
                    <td>${nominee.category_name || 'N/A'}</td>
                    <td><span class="badge bg-${nominee.gender === 'Male' ? 'primary' : 'success'}">${nominee.gender}</span></td>
                    <td>${nominee.votes_count}</td>
                    <td>${nominee.percentage}%</td>
                    <td>${nominee.transaction_count || 0}</td>
                    <td>KES ${parseFloat(nominee.total_amount || 0).toLocaleString()}</td>
                `;
                tbody.appendChild(row);
            });
        }
        
        function updateCharts(data) {
            const maleData = data.filter(n => n.gender === 'Male').slice(0, 10);
            const femaleData = data.filter(n => n.gender === 'Female').slice(0, 10);
            
            updateChart('maleChart', maleData, 'Male Contestants');
            updateChart('femaleChart', femaleData, 'Female Contestants');
        }
        
        function updateChart(canvasId, data, title) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            
            if (window[canvasId + 'Chart']) {
                window[canvasId + 'Chart'].destroy();
            }
            
            window[canvasId + 'Chart'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(n => n.name),
                    datasets: [{
                        label: 'Votes',
                        data: data.map(n => n.votes_count),
                        backgroundColor: canvasId === 'maleChart' ? 'rgba(54, 162, 235, 0.6)' : 'rgba(255, 99, 132, 0.6)',
                        borderColor: canvasId === 'maleChart' ? 'rgba(54, 162, 235, 1)' : 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: title
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        function displayWinners(data) {
            const content = document.getElementById('winnersContent');
            if (data.length === 0) {
                content.innerHTML = '<p class="text-muted">No winners data available</p>';
                return;
            }
            
            // Always use rainbow gradient palette
            const gradients = [
                'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);',
                'background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);',
                'background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);',
                'background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);',
                'background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);',
                'background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);',
                'background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);',
                'background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);',
                'background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);',
                'background: linear-gradient(135deg, #ff8a80 0%, #ea4c89 100%);',
                'background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);',
                'background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);'
            ];
            
            let html = '<div class="row">';
            data.forEach((category, index) => {
                const headerStyle = gradients[index % gradients.length];
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="card border-primary">
                            <div class="card-header category-header-gradient text-white" style="${headerStyle}; position: relative; overflow: hidden;">
                                <div style="position: relative; z-index: 2; text-shadow: 0 2px 10px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.9), 0 1px 2px rgba(0,0,0,1); font-weight: 700; font-size: 1.1rem; letter-spacing: 0.5px;">
                                    <strong>${category.category_name}</strong>
                                </div>
                                <div class="category-header-overlay"></div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Male Winner</small>
                                        <p class="mb-0">${category.male_winner ? category.male_winner.nominee_name + ' (' + category.male_winner.votes_count + ' votes)' : 'N/A'}</p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Female Winner</small>
                                        <p class="mb-0">${category.female_winner ? category.female_winner.nominee_name + ' (' + category.female_winner.votes_count + ' votes)' : 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            content.innerHTML = html;
        }
        
        // Load data on page load
        loadLeaderboard();
        loadWinners();
        
        // Auto-refresh every 10 seconds
        setInterval(() => {
            loadLeaderboard();
            loadWinners();
        }, 10000);
    </script>
</body>
</html>
