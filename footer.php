</div> <!-- .main-content end -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        let sidebar = document.getElementById('mainSidebar');
        let overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }

    // अगर स्क्रीन बड़ी हो जाए (Desktop) तो Sidebar अपने आप ठीक हो जाए
    window.addEventListener('resize', function() {
        if (window.innerWidth > 991) {
            document.getElementById('mainSidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        }
    });

    // Overlay क्लिक पर Sidebar बंद करें
    document.addEventListener('DOMContentLoaded', function() {
        let overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.addEventListener('click', function() {
                toggleSidebar();
            });
        }
    });
</script>
</body>
</html>
