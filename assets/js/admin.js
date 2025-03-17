jQuery(document).ready(function($) {
    $('.wc-product-search').selectWoo({
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term,
                    action: 'woocommerce_json_search_products',
                    security: woocommerce_admin.search_products_nonce
                };
            },
            processResults: function(data) {
                var terms = [];
                if (data) {
                    $.each(data, function(id, text) {
                        terms.push({ id: id, text: text });
                    });
                }
                return {
                    results: terms
                };
            }
        },
        minimumInputLength: 3
    });
}); 