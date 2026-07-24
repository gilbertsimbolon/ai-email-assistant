<?php

namespace App\Enums;

enum MessageType: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
}