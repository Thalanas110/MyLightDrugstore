<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\StrictInputRequest;
use Tests\TestCase;

final class UnknownInputFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/v1/_contract-test/strict-input', static function (StrictInputRequest $request): JsonResponse {
            return response()->json(['accepted' => true]);
        });
    }

    public function test_valid_nested_request_fields_continue_to_the_action(): void
    {
        $response = $this->postJson('/api/v1/_contract-test/strict-input', [
            'name' => 'Example medicine',
            'items' => [
                ['quantity' => 2],
            ],
        ]);

        $response->assertOk()->assertExactJson(['accepted' => true]);
    }

    public function test_unrecognized_top_level_fields_return_validation_failed(): void
    {
        $response = $this->postJson('/api/v1/_contract-test/strict-input', [
            'name' => 'Example medicine',
            'items' => [
                ['quantity' => 2],
            ],
            'unexpected' => 'must not be mass assigned',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.unexpected.0', 'The unexpected field is prohibited.');
    }

    public function test_unrecognized_nested_fields_return_validation_failed(): void
    {
        $response = $this->postJson('/api/v1/_contract-test/strict-input', [
            'name' => 'Example medicine',
            'items' => [
                ['quantity' => 2, 'unexpected' => 'must be rejected'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(
            ['items.0.unexpected' => ['The items.0.unexpected field is prohibited.']],
            $response->json('error.details'),
        );
    }
}
