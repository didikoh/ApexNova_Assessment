<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ApexNova · Inventory API reference</title>
    <link rel="stylesheet" href="{{ asset('swagger-assets/swagger-ui.css') }}">
    <style>
        body { margin: 0; background: #fafbf9; }
        header { background: #122a2b; padding: 20px 32px; color: #fff; font: 15px system-ui; }
        header p { margin-bottom: 0; line-height: 1.5; }
        #swagger-ui { max-width: 1280px; margin: auto; }
    </style>
</head>
<body>
    <header>
        Question 2 / Inventory API reference
        <p>Execute POST /login, copy the returned token, then select Authorize to test protected endpoints.</p>
    </header>
    <div id="swagger-ui"></div>
    <script src="{{ asset('swagger-assets/swagger-ui-bundle.js') }}"></script>
    <script>
        SwaggerUIBundle({
            url: {{ Illuminate\Support\Js::from(route('swagger.spec')) }},
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis],
            persistAuthorization: false,
            validatorUrl: null,
        });
    </script>
</body>
</html>
