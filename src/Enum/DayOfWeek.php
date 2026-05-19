<?php

namespace App\Enum;

enum DayOfWeek: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    public static function fromPhpDayOfWeek(int $n): self
    {
        return match ($n) {
            0 => self::Sunday,
            1 => self::Monday,
            2 => self::Tuesday,
            3 => self::Wednesday,
            4 => self::Thursday,
            5 => self::Friday,
            6 => self::Saturday,
            default => throw new \ValueError("Invalid day-of-week index: $n"),
        };
    }

    public static function today(?\DateTimeInterface $now = null): self
    {
        $now ??= new \DateTimeImmutable();
        return self::fromPhpDayOfWeek((int) $now->format('w'));
    }
}
