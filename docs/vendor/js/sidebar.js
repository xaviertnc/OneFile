document.getElementById('hamburger-menu').addEventListener('click', function (e) {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
    e.stopPropagation();
});

document.addEventListener('click', function (e) {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger-menu');
    if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !hamburger.contains(e.target)) {
        sidebar.classList.remove('active');
    }
});
