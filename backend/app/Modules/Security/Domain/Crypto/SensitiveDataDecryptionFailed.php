<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

use RuntimeException;

final class SensitiveDataDecryptionFailed extends RuntimeException {}
