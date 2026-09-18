<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class AuthenticationControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $lastTransportKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('data_protection.keys', [
            'current' => [
                'key_id' => 'test-data-2026-01',
                'key_base64' => base64_encode(str_repeat('d', 32)),
            ],
            'retiring' => [],
        ]);
        config()->set('data_protection.username_lookup_key_base64', base64_encode(str_repeat('l', 32)));
    }

    public function test_it_issues_csrf_tokens_authenticates_and_returns_the_current_profile(): void
    {
        $user = $this->createUser();
        $csrfToken = $this->requestCsrfToken();

        $login = $this->encryptedRequest(
            'POST',
            '/api/v1/auth/login',
            ['username' => '  PHARMACY.STAFF_1 ', 'password' => 'CorrectHorseBatteryStaple!2026'],
            $csrfToken,
        )->assertOk();
        $loginBody = $this->responseBody($login, 'POST', '/api/v1/auth/login');

        $this->assertSame([
            'id' => $user->getKey(),
            'username' => 'pharmacy.staff_1',
            'fullName' => 'Test Staff',
            'role' => 'staff',
            'active' => true,
        ], json_decode($loginBody, true, 512, JSON_THROW_ON_ERROR)['data']);

        $currentUser = $this->encryptedRequest('GET', '/api/v1/auth/me')->assertOk();
        $currentUserBody = $this->responseBody($currentUser, 'GET', '/api/v1/auth/me');
        $this->assertSame($loginBody, $currentUserBody);
    }

    public function test_it_rejects_invalid_credentials_with_a_generic_unauthenticated_response(): void
    {
        $this->createUser();
        $csrfToken = $this->requestCsrfToken();

        $response = $this->encryptedRequest(
            'POST',
            '/api/v1/auth/login',
            ['username' => 'pharmacy.staff_1', 'password' => 'incorrect-password'],
            $csrfToken,
        )->assertUnauthorized();

        $body = $this->responseBody($response, 'POST', '/api/v1/auth/login');
        $this->assertSame('unauthenticated', json_decode($body, true, 512, JSON_THROW_ON_ERROR)['error']['code']);
    }

    public function test_it_rejects_login_requests_without_a_valid_csrf_token(): void
    {
        $response = $this->encryptedRequest(
            'POST',
            '/api/v1/auth/login',
            ['username' => 'pharmacy.staff_1', 'password' => 'CorrectHorseBatteryStaple!2026'],
        )->assertStatus(419);

        $body = $this->responseBody($response, 'POST', '/api/v1/auth/login');
        $this->assertSame('csrf_token_mismatch', json_decode($body, true, 512, JSON_THROW_ON_ERROR)['error']['code']);
    }

    public function test_it_logs_out_and_invalidates_the_authenticated_session(): void
    {
        $this->createUser();
        $csrfToken = $this->requestCsrfToken();
        $this->encryptedRequest(
            'POST',
            '/api/v1/auth/login',
            ['username' => 'pharmacy.staff_1', 'password' => 'CorrectHorseBatteryStaple!2026'],
            $csrfToken,
        )->assertOk();

        $csrfToken = $this->requestCsrfToken();
        $this->encryptedRequest('POST', '/api/v1/auth/logout', [], $csrfToken)->assertNoContent();
        $this->encryptedRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_it_changes_the_authenticated_users_password_after_verifying_the_current_password(): void
    {
        $user = $this->createUser();
        $this->authenticateUser();
        $csrfToken = $this->requestCsrfToken();
        $newPassword = 'ANewSecurePassword!2026';

        $this->encryptedRequest('POST', '/api/v1/auth/change-password', [
            'currentPassword' => 'CorrectHorseBatteryStaple!2026',
            'newPassword' => $newPassword,
        ], $csrfToken)->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->getAuthPassword()));
        $this->assertFalse(Hash::check('CorrectHorseBatteryStaple!2026', $user->getAuthPassword()));
        $this->assertNotNull($user->password_changed_at);
        $this->encryptedRequest('GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_it_does_not_change_the_password_when_the_current_password_is_wrong(): void
    {
        $user = $this->createUser();
        $this->authenticateUser();
        $csrfToken = $this->requestCsrfToken();

        $response = $this->encryptedRequest('POST', '/api/v1/auth/change-password', [
            'currentPassword' => 'incorrect-password',
            'newPassword' => 'ANewSecurePassword!2026',
        ], $csrfToken)->assertUnprocessable();
        $body = $this->responseBody($response, 'POST', '/api/v1/auth/change-password');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertArrayHasKey('currentPassword', $payload['error']['details']);
        $this->assertTrue(Hash::check('CorrectHorseBatteryStaple!2026', $user->fresh()->getAuthPassword()));
    }

    public function test_it_rejects_passwords_that_repeat_the_current_value_or_are_too_short(): void
    {
        $this->createUser();
        $this->authenticateUser();
        $csrfToken = $this->requestCsrfToken();

        foreach (['CorrectHorseBatteryStaple!2026', 'too-short'] as $newPassword) {
            $response = $this->encryptedRequest('POST', '/api/v1/auth/change-password', [
                'currentPassword' => 'CorrectHorseBatteryStaple!2026',
                'newPassword' => $newPassword,
            ], $csrfToken)->assertUnprocessable();
            $body = $this->responseBody($response, 'POST', '/api/v1/auth/change-password');
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('validation_failed', $payload['error']['code']);
            $this->assertArrayHasKey('newPassword', $payload['error']['details']);
        }
    }

    private function requestCsrfToken(): string
    {
        $this->withSession(['_token' => 'test-csrf-token']);
        $response = $this->encryptedRequest('GET', '/api/v1/auth/csrf')->assertOk();
        $body = $this->responseBody($response, 'GET', '/api/v1/auth/csrf');
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $token = $payload['data']['csrfToken'] ?? null;

        $this->assertIsString($token);

        return $token;
    }

    private function authenticateUser(): void
    {
        $csrfToken = $this->requestCsrfToken();
        $this->encryptedRequest(
            'POST',
            '/api/v1/auth/login',
            ['username' => 'pharmacy.staff_1', 'password' => 'CorrectHorseBatteryStaple!2026'],
            $csrfToken,
        )->assertOk();
    }

    /** @param array<string, mixed> $payload */
    private function encryptedRequest(string $method, string $path, array $payload = [], ?string $csrfToken = null): TestResponse
    {
        $builder = new EncryptedTransportRequestBuilder;
        $headers = [];

        if ($payload === []) {
            $key = $builder->buildBodyless();
            $body = '';
        } else {
            $key = $builder->build($method, $path, $payload);
            $body = json_encode($key['envelope'], JSON_THROW_ON_ERROR);
        }

        if ($csrfToken !== null) {
            $headers['HTTP_X_CSRF_TOKEN'] = $csrfToken;
        }

        $headers['HTTP_X_TRANSPORT_KEY'] = $key['header'];
        $this->lastTransportKey = $key['aesKey'];

        return $this->call($method, $path, [], [], [], $headers + ['CONTENT_TYPE' => 'application/json'], $body);
    }

    private function responseBody(TestResponse $response, string $method, string $path): string
    {
        return (new EncryptedTransportResponseReader)
            ->read($response, $this->lastTransportKey, $method, $path)['body'];
    }

    private function createUser(): User
    {
        $user = new User;
        $user->setUsername(new Username('pharmacy.staff_1'));
        $user->setFullName(new FullName('Test Staff'));
        $user->password = 'CorrectHorseBatteryStaple!2026';
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        return $user;
    }
}
