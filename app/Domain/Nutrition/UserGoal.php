<?php

declare(strict_types=1);

namespace App\Domain\Nutrition;

enum UserGoal: string
{
    case Athlete = 'athlete';
    case WeightLoss = 'weight_loss';
    case Pregnancy = 'pregnancy';
    case Sustainability = 'sustainability';
    case None = 'none';

    public static function fromString(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::None;
        }

        return self::tryFrom($value) ?? self::None;
    }

    public function label(): string
    {
        return match ($this) {
            self::Athlete => 'Deportista',
            self::WeightLoss => 'Bajar peso',
            self::Pregnancy => 'Embarazo',
            self::Sustainability => 'Sostenibilidad',
            self::None => 'Sin preferencia',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Athlete => '💪',
            self::WeightLoss => '🥗',
            self::Pregnancy => '🤰',
            self::Sustainability => '🌱',
            self::None => '😐',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Athlete => 'Quiero un pescado que me ayude con el ejercicio físico o recuperación muscular.',
            self::WeightLoss => 'Busco opciones bajas en calorías y grasas para perder peso.',
            self::Pregnancy => 'Necesito un pescado seguro para embarazo (bajo en mercurio, rico en Omega-3).',
            self::Sustainability => 'Me importa el impacto ambiental y la pesca responsable.',
            self::None => 'Solo quiero información general del pescado.',
        };
    }
}
