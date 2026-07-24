<?php

namespace App\Enums;

enum SenderType: string
{
    case Customer = 'customer';
    case Agent = 'agent';
    case System = 'system';
}