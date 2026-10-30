<?php
namespace Tests\Feature;
use App\Models\CoolingSession;
use App\Models\CoolingSetting;
use App\Services\EnergyEstimator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CoolingTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp(); CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 20:00:00', 'Asia/Manila'));
        \Illuminate\Support\Carbon::setTestNow(CarbonImmutable::now());
    }
    protected function tearDown(): void {
        CarbonImmutable::setTestNow(); \Illuminate\Support\Carbon::setTestNow(); parent::tearDown();
    }
    private function entry(array $overrides=[]): array {
        return array_replace(['started_at'=>'2026-10-29T18:00','ended_at'=>'2026-10-29T19:00',
            'eco_at'=>'2026-10-29T18:10','temperature'=>26,'fan'=>'low','room_heat'=>'warm','notes'=>null], $overrides);
    }
    public function test_warm_room_estimate_uses_label_then_assumed_settled_input(): void {
        $response=$this->postJson('/api/estimate',$this->entry())->assertOk();
        $this->assertEqualsWithDelta(.79,$response->json('kwh'),.000001);
        $this->assertEqualsWithDelta(10.5307,$response->json('cost'),.0001);
        $response->assertJsonPath('cooldown_minutes',20)->assertJsonPath('eco_minutes',50);
        $this->assertEqualsWithDelta(.5135,$response->json('low_kwh'),.000001);
    }
    public function test_eco_phase_integrates_only_after_eco_switch_and_initial_cooling(): void {
        $s=config('cooling'); $s['normal_watts']=800;$s['eco_watts']=400;
        $e=app(EnergyEstimator::class)->calculate($this->entry(['eco_at'=>'2026-10-29T18:30']),$s);
        $this->assertEqualsWithDelta(1.03/3+.8/6+.4/2,$e['kwh'],.000001);
    }
    public function test_scenario_upper_end_never_exceeds_label_maximum(): void {
        $s=config('cooling');$s['normal_watts']=1250;$s['uncertainty_percent']=80;
        $e=app(EnergyEstimator::class)->calculate($this->entry(),$s);
        $this->assertLessThanOrEqual(1.25,$e['high_kwh']);
    }
    public function test_overnight_session_is_split_at_philippine_midnight(): void {
        $this->postJson('/api/sessions',$this->entry(['started_at'=>'2026-10-28T23:30','ended_at'=>'2026-10-29T00:30','eco_at'=>null]))->assertCreated();
        $r=$this->getJson('/api/state')->assertOk();
        $this->assertEqualsWithDelta(.335,$r->json('today.kwh'),.000001);
        $this->assertEqualsWithDelta(.79,$r->json('month.kwh'),.000001);
        $this->assertEqualsWithDelta(.5,$r->json('today.hours'),.000001);
    }
    public function test_month_boundary_only_counts_portion_in_current_month(): void {
        $this->postJson('/api/sessions',$this->entry(['started_at'=>'2026-09-30T23:30','ended_at'=>'2026-10-01T00:30','eco_at'=>null]))->assertCreated();
        $this->assertEqualsWithDelta(.335,$this->getJson('/api/state')->json('month.kwh'),.000001);
    }
    public function test_invalid_time_orders_future_dates_and_eco_outside_session_are_rejected(): void {
        foreach ([['ended_at'=>'2026-10-29T17:00'],['ended_at'=>'2026-10-30T18:00'],['eco_at'=>'2026-10-29T19:10'],['eco_at'=>'2026-10-29T17:59'],['ended_at'=>'2026-10-29T18:00']] as $bad) {
            $this->postJson('/api/sessions',$this->entry($bad))->assertUnprocessable();
        }
        $this->assertDatabaseCount('cooling_sessions',0);
    }
    public function test_overlapping_sessions_rejected_but_adjacent_sessions_allowed(): void {
        $this->postJson('/api/sessions',$this->entry())->assertCreated();
        $this->postJson('/api/sessions',$this->entry())->assertUnprocessable();
        $this->postJson('/api/sessions',$this->entry(['started_at'=>'2026-10-29T19:00','ended_at'=>'2026-10-29T19:30','eco_at'=>null]))->assertCreated();
    }
    public function test_saved_rate_and_model_survive_settings_changes_and_edit_preview(): void {
        $id=$this->postJson('/api/sessions',$this->entry())->json('id');
        $old=CoolingSession::find($id)->estimate;
        $settings=CoolingSetting::current();$settings['rate']=20;$settings['normal_watts']=900;
        $this->putJson('/api/settings',$settings)->assertOk();
        $this->assertEquals(13.33,CoolingSession::find($id)->estimate['rate']);
        $this->postJson('/api/estimate',$this->entry()+['session_id'=>$id])->assertOk()->assertJsonPath('rate',13.33);
        $this->putJson('/api/sessions/'.$id,$this->entry(['notes'=>'Edited']))->assertOk();
        $this->assertEquals($old['cost'],CoolingSession::find($id)->estimate['cost']);
    }
    public function test_only_one_timer_can_run_and_it_survives_a_state_reload(): void {
        $this->postJson('/api/sessions/start',$this->entry())->assertCreated();
        $this->postJson('/api/sessions/start',$this->entry())->assertConflict();
        $this->getJson('/api/state')->assertOk()->assertJsonPath('active.temperature',26)->assertJsonCount(0,'sessions');
        $this->assertDatabaseCount('cooling_sessions',1);
    }
    public function test_live_eco_and_stop_then_repeat_stop_is_rejected(): void {
        $id=$this->postJson('/api/sessions/start',$this->entry())->json('id');
        $this->putJson('/api/sessions/'.$id.'/eco',['eco_at'=>'2026-10-29T19:59'])->assertUnprocessable();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 21:00:00','Asia/Manila'));
        \Illuminate\Support\Carbon::setTestNow(CarbonImmutable::now());
        $this->putJson('/api/sessions/'.$id.'/eco',['eco_at'=>'2026-10-29T20:10'])->assertOk();
        $this->postJson('/api/sessions/'.$id.'/stop',[])->assertOk();
        $this->postJson('/api/sessions/'.$id.'/stop',[])->assertConflict();
        $this->getJson('/api/state')->assertJsonPath('active',null)->assertJsonCount(1,'sessions');
    }
    public function test_rate_period_warning_and_csv_export(): void {
        $this->postJson('/api/sessions',$this->entry(['started_at'=>'2026-10-01T18:00','ended_at'=>'2026-10-01T19:00','eco_at'=>null]))->assertCreated()->assertJsonPath('estimate.rate_outside_period',true);
        $this->get('/api/export')->assertOk()->assertDownload('coolmeter-sessions-2026-10-29.csv');
    }
    public function test_delete_removes_record_and_totals(): void {
        $id=$this->postJson('/api/sessions',$this->entry())->json('id');
        $this->deleteJson('/api/sessions/'.$id)->assertNoContent();
        $this->assertEquals(0,$this->getJson('/api/state')->json('month.kwh'));
    }
    public function test_settings_validation_keeps_bad_power_and_rates_out(): void {
        $this->putJson('/api/settings',array_replace(config('cooling'),['normal_watts'=>1300,'rate'=>-1]))->assertUnprocessable();
    }
}
