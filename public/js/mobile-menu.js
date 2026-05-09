const btn = document.getElementById('mobile-menu-btn');
const menu = document.getElementById('mobile-menu');
const openIcon = document.getElementById('mobile-menu-icon-open');
const closeIcon = document.getElementById('mobile-menu-icon-close');

if (btn && menu) {
    btn.addEventListener('click', function () {
        const isNowHidden = menu.classList.toggle('hidden');
        openIcon.classList.toggle('hidden', !isNowHidden);
        closeIcon.classList.toggle('hidden', isNowHidden);
    });
}
