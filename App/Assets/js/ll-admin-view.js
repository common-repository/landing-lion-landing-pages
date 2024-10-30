/**
 * Created by mcarlson on 11/21/16.
 */

(function ($) {

    function receiveMessage(event) {
        var origin = event.origin || '';
        var data = event.data || {};
        var allowedOrigin = LLPluginConfig.allowedOrigin || "";
        var devOrigin = LLPluginConfig.allowedDevOrigin || "https://wp.dev.landinglion.com";

        if (allowedOrigin !== "" && (origin === allowedOrigin || origin === devOrigin)) {
            if (data.method) {
                if (data.method === 'createWordPressPageMapping') {
                    createWordPressPageMapping(data.body, event.source.window, event.origin);
                }
                else if (data.method === 'updateWordPressPageMapping') {
                    updateWordPressPageMapping(data.body, event.source.window, event.origin);
                }
                else if (data.method === 'deleteWordPressPageMapping') {
                    deleteWordPressPageMapping(data.body, event.source.window, event.origin);
                }
                else if (data.method === 'fetchWordPressPageSlugs') {
                    fetchWordPressPageSlugs(event.source.window, event.origin);
                }
                else if (data.method === 'fetchWordPressPageMappings') {
                    fetchPageMappings(event.source.window, event.origin);
                }
                else {
                    console.log("PLUGIN:: Invalid Method");
                    console.log("PLUGIN:: Message Method: ", event.origin);
                }
            }
        }
        else if (origin === "*") {
            if (data.method && data.method === 'init') {
                sendInitMessage(event.source.window, event.origin);
            }
        }
        else{
            console.log("PLUGIN:: Message Origin: ", event.origin);
        }
    }

    window.addEventListener("message", receiveMessage, false);

    function sendInitMessage(childWindow, childOrigin) {
        var message = {"method":"init"};
        childWindow.postMessage(message, childOrigin);
    }

    function fetchPageMappings(childWindow, childOrigin) {
        $.ajax({
            type: 'GET',
            url: LLPluginConfig.ajaxurl,
            data: {action: 'get_ll_page_mappings'},
            success: function (response) {
                var pageMappings = response.pageMappings;
                var mappings = [];

                for (mappingIndex in pageMappings) {
                    if (pageMappings[mappingIndex]) {
                        var mapping = pageMappings[mappingIndex];
                        var newMapping = {
                            'id': parseInt(mapping.ID),
                            'status': mapping.status,
                            'LLPageStatus': convertLLPageStatus(parseInt(mapping.ll_page_status)),
                            'LLPageId': parseInt(mapping.ll_page_id),
                            'LLStaticUrl': mapping.ll_static_url,
                            'WPPageId': parseInt(mapping.wp_page_id),
                            'WPPageStatus': mapping.wp_page_status,
                            'WPPageTitle': mapping.wp_page_title,
                            'WPPageSlug': mapping.wp_page_slug,
                            'WPPageUrl': mapping.wp_page_url
                        };
                        mappings.push(newMapping);
                    }
                }
                var message = {
                    'method': 'fetchWordPressPageMappings',
                    'data': mappings
                };

                childWindow.postMessage(message, childOrigin);
            }
        });
    }

    function fetchWordPressPageSlugs(childWindow, childOrigin) {
        $.ajax({
            type: 'GET',
            url: LLPluginConfig.ajaxurl,
            data: {action: 'get_wordpress_page_slugs'},
            success: function (response) {
                var slugs = response.pageSlugs || [];
                var message = {
                    'method': 'fetchWordPressPageSlugs',
                    'data': slugs
                };

                childWindow.postMessage(message, childOrigin);
            }
        });
    }

    function createWordPressPageMapping(new_page, childWindow, childOrigin) {
        var data = {page: new_page, action: 'll_create_page_mapping'};

        $.ajax({
            type: 'POST',
            url: LLPluginConfig.ajaxurl,
            data: data,
            success: function (response) {
                var mapping = response.page;
                var newMapping = {
                    'id': parseInt(mapping.ID),
                    'LLPageStatus': convertLLPageStatus(parseInt(mapping.ll_page_status)),
                    'LLPageId': parseInt(mapping.ll_page_id),
                    'LLStaticUrl': mapping.ll_static_url,
                    'WPPageId': parseInt(mapping.wp_page_id),
                    'WPPageStatus': mapping.wp_page_status,
                    'WPPageTitle': mapping.wp_page_title,
                    'WPPageSlug': mapping.wp_page_slug,
                    'WPPageUrl': mapping.wp_page_url
                };

                var message = {
                    'method': 'createWordPressPageMapping',
                    'data': newMapping
                };

                childWindow.postMessage(message, childOrigin);
            }
        });
    }

    function updateWordPressPageMapping(mapping, childWindow, childOrigin) {
        var data = {page: mapping, action: 'll_update_page_mapping'};

        $.ajax({
            type: 'POST',
            url: LLPluginConfig.ajaxurl,
            data: data,
            success: function (response) {
                var mapping = response.page;
                var newMapping = {
                    'id': parseInt(mapping.id),
                    'LLPageStatus': convertLLPageStatus(parseInt(mapping.ll_page_status)),
                    'LLPageId': parseInt(mapping.ll_page_id),
                    'LLStaticUrl': mapping.ll_static_url,
                    'WPPageId': parseInt(mapping.wp_page_id),
                    'WPPageStatus': mapping.wp_page_status,
                    'WPPageTitle': mapping.wp_page_title,
                    'WPPageSlug': mapping.wp_page_slug,
                    'WPPageUrl': mapping.wp_page_url
                };

                var message = {
                    'method': 'updateWordPressPageMapping',
                    'data': newMapping
                };

                childWindow.postMessage(message, childOrigin);
            }
        });
    }

    function deleteWordPressPageMapping(page_id, childWindow, childOrigin) {
        var data = {page: page_id, action: 'll_delete_page_mapping'};

        $.ajax({
            type: 'POST',
            url: LLPluginConfig.ajaxurl,
            data: data,
            success: function (response) {
                var success = response.success;

                var message = {
                    'method': 'deleteWordPressPageMapping',
                    'data': {'success': success }
                };

                childWindow.postMessage(message, childOrigin);
            }
        });
    }

    function convertLLPageStatus(pageStatus){
        if ( pageStatus == 1) {
            return "dead";
        }
        else if (pageStatus == 0 ){
            return "active";
        }
    }


})(jQuery);
