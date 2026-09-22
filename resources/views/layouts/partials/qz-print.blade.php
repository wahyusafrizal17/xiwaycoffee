<script>
(function () {
    function notice(message) {
        if (window.posNotice) {
            window.posNotice(message);
            return;
        }
        console.warn(message);
    }

    async function connect() {
        if (typeof qz === 'undefined') {
            throw new Error('QZ Tray belum dimuat. Install dan jalankan QZ Tray.');
        }
        if (qz.websocket.isActive()) {
            return;
        }
        await qz.websocket.connect();
    }

    async function resolvePrinter(preferred) {
        var listed = await qz.printers.find();
        var names = Array.isArray(listed) ? listed : [listed];
        if (preferred && names.indexOf(preferred) !== -1) {
            return preferred;
        }
        if (preferred) {
            var needle = String(preferred).toLowerCase();
            var fuzzy = names.find(function (name) {
                return (name || '').toLowerCase().indexOf(needle) !== -1;
            });
            if (fuzzy) {
                return fuzzy;
            }
        }
        var match = names.find(function (name) {
            return /gezhi|eppos|thermal|receipt|80\s?mm/i.test(name || '');
        });
        return match || names[0] || qz.printers.getDefault();
    }

    function rawConfig(name) {
        return qz.configs.create(name, {
            encoding: 'ISO-8859-1',
            rasterize: false,
            altPrinting: true,
            jobName: 'Xiway Pos ESC/POS'
        });
    }

    async function printRaw(printerName, base64) {
        if (!base64) {
            throw new Error('Data struk kosong.');
        }
        await connect();
        var name = await resolvePrinter(printerName || window.RasaQz?.printer);
        await qz.print(rawConfig(name), [{
            type: 'raw',
            format: 'command',
            flavor: 'base64',
            data: base64
        }]);
        return true;
    }

    async function jobs(list, options) {
        options = options || {};
        for (var i = 0; i < list.length; i++) {
            var job = list[i];
            var station = job.payload?.station || job.printer?.station;
            if (options.skipCashier && station === 'cashier') {
                continue;
            }
            if (!job.escpos) {
                continue;
            }
            var name = job.printer?.name || job.payload?.printer?.name || options.printer;
            await printRaw(name, job.escpos);
        }
    }

    async function receipt(payload) {
        var printer = payload.qz_printer || payload.printer || window.RasaQz?.printer;
        try {
            if (!payload.receipt_escpos) {
                throw new Error('Struk ESC/POS tidak tersedia.');
            }
            await printRaw(printer, payload.receipt_escpos);
            return true;
        } catch (error) {
            notice(error.message || 'QZ Tray gagal. Jangan cetak lewat Chrome — itu yang bikin kertas PostScript.');
            return false;
        }
    }

    async function tickets(payload) {
        try {
            await jobs(payload.print_jobs || [], { printer: payload.qz_printer || window.RasaQz?.printer });
            return true;
        } catch (error) {
            notice(error.message || 'QZ Tray gagal.');
            return false;
        }
    }

    async function checkout(payload) {
        var receiptOk = await receipt(payload);
        if (!payload.prep_ticket_escpos) {
            return receiptOk;
        }
        try {
            await printRaw(payload.qz_printer || window.RasaQz?.printer, payload.prep_ticket_escpos);
            return receiptOk;
        } catch (error) {
            notice(error.message || 'Tiket dapur/bar gagal dicetak.');
            return false;
        }
    }

    window.RasaQz = window.RasaQz || {};
    window.RasaQz.printReceipt = receipt;
    window.RasaQz.printTickets = tickets;
    window.RasaQz.printCheckout = checkout;
    window.RasaQz.printRaw = printRaw;
    window.RasaQz.connect = connect;
})();
</script>
