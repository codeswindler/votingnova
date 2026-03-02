<?php
/**
 * Navigation Bar Component
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1a1f1c 0%, #232825 100%) !important;">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/dashboard.php">Voting System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/admin/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/categories.php">
                        <i class="bi bi-trophy"></i> Categories & Winners
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/transactions.php">
                        <i class="bi bi-list-ul"></i> Transactions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/users.php">
                        <i class="bi bi-people"></i> Users
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="toggleTheme(); return false;" title="Toggle Dark/Light Mode">
                        <i class="bi" id="themeIcon"></i>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars(Auth::getUsername()); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/admin/logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script>
    // Theme Toggle Functionality
    function initTheme() {
        const theme = localStorage.getItem('theme') || 'dark';
        const colorPalette = localStorage.getItem('colorPalette') || 'forest';
        const themeIcon = document.getElementById('themeIcon');
        
        if (!themeIcon) return;
        
        if (theme === 'dark') {
            document.body.classList.add('dark-theme');
            document.body.setAttribute('data-theme', colorPalette);
            themeIcon.className = 'bi bi-sun';
            themeIcon.setAttribute('id', 'themeIcon');
        } else {
            document.body.classList.remove('dark-theme');
            document.body.removeAttribute('data-theme');
            themeIcon.className = 'bi bi-moon';
            themeIcon.setAttribute('id', 'themeIcon');
        }
    }
    
    function toggleTheme() {
        const isDark = document.body.classList.contains('dark-theme');
        const colorPalette = localStorage.getItem('colorPalette') || 'forest';
        const themeIcon = document.getElementById('themeIcon');
        
        if (!themeIcon) return;
        
        if (isDark) {
            document.body.classList.remove('dark-theme');
            document.body.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
            themeIcon.className = 'bi bi-moon';
            themeIcon.setAttribute('id', 'themeIcon');
        } else {
            document.body.classList.add('dark-theme');
            document.body.setAttribute('data-theme', colorPalette);
            localStorage.setItem('theme', 'dark');
            themeIcon.className = 'bi bi-sun';
            themeIcon.setAttribute('id', 'themeIcon');
        }
    }
    
    // Initialize theme on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
</script>