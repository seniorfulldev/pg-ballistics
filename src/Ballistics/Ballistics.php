<?php

namespace Ballistics;

use Conversions\Conversions;
use Drag\Drag;

class Ballistics
{

    /**
     * Create a new class instance.
     *

     * @return void
     */
    public function __construct()
    {
        $this->conversions = new Conversions();
        $this->drag = new Drag();
    }

    public function getRangeData($weather, $target, $firearm, $round)
    {
        $rangeData = [];
        if ($weather && $target && $firearm && $round) {
            // Loop through from Range = 0 to the maximum range and display the ballistics table at each chart stepping range.
            $currentBallisticCoefficient = $this->drag->modifiedBallisticCoefficient($round->bulletBC, $weather->altitudeFeet ?? 0, $weather->temperatureDegreesFahrenheit ?? 59, $weather->barometricPressureInchesHg ?? 29.53, $weather->relativeHumidityPercent ?? 78);
            $zeroRangeYards = $firearm->zeroRangeUnits === 'Yards' ? $firearm->zeroRange : $this->conversions->metersToYards($firearm->zeroRange);
            $muzzleAngleDegrees = $this->drag->muzzleAngleDegreesForZeroRange($round->muzzleVelocityFPS, $zeroRangeYards ?? 100, $firearm->sightHeightInches ?? 1.5, $currentBallisticCoefficient);
            $currentRange = 0;
            while ($currentRange <= $target->distance) {
                $currentRangeMeters = $target->distanceUnits === 'Yards' ? $this->conversions->yardsToMeters($currentRange) : $currentRange;
                $currentRangeYards = $target->distanceUnits === 'Yards' ? $currentRange : $this->conversions->metersToYards($currentRange);
                $currentVelocityFPS =
                $this->drag->velocityFromRange($currentBallisticCoefficient, $round->muzzleVelocityFPS, $currentRangeYards) > 0
                ? $this->drag->velocityFromRange($currentBallisticCoefficient, $round->muzzleVelocityFPS, $currentRangeYards) : 0;
                $currentEnergyFtLbs = $this->drag->energy($round->bulletWeightGrains, $currentVelocityFPS);
                $currentTimeSeconds = $this->drag->time($currentBallisticCoefficient, $round->muzzleVelocityFPS, $currentVelocityFPS);
                $currentDropInches = $this->drag->drop($round->muzzleVelocityFPS, $currentVelocityFPS, $currentTimeSeconds);
                $currentVerticalPositionInches = $this->drag->verticalPosition($firearm->sightHeightInches, $muzzleAngleDegrees, $currentRangeYards, $currentDropInches);
                // Cross Winds take on full range value regardless of Slant To Target
                $currentCrossWindDriftInches = $this->drag->crossWindDrift($currentRangeYards, $currentTimeSeconds, $weather->windAngleDegrees ?? 0, $weather->windVelocityMPH ?? 0, $muzzleAngleDegrees, $round->muzzleVelocityFPS);
                $currentLeadInches = $this->drag->lead($target->speedMPH, $currentTimeSeconds);
                $slantDropInches = $currentDropInches * (1 - cos($this->conversions->degreesToRadians($target->slantDegrees)));
                $range = array(
                    'rangeMeters' => $currentRangeMeters,
                    'rangeYards' => $currentRangeYards,
                    'velocityFPS' => $currentVelocityFPS,
                    'energyFtLbs' => $currentEnergyFtLbs,
                    'timeSeconds' => $currentTimeSeconds,
                    'dropInches' => $currentDropInches,
                    'verticalPositionInches' => -$currentVerticalPositionInches, // Go negative to reflect how much scope dial up is needed
                    'crossWindDriftInches' => $currentCrossWindDriftInches,
                    'leadInches' => $currentLeadInches,
                    'slantDegrees' => $target->slantDegrees,
                    // All the remaining properties are computed
                    'verticalPositionMil' => $this->conversions->inchesToMil(-$currentVerticalPositionInches, $currentRangeYards),
                    'verticalPositionMoA' => $this->conversions->inchesToMinutesOfAngle(-$currentVerticalPositionInches, $currentRangeYards),
                    'verticalPositionIPHY' => $this->conversions->inchesToIPHY(-$currentVerticalPositionInches, $currentRangeYards),
                    'crossWindDriftMil' => $this->conversions->inchesToMil($currentCrossWindDriftInches, $currentRangeYards),
                    'crossWindDriftMoA' => $this->conversions->inchesToMinutesOfAngle($currentCrossWindDriftInches, $currentRangeYards),
                    'crossWindDriftIPHY' => $this->conversions->inchesToIPHY($currentCrossWindDriftInches, $currentRangeYards),
                    'leadMil' => $this->conversions->inchesToMil($currentLeadInches, $currentRangeYards),
                    'leadMoA' => $this->conversions->inchesToMinutesOfAngle($currentLeadInches, $currentRangeYards),
                    'leadIPHY' => $this->conversions->inchesToIPHY($currentLeadInches, $currentRangeYards),
                    'slantDropInches' => $slantDropInches,
                    'slantMil' => $this->conversions->inchesToMil($slantDropInches, $currentRangeYards),
                    'slantMoA' => $this->conversions->inchesToMinutesOfAngle($slantDropInches, $currentRangeYards),
                    'slantIPHY' => $this->conversions->inchesToIPHY($slantDropInches, $currentRangeYards),
                );
                $rangeData[] = $range;
                $currentRange += $target->chartStepping;
            }
        }
        return $rangeData;
    }
}
