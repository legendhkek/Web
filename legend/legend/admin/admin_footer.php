    </div> <!-- End of content -->
</div> <!-- End of main-container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Admin Scripts -->
<script>
// Add active class to current page in sidebar
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop();
    const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
    
    sidebarLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
            link.style.backgroundColor = 'rgba(255,255,255,0.1)';
        }
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    });
}, 5000);

// Confirm destructive actions
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-danger') || e.target.classList.contains('btn-warning')) {
        if (!confirm('Are you sure you want to perform this action? This cannot be undone.')) {
            e.preventDefault();
        }
    }
});
</script>

</body>
</html>
