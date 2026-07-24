<?php

namespace App\Enums;

enum DraftStatus: string
{
    case Active = 'active';
    case Regenerated = 'regenerated';
    case Approved = 'approved';
    case Sent = 'sent';
    case Discarded = 'discarded';
}