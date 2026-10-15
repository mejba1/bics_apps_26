<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin dashboard and login page. Runs inside a transaction. */
class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $u = new User();
        $u->forceFill(['name' => 'Dash Admin', 'email' => uniqid('d') . '@test.local', 'password' => bcrypt('secret-pass'), 'role' => 'admin',
            'role_id' => Role::where('is_super', true)->value('id'), 'status' => 'active'])->save();
        return $u;
    }

    public function test_dashboard_shows_the_selected_event(): void
    {
        $this->actingAs($this->admin());
        $current = \App\Support\EventSettings::get('venue_code');

        // Default: current event
        $html = $this->get('/home')->assertOk()->assertSee('Registrations per day')->assertSee('db-trend', false)->getContent();
        $this->assertStringContainsString('db-venue is-active', $html);
        $this->assertSame(DB::table('registrations')->where('venue', $current)->count(), $this->kpi($html));

        // Another event, and all events
        $venue = DB::table('registrations')->where('venue', '!=', $current)->value('venue');
        if ($venue) {
            $this->assertSame(DB::table('registrations')->where('venue', $venue)->count(), $this->kpi($this->get('/home?venue=' . urlencode($venue))->getContent()));
        }
        $this->assertSame(DB::table('registrations')->count(), $this->kpi($this->get('/home?venue=all')->getContent()));

        // Unknown venue falls back to the current event; old venue page redirects to the dashboard
        $this->get('/home?venue=Nowhere')->assertOk();
        $this->get('/venue/details/' . $current)->assertRedirect('/home?venue=' . urlencode($current));
    }

    public function test_login_page(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back')->assertSee(\App\Support\EventSettings::get('event_name'))->assertSee('lgEye', false);
        $this->post('/custom-login', ['email' => 'nobody@test.local', 'password' => 'wrong'])->assertSessionHas('error')->assertSessionHasInput('email', 'nobody@test.local');
    }

    /** Number shown on the "Registrations" KPI card. */
    private function kpi(string $html): int
    {
        preg_match('#<div class="db-kpi-label">Registrations</div>\s*<div class="db-kpi-value">([\d,]+)</div>#', $html, $m);
        return (int) str_replace(',', '', $m[1] ?? '-1');
    }
}
