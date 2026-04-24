<?php

namespace App\Enum;

enum MaterialType: string
{
    case CONCRETE = 'бетон';
    case BRICK = 'кирпич';
    case WOOD = 'дерево';
    case STEEL = 'сталь';
    case GLASS = 'стекло';
    case PLASTIC = 'пластик';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
