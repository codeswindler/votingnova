/**
 * Dashboard JavaScript
 * Handles dashboard data loading and updates
 */

let categoryChart = null;

// Load dashboard stats
function loadStats() {
    fetch('/api/admin-api.php?action=stats')
        .then(response => response.json())
        .then(data => {
            // Update stat cards
            document.getElementById('totalVotes').textContent = data.total_votes.toLocaleString();
            document.getElementById('totalRevenue').textContent = 'KES ' + parseFloat(data.total_revenue).toLocaleString();
            document.getElementById('totalTransactions').textContent = data.total_transactions.toLocaleString();
            document.getElementById('pendingPayments').textContent = data.pending_payments;
            
            // Update recent transactions
            updateRecentTransactions(data.recent_transactions);
        })
        .catch(error => {
            console.error('Error loading stats:', error);
        });
}

// Update recent transactions table
function updateRecentTransactions(transactions) {
    const tbody = document.getElementById('recentTransactions');
    
    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No recent transactions</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactions.map(t => `
        <tr>
            <td>${t.nominee_name}</td>
            <td>${t.votes_count}</td>
            <td>KES ${parseFloat(t.amount).toLocaleString()}</td>
            <td>${formatTime(t.created_at)}</td>
        </tr>
    `).join('');
}

// Format time
function formatTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    return date.toLocaleDateString();
}

// Load votes by category chart
function loadCategoryChart() {
    fetch('/api/admin-api.php?action=votes-by-category')
        .then(response => response.json())
        .then(data => {
            updateCategoryChart(data);
        })
        .catch(error => {
            console.error('Error loading category chart:', error);
        });
}

// Update category chart
function updateCategoryChart(data) {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    
    if (categoryChart) {
        categoryChart.destroy();
    }
    
    categoryChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.map(c => c.name),
            datasets: [{
                data: data.map(c => parseInt(c.total_votes) || 0),
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384',
                    '#36A2EB', '#FFCE56'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} votes (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCategoryChart();
    
    // Auto-refresh every 10 seconds
    setInterval(() => {
        loadStats();
        loadCategoryChart();
    }, 10000);
});
