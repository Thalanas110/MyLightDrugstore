<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

final class ArchiveMedicineAction
{
    public function __construct(private readonly MedicineArchiver $medicineArchiver) {}

    public function execute(int $medicineId): void
    {
        $this->medicineArchiver->archive($medicineId);
    }
}
