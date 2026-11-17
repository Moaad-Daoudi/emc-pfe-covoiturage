<?php
namespace App\Enum;

enum BookingStatus: string
{
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED_PASSENGER = 'CANCELLED_PASSENGER';
    case CANCELLED_DRIVER = 'CANCELLED_DRIVER';
}