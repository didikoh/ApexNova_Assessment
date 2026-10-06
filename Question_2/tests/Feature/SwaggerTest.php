<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerTest extends TestCase
{
    public function test_swagger_page_is_public_and_uses_local_assets_and_specification(): void
    {
        $this->get('/swagger')->assertOk()
            ->assertSee('swagger-assets/swagger-ui.css')
            ->assertSee('swagger-assets/swagger-ui-bundle.js')
            ->assertSee('\/swagger\/openapi.json', false)
            ->assertSee('persistAuthorization: false', false)
            ->assertSee('validatorUrl: null', false);

        foreach (['swagger-ui.css', 'swagger-ui-bundle.js', 'LICENSE', 'NOTICE'] as $asset) {
            $this->assertFileExists(public_path('swagger-assets/'.$asset));
        }
    }

    public function test_specification_uses_the_current_laravel_origin_and_preserves_api_contract(): void
    {
        $spec = json_decode(file_get_contents(base_path('openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $response = $this->getJson('http://localhost:8000/swagger/openapi.json')->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('servers.0.url', 'http://localhost:8000/api');

        $this->assertSame($spec['paths'], $response->json('paths'));
        $this->assertSame($spec['components'], $response->json('components'));
        $this->assertSame($spec['security'], $response->json('security'));
    }
}
