<?php

declare(strict_types=1);

namespace App\Modules;

enum ModuleName: string
{
    case Identity = 'identity';
    case Catalog = 'catalog';
    case Inventory = 'inventory';
    case Sales = 'sales';
    case Users = 'users';
    case Reporting = 'reporting';
    case Operations = 'operations';
    case Transport = 'transport';
    case Security = 'security';
}
