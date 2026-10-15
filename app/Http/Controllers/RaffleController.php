<?php

namespace App\Http\Controllers;

use App\Support\EventSettings;
use App\Support\Raffle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The raffle draw screen (/raffle) for the current event. Only the event's assigned hosts can use it
 * (Admin > Raffle Draw > Raffle setup). The winner is picked here, on the server, with random_int();
 * the screen only plays the animation. A drawn person becomes a winner only when the host confirms.
 */
class RaffleController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            \App\Support\EventLock::ensureOpen(\App\Support\Raffle::event(), 'raffle');
            return $next($request);
        })->only(['draw', 'accept', 'redraw']);
    }

    public function stage(Request $request){
        $event = Raffle::event();
        if (\App\Support\EventLock::isLocked($event)) {   // ended / archived: no draw screen
            return response()->view('frontend.pages.raffle-denied', ['event' => $event, 'hosts' => collect(), 'locked' => true], 423);
        }
        if (!Raffle::isHost($request->user(), $event)) {
            return response()->view('frontend.pages.raffle-denied', ['event' => $event, 'hosts' => Raffle::hosts($event)], 403);
        }
        return view('frontend.pages.raffle', ['event' => $event, 'state' => $this->payload($request)]);
    }

    public function state(Request $request){
        $this->guardHost($request);
        return response()->json($this->payload($request));
    }

    /** Pick a random eligible person for the next prize (not saved until confirmed). */
    public function draw(Request $request){
        $event = $this->guardHost($request);
        $prize = Raffle::nextPrize($event);
        if (!$prize) {
            $message = Raffle::prizes($event)->isEmpty() ? 'No prizes yet. Add them in Raffle setup.' : 'All prizes have been awarded.';
            return response()->json(['message' => $message], 422);
        }

        $pool = Raffle::pool($event, $this->skipped($event->id));
        $count = (clone $pool)->count();
        if (!$count) return response()->json(['message' => 'Nobody is left to draw. Check "Who can win" in Raffle setup.'], 422);

        $person = Raffle::pick($event, $this->skipped($event->id));
        $token = Str::random(32);
        Cache::put($this->pendingKey($event->id), ['token' => $token, 'register_id' => $person->registration_id, 'entry_id' => $person->entry_id,
            'key' => Raffle::key($person), 'prize_id' => $prize->id, 'user_id' => $request->user()->id], now()->addMinutes(30));

        // A few numbers of people still in the draw, flashed in the reels while drawing (never the full list)
        $shuffle = Raffle::screen($event)['shuffle'] === 'numbers'
            ? (clone $pool)->inRandomOrder()->limit(40)->get()->pluck('qr_code')->map(fn ($n) => (string) $n)->values()
            : [];

        return response()->json([
            'token' => $token,
            'shuffle' => $shuffle,
            'prize' => ['id' => $prize->id, 'name' => $prize->prize, 'type' => $prize->prize_type],
            'person' => Raffle::person($person),
            'pool' => $count,
        ]);
    }

    /** The drawn person is here: save them as the winner of the prize. */
    public function accept(Request $request){
        $event = $this->guardHost($request);
        $pending = $this->pending($event->id, $request->input('token'));

        $result = DB::transaction(function () use ($event, $pending, $request) {
            $prize = DB::table('prizes')->where('id', $pending['prize_id'])->where('event_id', $event->id)->lockForUpdate()->first();
            if (!$prize || $prize->winner_id) return 'This prize has already been awarded.';
            $regId = $pending['register_id'] ?? null;
            $entryId = $pending['entry_id'] ?? null;
            $already = DB::table('winners')->where('event_id', $event->id)
                ->where(fn ($q) => $q->when($regId, fn ($w) => $w->orWhere('register_id', $regId))->when($entryId, fn ($w) => $w->orWhere('entry_id', $entryId)))->exists();
            if ($already) return 'This person has already won a prize.';
            $r = $regId ? DB::table('registrations')->where('id', $regId)->first() : null;
            $e = $entryId ? DB::table('raffle_entries')->where('id', $entryId)->first() : null;
            if (!$r && !$e) return 'This number is no longer in the draw.';

            // A registration gives the details; a list number without one wins by number (name / country from the import, if any)
            $winnerId = DB::table('winners')->insertGetId([
                'event_id' => $event->id, 'register_id' => $r->id ?? null, 'entry_id' => $e->id ?? null, 'winners_venue' => $r->venue ?? $event->venue_code,
                'winners_name' => $r ? trim($r->firstname . ' ' . $r->lastname) : (($e->name ?? '') ?: 'Number ' . $e->number),
                'winners_designation' => $r->profession ?? null, 'winners_country' => $r->country ?? ($e->country ?? null),
                'winners_number' => $r->qr_code ?? $e->number,
                'prize' => $prize->prize, 'prize_type' => $prize->prize_type, 'drawn_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('prizes')->where('id', $prize->id)->update(['winner_id' => $winnerId, 'prize_status' => 'Yes', 'updated_at' => now()]);
            if ($r) DB::table('attendees')->where('register_id', $r->id)->update(['winner_status' => 'Yes']);
            return $winnerId;
        });
        if (!is_int($result)) return response()->json(['message' => $result], 422);

        Cache::forget($this->pendingKey($event->id));
        // Winner email (Raffle setup > Winner email): sent after the response, so the stage never waits for the mail server
        $emailing = \App\Support\WinnerEmail::settings($event)['mode'] === 'auto';
        if ($emailing) app()->terminating(fn () => \App\Support\WinnerEmail::send($result));
        return response()->json(['success' => true, 'message' => $emailing ? 'Winner confirmed. Congratulations email on its way.' : 'Winner confirmed.', 'state' => $this->payload($request)]);
    }

    /** The drawn person is not here: leave them out of this event's draw and draw again. */
    public function redraw(Request $request){
        $event = $this->guardHost($request);
        $pending = $this->pending($event->id, $request->input('token'));
        $skipped = $this->skipped($event->id);
        $skipped[] = $pending['key'] ?? ('r:' . $pending['register_id']);
        Cache::put($this->skippedKey($event->id), array_values(array_unique($skipped)), now()->addDays(3));
        Cache::forget($this->pendingKey($event->id));
        return response()->json(['success' => true, 'state' => $this->payload($request)]);
    }

    private function payload(Request $request): array
    {
        $event = Raffle::event();
        $prizes = Raffle::prizes($event);
        $next = $prizes->first(fn ($p) => !$p->winner_id);
        return [
            'event' => $event ? ['name' => $event->event_name, 'short' => $event->event_short, 'venue' => $event->event_venue, 'dates' => EventSettings::datesLabel()] : null,
            'prizes' => $prizes->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->prize, 'type' => $p->prize_type, 'won' => (bool) $p->winner_id,
                'winner' => $p->winner_id ? ['name' => $p->winners_name, 'number' => $p->winners_number, 'country' => $p->winners_country] : null,
            ])->values(),
            'next' => $next ? $next->id : null,
            'pool' => $event ? Raffle::pool($event, $this->skipped($event->id))->count() : 0,
            'skipped' => $event ? count($this->skipped($event->id)) : 0,
            'eligibility' => Raffle::ELIGIBILITY[Raffle::eligibility($event)],
            'host' => $request->user()->name,
            'screen' => Raffle::screen($event),
        ];
    }

    private function guardHost(Request $request)
    {
        $event = Raffle::event();
        abort_unless($event && Raffle::isHost($request->user(), $event), 403, 'Only the assigned raffle hosts can run this draw.');
        return $event;
    }

    private function pending(int $eventId, ?string $token): array
    {
        $pending = Cache::get($this->pendingKey($eventId));
        abort_unless($pending && $token && hash_equals($pending['token'], (string) $token), 409, 'This draw is no longer valid. Please draw again.');
        return $pending;
    }

    private function skipped(int $eventId): array
    {
        return Cache::get($this->skippedKey($eventId), []);
    }

    private function pendingKey(int $eventId): string { return 'raffle.pending.' . $eventId; }
    private function skippedKey(int $eventId): string { return 'raffle.skipped.' . $eventId; }
}
