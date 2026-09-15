<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    .select2-container {
        display: block !important;
        width: 100% !important;
        min-width: 0;
    }
    .select2-container .select2-selection--single {
        box-sizing: border-box;
        height: 42px;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        background: #fff;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-left: 14px;
        padding-right: 28px;
        line-height: 40px;
        color: #111111;
        font-size: 14px;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #737373;
        line-height: 40px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
        top: 1px;
        right: 8px;
    }
    .select2-container--default .select2-selection--single .select2-selection__clear {
        height: 40px;
        margin-right: 20px;
        font-size: 18px;
    }
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #6f1715;
        box-shadow: 0 0 0 4px rgba(111, 23, 21, 0.15);
    }
    .select2-dropdown {
        z-index: 80;
        border-color: #e5e5e5;
        border-radius: 8px;
        overflow: hidden;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 8px 10px;
        outline: none;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field:focus {
        border-color: #6f1715;
    }
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background: #6f1715;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    function parentShown(el) {
        var node = el.parentElement;
        while (node && node !== document.body) {
            if (getComputedStyle(node).display === 'none') {
                return false;
            }
            node = node.parentElement;
        }
        return true;
    }

    function bind(el) {
        if (!el || el.dataset.select2Ready || !window.jQuery?.fn?.select2 || !parentShown(el)) {
            return;
        }

        el.dataset.select2Ready = '1';
        var $ = window.jQuery;
        var placeholder = el.querySelector('option[value=""]')?.textContent || 'Pilih';

        $(el).select2({
            width: '100%',
            dropdownParent: $(document.body),
            placeholder: placeholder,
            allowClear: true,
            language: {
                noResults: function () { return 'Tidak ada hasil'; },
                searching: function () { return 'Mencari…'; },
            },
        });

        $(el).next('.select2-container').css('width', '100%');

        $(el).on('change.select2', function () {
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });

        function watchModel() {
            if (!window.Alpine || !el._x_model || el.dataset.select2Model) {
                return false;
            }
            el.dataset.select2Model = '1';
            Alpine.effect(function () {
                var next = String(el._x_model.get() ?? '');
                if (String($(el).val() ?? '') !== next) {
                    $(el).val(next).trigger('change.select2');
                }
            });
            return true;
        }

        if (! watchModel()) {
            setTimeout(watchModel, 80);
        }
    }

    function scan() {
        document.querySelectorAll('select.js-product-select:not([data-select2-ready])').forEach(bind);
    }

    document.addEventListener('alpine:initialized', scan);
    document.addEventListener('DOMContentLoaded', function () {
        scan();
        new MutationObserver(function () {
            requestAnimationFrame(scan);
        }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
    });
})();
</script>
