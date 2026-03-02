/**
 * Charts JavaScript
 * Handles chart rendering for categories page
 */

// This file is included but main chart logic is in categories.php
// Additional chart utilities can be added here

function formatCurrency(amount) {
    return 'KES ' + parseFloat(amount).toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatNumber(num) {
    return parseInt(num).toLocaleString();
}
