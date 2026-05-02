document.addEventListener('DOMContentLoaded', () => {
    const form          = document.getElementById('team-form');
    const BUDGET        = parseFloat(form.dataset.budget);
    const REQUIRED      = parseInt(form.dataset.requiredPicks, 10);
    const checkboxes    = [...form.querySelectorAll('.country-checkbox')];
    const submitBtn     = document.getElementById('submit-btn');
    const countriesCount = document.getElementById('countries-count');
    const groupsCount   = document.getElementById('groups-count');
    const budgetSpent   = document.getElementById('budget-spent');
    const budgetRem     = document.getElementById('budget-remaining');

    const allGroupSections  = [...document.querySelectorAll('[data-group-container]')];
    const nonWildcardGroups = allGroupSections.filter(s => s.dataset.isWildcard !== '1');
    const TOTAL_GROUPS      = nonWildcardGroups.length;

    function groupCounts(checked) {
        const counts = {};
        checked.forEach(cb => {
            const gid = cb.dataset.groupId;
            counts[gid] = (counts[gid] || 0) + 1;
        });
        return counts;
    }

    function update() {
        const checked   = checkboxes.filter(cb => cb.checked);
        const count     = checked.length;
        const spent     = checked.reduce((sum, cb) => sum + parseFloat(cb.dataset.price), 0);
        const remaining = BUDGET - spent;
        const counts    = groupCounts(checked);

        // Countries counter (all picks, including wildcard)
        countriesCount.textContent = `${count}/${REQUIRED}`;

        // Groups counter (non-wildcard groups with ≥1 pick / total non-wildcard groups)
        const groupsFilled = nonWildcardGroups.filter(s => (counts[s.dataset.groupId] || 0) > 0).length;
        groupsCount.textContent = `${groupsFilled}/${TOTAL_GROUPS}`;

        // Budget counters
        budgetSpent.textContent = `€${spent.toFixed(1)}m`;
        budgetRem.textContent   = `${remaining < 0 ? '-' : ''}€${Math.abs(remaining).toFixed(1)}m`;

        // Colour remaining amount
        budgetRem.classList.remove('text-red-400', 'text-yellow-400', 'text-emerald-400');
        if (remaining < 0) {
            budgetRem.classList.add('text-red-400');
        } else if (remaining < 5) {
            budgetRem.classList.add('text-yellow-400');
        } else {
            budgetRem.classList.add('text-emerald-400');
        }

        // Update per-group displays and validity
        let valid = (count === REQUIRED) && (remaining >= 0);

        allGroupSections.forEach(section => {
            const gid        = section.dataset.groupId;
            const isWildcard = section.dataset.isWildcard === '1';
            const max        = isWildcard ? 1 : 2;
            const min        = isWildcard ? 0 : 1;
            const gCount     = counts[gid] || 0;
            const display    = section.querySelector(`[data-group-display="${gid}"]`);

            if (display) {
                display.textContent = `${gCount}/${max}`;
                display.classList.remove('text-red-400', 'text-green-400', 'text-indigo-400');
                if (gCount > max) {
                    display.classList.add('text-red-400');
                } else if (gCount >= min && (min > 0 || gCount > 0)) {
                    display.classList.add('text-green-400');
                } else {
                    display.classList.add('text-indigo-400');
                }
            }

            if (gCount < min || gCount > max) valid = false;
        });

        submitBtn.disabled = !valid;

        // Disable unchecked boxes when limits are reached
        checkboxes.forEach(cb => {
            if (cb.checked) {
                cb.disabled = false;
                return;
            }
            const gid        = cb.dataset.groupId;
            const isWildcard = document.querySelector(`[data-group-container][data-group-id="${gid}"]`)?.dataset.isWildcard === '1';
            const max        = isWildcard ? 1 : 2;
            const gCount     = counts[gid] || 0;

            cb.disabled = (count >= REQUIRED) || (gCount >= max);
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', update));
    update(); // set initial state on page load

    // View toggle: groups vs running order
    const viewGroupsBtn  = document.getElementById('view-groups');
    const viewOrderBtn   = document.getElementById('view-order');
    const orderContainer = document.getElementById('order-container');
    const allLabels      = [...form.querySelectorAll('label[data-running-order]')];
    const labelParent    = new Map(allLabels.map(l => [l, l.parentElement]));

    function setView(view) {
        if (view === 'order') {
            const sorted = [...allLabels].sort((a, b) => {
                const ra = parseInt(a.dataset.runningOrder, 10) || 9999;
                const rb = parseInt(b.dataset.runningOrder, 10) || 9999;
                return ra - rb;
            });

            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3';
            sorted.forEach(label => {
                const ro     = parseInt(label.dataset.runningOrder, 10);
                const badge  = label.querySelector('.ro-number');
                if (badge) badge.textContent = ro || '—';
                grid.appendChild(label);
            });

            orderContainer.innerHTML = '';
            orderContainer.appendChild(grid);

            form.querySelectorAll('.ro-number').forEach(el => el.classList.remove('hidden'));
            form.querySelectorAll('.ro-group-name').forEach(el => el.classList.remove('hidden'));

            allGroupSections.forEach(s => s.classList.add('hidden'));
            orderContainer.classList.remove('hidden');

            viewGroupsBtn.classList.remove('bg-white/20', 'text-white');
            viewGroupsBtn.classList.add('text-white/50');
            viewOrderBtn.classList.add('bg-white/20', 'text-white');
            viewOrderBtn.classList.remove('text-white/50', 'hover:text-white/85', 'hover:bg-white/10');
        } else {
            allLabels.forEach(label => labelParent.get(label).appendChild(label));

            form.querySelectorAll('.ro-number').forEach(el => el.classList.add('hidden'));
            form.querySelectorAll('.ro-group-name').forEach(el => el.classList.add('hidden'));

            allGroupSections.forEach(s => s.classList.remove('hidden'));
            orderContainer.classList.add('hidden');

            viewOrderBtn.classList.remove('bg-white/20', 'text-white');
            viewOrderBtn.classList.add('text-white/50', 'hover:text-white/85', 'hover:bg-white/10');
            viewGroupsBtn.classList.add('bg-white/20', 'text-white');
            viewGroupsBtn.classList.remove('text-white/50');
        }
    }

    viewGroupsBtn.addEventListener('click', () => setView('groups'));
    viewOrderBtn.addEventListener('click',  () => setView('order'));
});
