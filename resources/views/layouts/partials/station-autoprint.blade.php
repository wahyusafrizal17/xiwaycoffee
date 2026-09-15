<script>
(function () {
    var pendingUrl = @json(route('print-jobs.index', ['station' => request()->routeIs('bar.*') ? 'bar' : 'kitchen']));
    var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var busy = false;
    var seen = {};

    async function poll() {
        if (busy || !window.RasaQz?.printRaw) {
            return;
        }
        busy = true;
        try {
            var res = await fetch(pendingUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) {
                return;
            }
            var data = await res.json();
            var jobs = data.jobs || [];
            for (var i = 0; i < jobs.length; i++) {
                var job = jobs[i];
                if (seen[job.order_id] || !job.escpos) {
                    continue;
                }
                await window.RasaQz.printRaw(data.printer || job.printer?.name || '', job.escpos);
                seen[job.order_id] = true;
                await fetch(job.ack_url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
            }
        } catch (error) {
            console.warn(error);
        } finally {
            busy = false;
        }
    }

    poll();
    setInterval(poll, 4000);
})();
</script>
