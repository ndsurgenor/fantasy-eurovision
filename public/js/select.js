document.addEventListener('DOMContentLoaded', () => {
    const form       = document.getElementById('team-form');
    const BUDGET     = parseFloat(form.dataset.budget);
    const REQUIRED   = parseInt(form.dataset.requiredPicks, 10);
    const checkboxes = [...form.querySelectorAll('.country-checkbox')];
    const submitBtn  = document.getElementById('submit-btn');
    const picksCount = document.getElementById('picks-count');
    const budgetSpent = document.getElementById('budget-spent');
    const budgetRem  = document.getElementById('budget-remaining');

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

        // Update counters
        picksCount.textContent  = count;
        budgetSpent.textContent = `€${spent.toFixed(1)}m`;
        budgetRem.textContent   = `${remaining < 0 ? '-' : ''}€${Math.abs(remaining).toFixed(1)}m`;

        // Colour remaining amount
        budgetRem.classList.remove('text-red-400', 'text-yellow-400', 'text-emerald-400', 'text-white');
        if (remaining < 0) {
            budgetRem.classList.add('text-red-400');
        } else if (remaining < 5) {
            budgetRem.classList.add('text-yellow-400');
        } else {
            budgetRem.classList.add('text-emerald-400');
        }

        // Update per-group displays and validity
        let valid = (count === REQUIRED) && (remaining >= 0);

        document.querySelectorAll('[data-group-container]').forEach(section => {
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
});
