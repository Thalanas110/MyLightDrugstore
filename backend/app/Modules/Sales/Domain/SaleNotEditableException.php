<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use DomainException;

final class SaleNotEditableException extends DomainException {}
