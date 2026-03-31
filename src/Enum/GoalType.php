<?php

namespace App\Enum;

enum GoalType: string
{
    case WeightLoss = 'weight_loss';
    case MuscleGain = 'muscle_gain';
    case Maintenance = 'maintenance';
    case Endurance = 'endurance';
}
