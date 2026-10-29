(function () {
    'use strict';
    // Upper-case and drop the spaces, dashes and dots people paste from documents.
    function normalize(value) { return value.toUpperCase().replace(/[\s\-.]+/g, ''); }
    function setup(root) {
        var form = root.querySelector('form'), input = root.querySelector('input'), button = root.querySelector('button'), result = root.querySelector('[role="status"]');
        if (!form || !input || !button || !result) return;
        function show(type, message, contact) {
            result.className = 'dvrms-result dvrms-result--' + type;
            result.textContent = '';
            var text = document.createElement('p');
            text.className = 'dvrms-result__message';
            text.textContent = message;
            result.appendChild(text);
            if (contact && (contact.url || contact.phone)) {
                var actions = document.createElement('p');
                actions.className = 'dvrms-result__actions';
                if (contact.url && /^https?:\/\//i.test(contact.url)) {
                    var link = document.createElement('a');
                    link.className = 'dvrms-cta'; link.href = contact.url; link.textContent = contact.label || contact.url;
                    actions.appendChild(link);
                }
                if (contact.phone && contact.tel) {
                    var tel = document.createElement('a');
                    tel.className = 'dvrms-phone'; tel.href = 'tel:' + contact.tel; tel.textContent = contact.phone;
                    actions.appendChild(tel);
                }
                result.appendChild(actions);
            }
        }
        async function post(params, signal) {
            var response = await fetch(DVRMS.ajaxUrl, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams(params).toString(), signal: signal });
            return response.json();
        }
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            var vin = normalize(input.value); input.value = vin;
            if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(vin)) { show('error', DVRMS.invalid); input.focus(); return; }
            button.disabled = true; input.readOnly = true; form.setAttribute('aria-busy', 'true'); show('loading', DVRMS.loading);
            // The server gives the factory at most ~25 s, so 45 s covers it with margin.
            var controller = new AbortController(), timer = setTimeout(function () { controller.abort(); }, 45000);
            try {
                var nonce = await post({ action: 'dvrms_form_nonce' }, controller.signal);
                if (!nonce.success || !nonce.data || !nonce.data.nonce) throw new Error('nonce');
                var payload = await post({ action: 'dvrms_lookup_vin', nonce: nonce.data.nonce, vin: vin }, controller.signal);
                if (!payload || !payload.data || typeof payload.data.message !== 'string') throw new Error('response');
                var type = !payload.success ? 'error' : (payload.data.type === 'match' ? 'match' : (payload.data.type === 'not_found' ? 'not-found' : 'no-match'));
                show(type, payload.data.message, payload.success ? payload.data.contact : null);
            } catch (e) { show('error', DVRMS.error); }
            finally { clearTimeout(timer); button.disabled = false; input.readOnly = false; form.removeAttribute('aria-busy'); }
        });
    }
    function init() {
        if (typeof DVRMS === 'undefined') return;
        document.querySelectorAll('[data-dvrms]').forEach(setup);
    }
    // Optimisation plugins may load this script after DOMContentLoaded has already fired.
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
