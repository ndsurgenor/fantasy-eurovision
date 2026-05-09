document.addEventListener('DOMContentLoaded', () => {
    const input   = document.getElementById('name');
    const counter = document.getElementById('name-counter');
    if (!input || !counter) return;

    const max = parseInt(input.maxLength, 10);

    function update() {
        const len = input.value.length;
        counter.textContent = `${len}/${max}`;
        counter.classList.toggle('text-rose-400', len >= max);
        counter.classList.toggle('text-white/70', len < max);
    }

    input.addEventListener('input', update);
    update();
});
