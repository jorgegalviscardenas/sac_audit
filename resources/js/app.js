import './bootstrap';
import $ from 'jquery';

// Make jQuery available globally
window.$ = window.jQuery = $;

// Import Select2 after jQuery is set globally
import select2 from 'select2';
import '../css/select2-custom.css';

// Initialize Select2 plugin on jQuery
select2($);

// Initialize Select2 on page load
document.addEventListener('DOMContentLoaded', function() {
    $('.select2-tenant').select2({
        width: '100%',
        placeholder: function() {
            return $(this).data('placeholder');
        },
        allowClear: true,
        ajax: {
            url: '/user-on-session/tenants',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return {
                    results: data.results,
                    pagination: {
                        more: data.pagination.more
                    }
                };
            },
            cache: true
        },
        minimumInputLength: 0
    });
});
