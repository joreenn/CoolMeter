<?php
namespace App\Services;
use Carbon\CarbonImmutable;
class EnergyEstimator {
    public function calculate(array $input, array $settings, ?CarbonImmutable $clipStart = null, ?CarbonImmutable $clipEnd = null): array {
        $start = CarbonImmutable::parse($input['started_at'], 'Asia/Manila');
        $end = CarbonImmutable::parse($input['ended_at'], 'Asia/Manila');
        $eco = empty($input['eco_at']) ? null : CarbonImmutable::parse($input['eco_at'], 'Asia/Manila');
        $from = $clipStart && $clipStart->gt($start) ? $clipStart : $start;
        $to = $clipEnd && $clipEnd->lt($end) ? $clipEnd : $end;
        $factor = ['comfortable' => 0.5, 'warm' => 1, 'hot' => 1.5][$input['room_heat']];
        $coolUntil = $start->addSeconds((int) round($settings['cooldown_minutes'] * $factor * 60));
        $points = [$start->timestamp, $end->timestamp, min($end->timestamp, $coolUntil->timestamp)];
        if ($eco) { $points[] = $eco->timestamp; }
        $points = array_unique($points); sort($points);
        $kwh = 0; $coolMinutes = 0; $ecoMinutes = 0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $a = max($points[$i], $from->timestamp); $b = min($points[$i + 1], $to->timestamp);
            if ($b <= $a) { continue; }
            $cooling = $a < $coolUntil->timestamp; $isEco = $eco && $a >= $eco->timestamp;
            $watts = $cooling ? $settings['rated_watts'] : $settings[$isEco ? 'eco_watts' : 'normal_watts'];
            $kwh += min($watts, $settings['max_watts']) / 1000 * ($b - $a) / 3600;
            $coolMinutes += $cooling ? ($b - $a) / 60 : 0;
            $ecoMinutes += $isEco ? ($b - $a) / 60 : 0;
        }
        $hours = max(0, $to->timestamp - $from->timestamp) / 3600;
        $spread = $settings['uncertainty_percent'] / 100;
        $low = max(0, $kwh * (1 - $spread));
        $high = min($settings['max_watts'] / 1000 * $hours, $kwh * (1 + $spread));
        return ['kwh' => round($kwh, 6), 'cost' => round($kwh * $settings['rate'], 4),
            'low_kwh' => round($low, 6), 'high_kwh' => round($high, 6),
            'low_cost' => round($low * $settings['rate'], 4), 'high_cost' => round($high * $settings['rate'], 4),
            'hours' => round($hours, 6), 'cooldown_minutes' => round($coolMinutes, 2),
            'eco_minutes' => round($ecoMinutes, 2), 'rate' => (float) $settings['rate'],
            'rate_outside_period' => $start->toDateString() < $settings['rate_from'] || $end->toDateString() > $settings['rate_until'],
            'version' => $settings['version']];
    }
}
