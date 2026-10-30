<?php
namespace App\Http\Controllers;
use App\Models\CoolingSession;
use App\Models\CoolingSetting;
use App\Services\EnergyEstimator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class CoolingController extends Controller {
    public function __construct(private EnergyEstimator $estimator) {}
    private function inputFor(CoolingSession $s): array {
        return ['started_at' => $s->started_at, 'ended_at' => $s->ended_at, 'eco_at' => $s->eco_at, 'room_heat' => $s->room_heat];
    }
    public function dashboard() {
        return response()->view('dashboard', ['initialState' => $this->journalState()])->header('Cache-Control', 'private, no-store');
    }
    public function state() {
        return response()->json($this->journalState())->header('Cache-Control', 'private, no-store');
    }
    private function journalState(): array {
        $sessions = CoolingSession::orderByDesc('started_at')->get();
        $completed = $sessions->whereNotNull('ended_at'); $now = CarbonImmutable::now('Asia/Manila');
        $sum = function ($from, $to) use ($completed) {
            $total = ['kwh' => 0, 'cost' => 0, 'hours' => 0];
            foreach ($completed as $s) {
                if ($s->started_at->gte($to) || $s->ended_at->lte($from)) { continue; }
                $part = $this->estimator->calculate($this->inputFor($s), $s->assumptions, $from, $to);
                foreach ($total as $key => $value) { $total[$key] += $part[$key]; }
            }
            return $total;
        };
        $week = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->startOfDay()->subDays($i);
            $week[] = ['date' => $day->toDateString()] + $sum($day, $day->addDay());
        }
        $active = $sessions->firstWhere('active_slot', 1);
        return ['settings' => CoolingSetting::current(), 'server_now' => $now->toIso8601String(),
            'sessions' => $completed->values(),
            'active' => $active ? $active->toArray() + ['live_estimate' => $this->estimator->calculate(
                array_replace($this->inputFor($active), ['ended_at' => $now]), $active->assumptions)] : null,
            'today' => $sum($now->startOfDay(), $now->startOfDay()->addDay()),
            'month' => $sum($now->startOfMonth(), $now->startOfMonth()->addMonth()), 'week' => $week];
    }
    private function journalWrite(callable $action) {
        return DB::transaction(function () use ($action) {
            // One AC has one timeline. Serialize edits from different household members.
            CoolingSetting::query()->insertOrIgnore(['id' => 1, 'values' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            CoolingSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            return $action();
        });
    }
    private function details(Request $r): array {
        return $r->validate(['temperature' => ['required', 'integer', 'between:16,30'],
            'room_heat' => ['required', Rule::in(['comfortable', 'warm', 'hot'])],
            'fan' => ['required', Rule::in(['low', 'medium', 'high', 'auto'])],
            'notes' => ['nullable', 'string', 'max:1000']]);
    }
    private function times(Request $r): array {
        $data = $r->validate(['started_at' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'ended_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:started_at', 'before_or_equal:now'],
            'eco_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:started_at', 'before_or_equal:ended_at']]);
        if (CarbonImmutable::parse($data['ended_at'])->timestamp - CarbonImmutable::parse($data['started_at'])->timestamp > 7 * 86400) {
            throw ValidationException::withMessages(['ended_at' => 'A manual session can be at most 7 days long.']);
        }
        return $data;
    }
    public function estimate(Request $r) {
        $data = $this->details($r) + $this->times($r);
        $r->validate(['session_id' => ['nullable', 'integer', 'exists:cooling_sessions,id']]);
        $settings = $r->filled('session_id') ? CoolingSession::findOrFail($r->input('session_id'))->assumptions : CoolingSetting::current();
        return response()->json($this->estimator->calculate($data, $settings));
    }
    public function store(Request $r) {
        $data = $this->details($r) + $this->times($r);
        return $this->journalWrite(function () use ($data) {
        $settings = CoolingSetting::current();
        $this->rejectOverlap($data['started_at'], $data['ended_at']);
        return response()->json(CoolingSession::create($data + ['assumptions' => $settings,
            'estimate' => $this->estimator->calculate($data, $settings)]), 201);
        });
    }
    public function update(Request $r, CoolingSession $session) {
        return $this->journalWrite(function () use ($r, $session) {
        $session = CoolingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
        abort_if($session->active_slot, 409, 'Stop the running session before editing it.');
        $data = $this->details($r) + $this->times($r);
        $this->rejectOverlap($data['started_at'], $data['ended_at'], $session->id);
        $session->update($data + ['estimate' => $this->estimator->calculate($data, $session->assumptions)]);
        return response()->json($session);
        });
    }
    public function start(Request $r) {
        $details = $this->details($r);
        return $this->journalWrite(function () use ($details) {
            abort_if(CoolingSession::where('active_slot', 1)->exists(), 409, 'A session is already running. Refresh to see it.');
            return response()->json(CoolingSession::create($details + ['started_at' => CarbonImmutable::now('Asia/Manila'),
                'active_slot' => 1, 'assumptions' => CoolingSetting::current()]), 201);
        });
    }
    public function eco(Request $r, CoolingSession $session) {
        return $this->journalWrite(function () use ($r, $session) {
        $session = CoolingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
        abort_unless($session->active_slot, 409, 'This session has already stopped.');
        $data = $r->validate(['eco_at' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now']]);
        $eco = CarbonImmutable::parse($data['eco_at'], 'Asia/Manila');
        if ($eco->lt($session->started_at->startOfMinute())) {
            throw ValidationException::withMessages(['eco_at' => 'Eco time must be within this session.']);
        }
        $session->update(['eco_at' => $eco->max($session->started_at)]);
        return response()->json($session);
        });
    }
    public function stop(Request $r, CoolingSession $session) {
        return $this->journalWrite(function () use ($r, $session) {
        $session = CoolingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
        abort_unless($session->active_slot, 409, 'This session has already stopped.');
        $r->validate(['ended_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'before_or_equal:now']]);
        $end = $r->filled('ended_at') ? CarbonImmutable::parse($r->input('ended_at'), 'Asia/Manila') : CarbonImmutable::now('Asia/Manila');
        if ($end->lte($session->started_at) || ($session->eco_at && $end->lt($session->eco_at))) {
            throw ValidationException::withMessages(['ended_at' => 'End time must follow the start and Eco times.']);
        }
        $this->rejectOverlap($session->started_at, $end, $session->id);
        $input = array_replace($this->inputFor($session), ['ended_at' => $end]);
        $session->update(['ended_at' => $end, 'active_slot' => null,
            'estimate' => $this->estimator->calculate($input, $session->assumptions)]);
        return response()->json($session);
        });
    }
    private function rejectOverlap($start, $end, ?int $except = null): void {
        $q = CoolingSession::where('started_at', '<', CarbonImmutable::parse($end, 'Asia/Manila'))
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', CarbonImmutable::parse($start, 'Asia/Manila')));
        if ($except) { $q->where('id', '!=', $except); }
        if ($q->exists()) {
            throw ValidationException::withMessages(['started_at' => 'These times overlap another session for your AC.']);
        }
    }
    public function destroy(CoolingSession $session) {
        return $this->journalWrite(function () use ($session) {
            CoolingSession::whereKey($session->id)->firstOrFail()->delete();
            return response()->noContent();
        });
    }
    public function settings(Request $r) {
        return $this->journalWrite(function () use ($r) {
        $data = $r->validate(['rate' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'rate_from' => ['required', 'date_format:Y-m-d'],
            'rate_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:rate_from'],
            'normal_watts' => ['required', 'integer', 'between:50,1250'],
            'eco_watts' => ['required', 'integer', 'between:50,1250'],
            'cooldown_minutes' => ['required', 'integer', 'between:0,120'],
            'uncertainty_percent' => ['required', 'integer', 'between:10,80']]);
        $old = CoolingSetting::current();
        if ((float) $data['rate'] !== (float) $old['rate'] || $data['rate_from'] !== $old['rate_from'] || $data['rate_until'] !== $old['rate_until']) {
            $data += ['rate_source' => 'Your manually entered Davao Light rate', 'rate_source_url' => 'https://www.davaolight.com/customer-services/faqs-rates', 'rate_status' => 'manual'];
        }
        CoolingSetting::updateOrCreate(['id' => 1], ['values' => array_replace($old, $data)]);
        return response()->json(CoolingSetting::current());
        });
    }
    public function export() {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Start (Asia/Manila)', 'End (Asia/Manila)', 'Eco start', 'Temperature C', 'Room', 'Fan', 'Hours', 'Estimated kWh', 'Estimated PHP', 'Rate PHP/kWh', 'Low kWh', 'High kWh']);
            foreach (CoolingSession::whereNotNull('ended_at')->orderBy('started_at')->cursor() as $s) {
                $e = $s->estimate;
                fputcsv($out, [$s->started_at->format('Y-m-d H:i:s'), $s->ended_at->format('Y-m-d H:i:s'),
                    $s->eco_at?->format('Y-m-d H:i:s'), $s->temperature, $s->room_heat, $s->fan,
                    $e['hours'], $e['kwh'], $e['cost'], $e['rate'], $e['low_kwh'], $e['high_kwh']]);
            }
            fclose($out);
        }, 'coolmeter-sessions-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
