const btn = document.getElementById('user-menu-btn');
const dropdown = document.getElementById('user-dropdown');

if (btn && dropdown) {
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', function () {
        dropdown.classList.add('hidden');
    });
}
