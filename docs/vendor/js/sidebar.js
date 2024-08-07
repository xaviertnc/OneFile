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

document.addEventListener('DOMContentLoaded', function () {
    var accHeaders = document.querySelectorAll('.accordion__header');

    accHeaders.forEach(function (header) {
        header.addEventListener('click', function () {
            this.classList.toggle('active');
            var content = this.nextElementSibling;
            if (content.style.maxHeight) {
                content.style.maxHeight = null;
                content.style.paddingTop = '0';
                content.style.paddingBottom = '0';
            } else {
                content.style.maxHeight = content.scrollHeight + 'px';
                content.style.paddingTop = '10px';
                content.style.paddingBottom = '10px';
            }
        });
    });
});
