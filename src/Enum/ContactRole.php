<?php

declare(strict_types=1);

namespace App\Enum;

enum ContactRole: string
{
    case StageManager = 'stage_manager';
    case Organizer = 'organizer';
    case Technician = 'technician';
    case Booker = 'booker';
    case Label = 'label';
    case Friend = 'friend';
    case FriendOfFriend = 'friend_of_friend';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::StageManager => 'Régisseur / régisseuse',
            self::Organizer => 'Organisation',
            self::Technician => 'Équipe technique',
            self::Booker => 'Booking / production',
            self::Label => 'Label',
            self::Friend => 'Pote',
            self::FriendOfFriend => 'Pote de pote',
            self::Other => 'Autre contact',
        };
    }
}
