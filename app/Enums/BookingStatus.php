<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
