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

    // Sticky tracker backdrop — fades in as user scrolls past the sentinel
    const sentinel = document.getElementById('tracker-sentinel');
    const backdrop = document.getElementById('tracker-backdrop');
    function updateBackdrop() {
        const progress = Math.max(0, Math.min(1, -sentinel.getBoundingClientRect().top / 80));
        backdrop.style.opacity = progress;
    }
    window.addEventListener('scroll', updateBackdrop, { passive: true });
    updateBackdrop();

    // View toggle
    const viewGroupsBtn  = document.getElementById('view-groups');
    const viewOrderBtn   = document.getElementById('view-order');
    const viewPriceBtn   = document.getElementById('view-price');
    const orderContainer = document.getElementById('order-container');
    const priceContainer = document.getElementById('price-container');
    const allLabels      = [...form.querySelectorAll('label[data-running-order]')];
    const labelParent    = new Map(allLabels.map(l => [l, l.parentElement]));

    // Pre-compute per-group min/max prices
    const groupPrices = {};
    allLabels.forEach(label => {
        const cb  = label.querySelector('.country-checkbox');
        const gid = cb.dataset.groupId;
        const p   = parseFloat(cb.dataset.price);
        if (!groupPrices[gid]) groupPrices[gid] = { min: p, max: p };
        else {
            groupPrices[gid].min = Math.min(groupPrices[gid].min, p);
            groupPrices[gid].max = Math.max(groupPrices[gid].max, p);
        }
    });
    const viewBtns       = [viewGroupsBtn, viewOrderBtn, viewPriceBtn];

    function setActiveBtn(activeBtn) {
        viewBtns.forEach(btn => {
            if (btn === activeBtn) {
                btn.classList.add('bg-white/20', 'text-white');
                btn.classList.remove('text-white/50', 'hover:text-white/85', 'hover:bg-white/10');
            } else {
                btn.classList.remove('bg-white/20', 'text-white');
                btn.classList.add('text-white/50', 'hover:text-white/85', 'hover:bg-white/10');
            }
        });
    }

    function buildTwoColView(sorted, container, headingText) {
        const half    = Math.ceil(sorted.length / 2);
        const wrapper = document.createElement('div');
        wrapper.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';

        [sorted.slice(0, half), sorted.slice(half)].forEach(col => {
            const colDiv = document.createElement('div');
            colDiv.className = 'flex flex-col gap-3';
            col.forEach(label => colDiv.appendChild(label));
            wrapper.appendChild(colDiv);
        });

        const heading = document.createElement('h2');
        heading.className = 'text-lg text-fuchsia-400 mb-3 text-center';
        heading.textContent = headingText;

        container.innerHTML = '';
        container.appendChild(heading);
        container.appendChild(wrapper);
    }

    function setView(view) {
        // Always restore labels to their original group containers first
        allLabels.forEach(label => labelParent.get(label).appendChild(label));
        form.querySelectorAll('.ro-number').forEach(el => el.classList.add('hidden'));
        form.querySelectorAll('.price-rank-icon').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('text-red-400', 'text-emerald-400');
        });
        form.querySelectorAll('.price-name').forEach(el => el.classList.add('min-w-0'));
        allGroupSections.forEach(s => s.classList.remove('hidden'));
        orderContainer.classList.add('hidden');
        priceContainer.classList.add('hidden');

        if (view === 'order') {
            const sorted = [...allLabels].sort((a, b) => {
                const ra = parseInt(a.dataset.runningOrder, 10) || 9999;
                const rb = parseInt(b.dataset.runningOrder, 10) || 9999;
                return ra - rb;
            });
            sorted.forEach(label => {
                const ro    = parseInt(label.dataset.runningOrder, 10);
                const badge = label.querySelector('.ro-number');
                if (badge) badge.textContent = ro || '—';
            });
            buildTwoColView(sorted, orderContainer, 'Running Order');
            form.querySelectorAll('.ro-number').forEach(el => el.classList.remove('hidden'));
            allGroupSections.forEach(s => s.classList.add('hidden'));
            orderContainer.classList.remove('hidden');
            setActiveBtn(viewOrderBtn);

        } else if (view === 'price') {
            const sorted = [...allLabels].sort((a, b) => {
                const pa = parseFloat(a.querySelector('.country-checkbox').dataset.price);
                const pb = parseFloat(b.querySelector('.country-checkbox').dataset.price);
                if (pb !== pa) return pb - pa;
                return a.querySelector('.price-name').textContent.trim()
                    .localeCompare(b.querySelector('.price-name').textContent.trim());
            });

            sorted.forEach(label => {
                const cb    = label.querySelector('.country-checkbox');
                const gid   = cb.dataset.groupId;
                const price = parseFloat(cb.dataset.price);
                const icon  = label.querySelector('.price-rank-icon');
                const { min, max } = groupPrices[gid];
                const isMax = price === max;
                const isMin = price === min;

                if (icon && min !== max) {
                    if (isMax) {
                        icon.classList.remove('hidden');
                        icon.classList.add('text-red-400');
                        icon.title = icon.dataset.group;
                    } else if (isMin) {
                        icon.classList.remove('hidden');
                        icon.classList.add('text-emerald-400');
                        icon.title = icon.dataset.group;
                    }
                }
            });

            buildTwoColView(sorted, priceContainer, 'Price Order');

            const iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M10 1C13.866 1 17 2.79086 17 5C17 7.20914 13.866 9 10 9C6.13401 9 3 7.20914 3 5C3 2.79086 6.13401 1 10 1ZM15.694 9.13079C16.1576 8.86588 16.6044 8.54736 17 8.17775V10C17 12.2091 13.866 14 10 14C6.13401 14 3 12.2091 3 10V8.17775C3.3956 8.54736 3.84244 8.86588 4.30604 9.13079C5.83803 10.0062 7.85433 10.5 10 10.5C12.1457 10.5 14.162 10.0062 15.694 9.13079ZM3 13.1777V15C3 17.2091 6.13401 19 10 19C13.866 19 17 17.2091 17 15V13.1777C16.6044 13.5474 16.1576 13.8659 15.694 14.1308C14.162 15.0062 12.1457 15.5 10 15.5C7.85433 15.5 5.83803 15.0062 4.30604 14.1308C3.84244 13.8659 3.3956 13.5474 3 13.1777Z" clip-rule="evenodd"/></svg>`;
            const key = document.createElement('div');
            key.className = 'mb-4 text-xs text-white/85';
            key.innerHTML = `
                <div class="xs:hidden flex justify-center items-center gap-1">
                    <span class="flex items-center text-red-400">${iconSvg}</span><span class="text-white/50 leading-none">/</span><span class="flex items-center text-emerald-400">${iconSvg}</span><span class="ml-2 leading-none translate-y-[0.5px]">Most/Least expensive in group</span>
                </div>
                <div class="hidden xs:flex justify-center items-center gap-6">
                    <span class="flex items-center gap-1.5"><span class="flex items-center text-red-400">${iconSvg}</span><span class="leading-none translate-y-[0.5px]">Most expensive in group</span></span>
                    <span class="flex items-center gap-1.5"><span class="flex items-center text-emerald-400">${iconSvg}</span><span class="leading-none translate-y-[0.5px]">Least expensive in group</span></span>
                </div>`;
            priceContainer.insertBefore(key, priceContainer.children[1]);

            allGroupSections.forEach(s => s.classList.add('hidden'));
            priceContainer.classList.remove('hidden');
            setActiveBtn(viewPriceBtn);

        } else {
            setActiveBtn(viewGroupsBtn);
        }
    }

    viewGroupsBtn.addEventListener('click', () => setView('groups'));
    viewOrderBtn.addEventListener('click',  () => setView('order'));
    viewPriceBtn.addEventListener('click',  () => setView('price'));
});
