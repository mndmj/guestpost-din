document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-din-add], [data-din-remove], [data-din-up], [data-din-down]');
    if (!button) {
        return;
    }

    var row = button.closest('.din-repeater__row');
    if (button.hasAttribute('data-din-remove')) {
        event.preventDefault();
        if (row) {
            row.remove();
        }
        return;
    }

    if (button.hasAttribute('data-din-up') && row && row.previousElementSibling) {
        event.preventDefault();
        row.parentNode.insertBefore(row, row.previousElementSibling);
        return;
    }

    if (button.hasAttribute('data-din-down') && row && row.nextElementSibling) {
        event.preventDefault();
        row.parentNode.insertBefore(row.nextElementSibling, row);
        return;
    }

    if (button.hasAttribute('data-din-add')) {
        event.preventDefault();
        var repeater = button.closest('[data-din-repeater]');
        var template = repeater && repeater.querySelector('template[data-din-template]');
        var rows = repeater && repeater.querySelector('[data-din-rows]');
        if (!template || !rows) {
            return;
        }

        var index = String(Date.now());
        var markup = template.innerHTML.replaceAll('__INDEX__', index);
        rows.insertAdjacentHTML('beforeend', markup);
    }
});

(function () {
    var configuration = window.dinChatbotVariationSearch;
    var selector = document.querySelector('[data-din-variation-selector]');
    if (!configuration || !selector) {
        return;
    }

    var search = selector.querySelector('[data-din-variation-search]');
    var results = selector.querySelector('[data-din-variation-results]');
    var selected = selector.querySelector('[data-din-variation-selected]');
    var status = selector.querySelector('[data-din-variation-status]');
    var timer;
    var requestGeneration = 0;
    var activeRequest;

    function setStatus(message) {
        status.textContent = message;
    }

    function selectedIds() {
        return Array.prototype.map.call(selected.querySelectorAll('[data-din-variation-id]'), function (item) {
            return item.getAttribute('data-din-variation-id');
        });
    }

    function addSelected(item) {
        var id = String(item.variation_id);
        if (selectedIds().indexOf(id) !== -1) {
            setStatus('This variation is already selected.');
            return;
        }

        var row = document.createElement('li');
        row.setAttribute('data-din-variation-id', id);
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'din_rule[variation_ids][]';
        input.value = id;
        var label = document.createElement('span');
        label.textContent = item.label;
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button-link-delete';
        remove.setAttribute('data-din-variation-remove', '');
        remove.textContent = 'Remove';
        row.append(input, label, remove);
        selected.appendChild(row);
        setStatus('Variation added.');
    }

    function renderResults(items) {
        results.replaceChildren();
        items.forEach(function (item) {
            var row = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'button';
            button.textContent = 'Add';
            button.addEventListener('click', function () {
                addSelected(item);
            });
            var label = document.createElement('span');
            label.textContent = item.label + ' (' + item.status + ')';
            row.append(label, document.createTextNode(' '), button);
            results.appendChild(row);
        });
    }

    search.addEventListener('input', function () {
        var generation = ++requestGeneration;
        window.clearTimeout(timer);
        if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
        }
        var term = search.value.trim();
        if (term.length < 2) {
            results.replaceChildren();
            setStatus('Type at least 2 characters to search.');
            return;
        }

        setStatus('Searching variations…');
        timer = window.setTimeout(function () {
            var url = new URL(configuration.url, window.location.origin);
            url.searchParams.set('term', term);
            url.searchParams.set('page', '1');
            var controller = new AbortController();
            activeRequest = controller;
            window.fetch(url.toString(), { headers: { 'X-WP-Nonce': configuration.nonce }, signal: controller.signal })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (generation !== requestGeneration) {
                        return;
                    }
                    activeRequest = null;
                    renderResults(Array.isArray(data.items) ? data.items : []);
                    setStatus(data.total ? data.total + ' variation(s) found.' : 'No variations found.');
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    if (generation !== requestGeneration) {
                        return;
                    }
                    activeRequest = null;
                    results.replaceChildren();
                    setStatus('Variation search is unavailable.');
                });
        }, 300);
    });

    selected.addEventListener('click', function (event) {
        var button = event.target.closest('[data-din-variation-remove]');
        if (!button) {
            return;
        }
        event.preventDefault();
        var row = button.closest('[data-din-variation-id]');
        if (row) {
            row.remove();
            setStatus('Variation removed.');
        }
    });
}());
