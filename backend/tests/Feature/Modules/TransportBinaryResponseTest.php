<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class TransportBinaryResponseTest extends TestCase
{
    public function test_binary_download_bytes_are_encrypted_as_base64_and_keep_the_filename_descriptor(): void
    {
        $path = '/api/v1/_transport-test/binary-download';
        $backupBytes = "\x00\x01pharmacy-backup\xFF\xFE\x00";
        Route::middleware('api')->get($path, static fn (): Response => response($backupBytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="pharmacy-backup.bin"',
            'X-Internal-Secret' => 'must stay hidden',
        ]));
        $request = (new EncryptedTransportRequestBuilder)->buildBodyless();

        $response = $this->call('GET', $path, [], [], [], ['HTTP_X_TRANSPORT_KEY' => $request['header']])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');

        $this->assertFalse($response->headers->has('Content-Disposition'));
        $this->assertFalse($response->headers->has('X-Internal-Secret'));
        $descriptor = (new EncryptedTransportResponseReader)->read($response, $request['aesKey'], 'GET', $path);

        $this->assertSame('application/octet-stream', $descriptor['contentType']);
        $this->assertSame('base64', $descriptor['bodyEncoding']);
        $this->assertSame($backupBytes, base64_decode($descriptor['body'], true));
        $this->assertSame(
            'attachment; filename="pharmacy-backup.bin"',
            $descriptor['headers']['content-disposition'] ?? null,
        );
    }
}
