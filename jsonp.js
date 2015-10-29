if (typeof wp_jsonp === 'undefined') {
    var wp_jsonp = function (event, method, params, callbackFunc) {
        // data needed to send jsonp
        var data = {
            action: event,	// wp ajax action
            ajaxSSLNonce: wp_jsonp_vars.wpAJAXNonce, // nonce
            method: method, // server has switch/case for processing
            params: params // data to be processed
        };

        jQuery.ajax({
            type: "GET", // this is the essence of jsonp
            url: wp_jsonp_vars.ajaxurl, // wp ajax url
            cache: false, // to ensure proper data response
            dataType: "jsonp", // jsonp
            crossDomain: true, // enable ssl/nonssl
            data: data, // data to be sent

            success: function (response) {
                //console.log('success', response);
                // your callback function
                callbackFunc(response);
            },

            complete: function (response) {
                //console.log('complete', response);
            },

            error: function (response) {
                console.log('error', response);
            }
        });
    };
}