// Améliorations progressives : le site reste utilisable sans JavaScript.
(function () {
    'use strict';

    var counter = 0;

    function addDays(iso, days) {
        var d = new Date(iso + 'T12:00:00');
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + days);
        return d.toISOString().slice(0, 10);
    }

    function addRow(button) {
        var list = document.getElementById(button.getAttribute('data-add-row'));
        var tpl = document.getElementById(button.getAttribute('data-add-row') + '-tpl');
        if (!list || !tpl) return;
        var html = tpl.innerHTML.split('__INDEX__').join('n' + Date.now() + '_' + (counter++));
        var rows = list.querySelectorAll('.row-item');
        var last = rows.length ? rows[rows.length - 1] : null;
        list.insertAdjacentHTML('beforeend', html);
        var added = list.lastElementChild;

        // Nouvelle date : jour suivant la précédente, mêmes horaires.
        if (button.getAttribute('data-kind') === 'date' && last) {
            var prevDate = last.querySelector('input[type=date]');
            var prevTimes = last.querySelector('input[type=text]');
            if (prevDate && prevDate.value) added.querySelector('input[type=date]').value = addDays(prevDate.value, 1);
            if (prevTimes && prevTimes.value) added.querySelector('input[type=text]').value = prevTimes.value;
        }
        var first = added.querySelector('input');
        if (first) first.focus();
    }

    function removeRow(button) {
        var row = button.closest('.row-item');
        if (!row) return;
        var list = row.parentElement;
        if (list.querySelectorAll('.row-item').length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
        }
    }

    function copy(button) {
        var input = document.getElementById(button.getAttribute('data-copy'));
        if (!input) return;
        var done = function () {
            var original = button.textContent;
            button.textContent = button.getAttribute('data-copied') || '✓';
            setTimeout(function () { button.textContent = original; }, 1800);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(done, function () { input.select(); });
        } else {
            input.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* sélection laissée à l'utilisateur */ }
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest('[data-add-row], [data-remove-row], [data-copy]');
        if (!target) return;
        if (target.hasAttribute('data-add-row')) addRow(target);
        else if (target.hasAttribute('data-remove-row')) removeRow(target);
        else if (target.hasAttribute('data-copy')) copy(target);
    });

    document.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) event.preventDefault();
    });

    // Sélection automatique des liens à copier.
    document.querySelectorAll('.copy-field input[readonly]').forEach(function (input) {
        input.addEventListener('focus', function () { input.select(); });
    });
})();
