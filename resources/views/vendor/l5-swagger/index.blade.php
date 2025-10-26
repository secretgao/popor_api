<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentation['api']['title'] ?? 'API Documentation' }}</title>
    <link rel="stylesheet" type="text/css" href="{{ secure_asset('docs/asset/swagger-ui.css') }}?v={{ uniqid() }}">
    <link rel="icon" type="image/png" href="{{ secure_asset('docs/asset/favicon-32x32.png') }}?v={{ uniqid() }}" sizes="32x32"/>
    <link rel="icon" type="image/png" href="{{ secure_asset('docs/asset/favicon-16x16.png') }}?v={{ uniqid() }}" sizes="16x16"/>
    <style>
    html
    {
        box-sizing: border-box;
        overflow: -moz-scrollbars-vertical;
        overflow-y: scroll;
    }

    *,
    *:before,
    *:after
    {
        box-sizing: inherit;
    }

    body {
        margin:0;
        background: #fafafa;
    }
    </style>
</head>

<body>
<div id="swagger-ui"></div>

<script src="{{ secure_asset('docs/asset/swagger-ui-bundle.js') }}?v={{ uniqid() }}" charset="UTF-8"> </script>
<script src="{{ secure_asset('docs/asset/swagger-ui-standalone-preset.js') }}?v={{ uniqid() }}" charset="UTF-8"> </script>
<script>
window.onload = function() {
    // Build a system
    const ui = SwaggerUIBundle({
        url: "{{ secure_url('docs/api-docs.json') }}",
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset
        ],
        plugins: [
            SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout",
        validatorUrl: null,
        onComplete: function() {
            // Force HTTPS for all requests
            if (window.location.protocol === 'https:') {
                const links = document.querySelectorAll('link[href^="http://"]');
                const scripts = document.querySelectorAll('script[src^="http://"]');
                
                links.forEach(link => {
                    link.href = link.href.replace('http://', 'https://');
                });
                
                scripts.forEach(script => {
                    script.src = script.src.replace('http://', 'https://');
                });
            }
        }
    });

    window.ui = ui
}
</script>
</body>
</html>
