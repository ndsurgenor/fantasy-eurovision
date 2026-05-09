// Close mobile menu when viewport widens past the sm breakpoint
const navToggle = document.getElementById('nav-toggle');
if (navToggle) {
    window.matchMedia('(min-width: 640px)').addEventListener('change', e => {
        if (e.matches) navToggle.checked = false;
    });
}

const btn      = document.getElementById('user-menu-btn');
const dropdown = document.getElementById('user-dropdown');
const chevron  = document.getElementById('user-menu-chevron');

if (btn && dropdown) {
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const open = dropdown.classList.toggle('hidden');
        if (chevron) chevron.classList.toggle('-scale-y-100', !open);
    });

    document.addEventListener('click', function () {
        dropdown.classList.add('hidden');
        if (chevron) chevron.classList.remove('-scale-y-100');
    });
}
