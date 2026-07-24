<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case NewCustomer = 'new_customer';
    case ExistingCustomer = 'existing_customer';
    case Unknown = 'unknown';
}
