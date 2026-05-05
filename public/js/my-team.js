(function () {
    const table = document.getElementById('my-team-table');
    const tbody = table.querySelector('tbody');
    let sortCol = 0, sortAsc = true;

    function getValue(row, col, type) {
        const td = row.querySelectorAll('td')[col];
        const raw = td ? td.dataset.sortValue : '';
        if (type === 'number') {
            const n = parseFloat(raw);
            return isNaN(n) ? (sortAsc ? Infinity : -Infinity) : n;
        }
        return raw.toLowerCase();
    }

    const svgFill   = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="absolute inset-0 m-auto w-4 h-4"';
    const svgStroke = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute inset-0 m-auto w-3 h-3"';
    const svgUp   = `<svg ${svgFill}><path fill-rule="evenodd" d="M12 20.25a.75.75 0 0 1-.75-.75V6.31l-5.47 5.47a.75.75 0 0 1-1.06-1.06l6.75-6.75a.75.75 0 0 1 1.06 0l6.75 6.75a.75.75 0 1 1-1.06 1.06l-5.47-5.47v13.19a.75.75 0 0 1-.75.75Z" clip-rule="evenodd"/></svg>`;
    const svgDown = `<svg ${svgFill}><path fill-rule="evenodd" d="M12 3.75a.75.75 0 0 1 .75.75v13.19l5.47-5.47a.75.75 0 1 1 1.06 1.06l-6.75 6.75a.75.75 0 0 1-1.06 0l-6.75-6.75a.75.75 0 0 1 1.06-1.06l5.47 5.47V4.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd"/></svg>`;
    const svgBoth = `<svg ${svgStroke}><polyline points="3,6 8,2 13,6"/><polyline points="3,10 8,14 13,10"/></svg>`;

    function updateIcons(activeCol) {
        table.querySelectorAll('th[data-col]').forEach(th => {
            const icon = th.querySelector('.sort-icon');
            const col = parseInt(th.dataset.col);
            if (col === activeCol) {
                icon.innerHTML = sortAsc ? svgUp : svgDown;
                th.classList.add('text-fuchsia-400');
            } else {
                icon.innerHTML = svgBoth;
                th.classList.remove('text-fuchsia-400');
            }
        });
    }

    function sortBy(col, type) {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const av = getValue(a, col, type);
            const bv = getValue(b, col, type);
            if (av < bv) return sortAsc ? -1 : 1;
            if (av > bv) return sortAsc ? 1 : -1;
            return 0;
        });
        rows.forEach(r => tbody.appendChild(r));
        updateIcons(col);
    }

    table.querySelectorAll('th[data-col]').forEach(th => {
        th.addEventListener('click', () => {
            const col = parseInt(th.dataset.col);
            const type = th.dataset.sortType;
            if (col === sortCol) {
                sortAsc = !sortAsc;
            } else {
                sortCol = col;
                sortAsc = true;
            }
            sortBy(sortCol, type);
        });
    });

    sortBy(sortCol, 'number');
})();
