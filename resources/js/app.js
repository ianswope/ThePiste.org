import './bootstrap';

// Instant client-side calendar filtering — no server round-trip, mirrors the prototype.
function applyFilter(f) {
    document.querySelectorAll('.card').forEach((c) => {
        const d = c.dataset;
        let show = false;
        if (f === 'all') show = true;
        else if (f === 'plan') show = d.plan === '1';
        else if (f === 'official') show = d.local !== '1';
        else if (f === 'nonneg') show = d.nonneg === '1';
        else if (f === 'goals') show = d.goals === '1';
        else if (f === 'nac') show = d.tier === 'nac';
        else if (f === 'priority') show = ['nac', 'home', 'priority'].includes(d.tier);
        else if (f === 'drive') show = ['nac', 'home', 'priority', 'drive'].includes(d.tier);
        else if (f === 'fly') show = d.tier === 'fly';
        else if (f === 'region') show = d.region === '1';
        else if (f === 'home') show = d.home === '1';
        c.classList.toggle('hidden', !show);
    });

    document.querySelectorAll('.mb').forEach((mb) => {
        const visible = mb.querySelectorAll('.card:not(.hidden)').length;
        mb.classList.toggle('all-hidden', visible === 0);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.fb').forEach((b) => {
        b.addEventListener('click', () => {
            document.querySelectorAll('.fb').forEach((x) => x.classList.remove('active'));
            b.classList.add('active');
            applyFilter(b.dataset.f);
        });
    });
});
