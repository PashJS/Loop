// ============================================
// MOBILE SEARCH FUNCTIONALITY
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    const mobileSearchBtn = document.getElementById('mobileSearchBtn');

    if (mobileSearchBtn) {
        mobileSearchBtn.addEventListener('click', () => {
            // Navigate to search page
            window.location.href = 'search.php';
        });
    }
});


