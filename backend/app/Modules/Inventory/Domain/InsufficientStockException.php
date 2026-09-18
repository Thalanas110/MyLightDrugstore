<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

use DomainException;

final class InsufficientStockException extends DomainException {}
