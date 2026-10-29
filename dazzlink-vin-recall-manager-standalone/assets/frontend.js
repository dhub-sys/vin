document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-dvrms]').forEach(function (root) {
        var form = root.querySelector('form'), input = root.querySelector('input'), button = root.querySelector('button'), result = root.querySelector('[role="status"]');
        function show(type, message) { result.className = 'dvrms-result dvrms-result--' + type; result.textContent = message; }
        async function post(params, signal) {
            var response = await fetch(DVRMS.ajaxUrl, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams(params).toString(), signal: signal });
            return response.json();
        }
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            var vin = input.value.trim().toUpperCase(); input.value = vin;
            if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(vin)) { show('error', DVRMS.invalid); input.focus(); return; }
            button.disabled = true; input.disabled = true; form.setAttribute('aria-busy', 'true'); show('loading', DVRMS.loading);
            var controller = new AbortController(), timer = setTimeout(function () { controller.abort(); }, 110000);
            try {
                var nonce = await post({ action: 'dvrms_form_nonce' }, controller.signal);
                if (!nonce.success || !nonce.data || !nonce.data.nonce) throw new Error('nonce');
                var payload = await post({ action: 'dvrms_lookup_vin', nonce: nonce.data.nonce, vin: vin }, controller.signal);
                if (!payload || !payload.data || typeof payload.data.message !== 'string') throw new Error('response');
                show(payload.success ? (payload.data.type === 'match' ? 'match' : 'no-match') : 'error', payload.data.message);
            } catch (e) { show('error', DVRMS.error); }
            finally { clearTimeout(timer); button.disabled = false; input.disabled = false; form.removeAttribute('aria-busy'); }
        });
    });
});
