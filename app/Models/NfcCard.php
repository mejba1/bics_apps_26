<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An NFC card (docs/NFC-PROFILE-PLAN.md, phase 2). The chip stores the card's own link /n/{token};
 * BICS sends a tap to the profile of the participant the card is assigned to, but only while the card is active.
 *
 * Lifecycle:  available → assigned → (encoded → verified) → active → lost / damaged / deactivated
 * Rules:
 * - a participant has at most one card in use (assigned or active); a new one goes through "Replace";
 * - activation needs a participant and an encoded chip (verification is recommended);
 * - lost / damaged cards never come back into use; deactivated cards can be reassigned (no re-encoding needed);
 * - every step is written to nfc_card_logs (who, when, what).
 */
class NfcCard extends Model
{
    public const IN_USE = ['assigned', 'active'];
    public const STATUS = [
        'available' => ['In stock', 'secondary'], 'assigned' => ['Assigned', 'info'], 'active' => ['Active', 'success'],
        'lost' => ['Lost', 'danger'], 'damaged' => ['Damaged', 'danger'], 'deactivated' => ['Deactivated', 'warning'],
    ];
    public const ENCODE = ['not_encoded' => ['Not encoded', 'secondary'], 'encoded' => ['Encoded', 'success'], 'failed' => ['Encoding failed', 'danger']];
    public const VERIFY = ['not_verified' => ['Not verified', 'secondary'], 'verified' => ['Verified', 'success'], 'mismatch' => ['Wrong link', 'danger']];
    public const PRINT = ['not_printed' => ['Not printed', 'secondary'], 'printed' => ['Printed', 'success']];

    protected $guarded = ['id'];
    protected $casts = ['locked' => 'boolean', 'encoded_at' => 'datetime', 'verified_at' => 'datetime', 'activated_at' => 'datetime', 'printed_at' => 'datetime'];

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function logs()
    {
        return $this->hasMany(NfcCardLog::class, 'card_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    /** The link written to the chip. */
    public function url(): string
    {
        return \App\Support\PublicLinks::base() . '/n/' . $this->token;
    }

    public static function normalizeUid(?string $uid): ?string
    {
        $uid = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $uid));
        return $uid !== '' ? implode(':', str_split($uid, 2)) : null;
    }

    /* ---------------- inventory ---------------- */

    /** Add blank cards to stock (optionally with chip UIDs, one per card). */
    public static function addToStock(int $count, array $uids = [], ?int $userId = null): array
    {
        $uids = array_values(array_filter(array_map([static::class, 'normalizeUid'], $uids)));
        $count = max($count, count($uids));
        $dupes = $uids ? static::whereIn('uid', $uids)->pluck('uid')->all() : [];
        if ($dupes) throw ValidationException::withMessages(['uids' => 'Already in stock: ' . implode(', ', array_slice($dupes, 0, 5))]);
        if (count($uids) !== count(array_unique($uids))) throw ValidationException::withMessages(['uids' => 'The same UID is listed twice.']);
        return DB::transaction(function () use ($count, $uids, $userId) {
            $made = [];
            $seq = (int) static::lockForUpdate()->max('sequence');
            for ($i = 0; $i < $count; $i++) {
                $seq++;
                $card = static::create([
                    'card_no' => 'NFC-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT), 'sequence' => $seq,
                    'token' => static::newToken(), 'uid' => $uids[$i] ?? null,
                    'status' => 'available', 'encode_status' => 'not_encoded', 'verify_status' => 'not_verified', 'print_status' => 'not_printed', 'locked' => false,
                ]);
                $card->log('created', 'Added to stock' . ($card->uid ? ' (UID ' . $card->uid . ')' : ''), $userId);
                $made[] = $card;
            }
            return $made;
        });
    }

    private static function newToken(): string
    {
        do { $t = Str::random(10); } while (static::where('token', $t)->exists() || preg_match('/[0O1lI]/', $t));
        return $t;
    }

    /** A card from stock, or a new one, for a participant. */
    public static function takeFromStock(?int $userId = null): NfcCard
    {
        return static::where('status', 'available')->whereNull('participant_id')->orderBy('sequence')->first()
            ?? static::addToStock(1, [], $userId)[0];
    }

    /* ---------------- workflow ---------------- */

    public function assignTo(Participant $p, ?int $userId = null, ?int $replaces = null): self
    {
        if ($this->status !== 'available' && $this->status !== 'deactivated') $this->fail('This card is ' . strtolower(static::STATUS[$this->status][0]) . ' and cannot be assigned.');
        if ($p->status !== 'active' || $p->trashed()) $this->fail('This participant profile is not active.');
        $inUse = static::where('participant_id', $p->id)->whereIn('status', static::IN_USE)->where('id', '!=', $this->id)->first();
        if ($inUse && !$replaces) $this->fail($p->name . ' already has card ' . $inUse->card_no . ' (' . strtolower(static::STATUS[$inUse->status][0]) . '). Use "Replace card" instead.');
        $previous = $this->participant_id && $this->participant_id !== $p->id ? $this->participant : null;
        $this->fill(['participant_id' => $p->id, 'status' => 'assigned', 'replaces_card_id' => $replaces ?? $this->replaces_card_id])->save();
        $this->log($previous ? 'reassigned' : 'assigned', ($previous ? 'From ' . $previous->code . ' to ' : 'To ') . $p->code . ' ' . $p->name
            . ($this->encode_status === 'encoded' ? ' (chip already encoded with this card\'s link: no rewrite needed)' : ''), $userId);
        return $this;
    }

    public function unassign(?int $userId = null): self
    {
        if ($this->status === 'active') $this->fail('Deactivate the card first.');
        if (in_array($this->status, ['lost', 'damaged'], true)) $this->fail('Lost or damaged cards stay with their history.');
        $old = $this->participant;
        $this->fill(['participant_id' => null, 'status' => 'available'])->save();
        $this->log('unassigned', $old ? 'From ' . $old->code . ' ' . $old->name : null, $userId);
        return $this;
    }

    public function markEncoded(?string $uid, bool $locked, string $method, ?int $userId = null): self
    {
        if (!$this->participant_id) $this->fail('Assign the card to a participant first.');
        $uid = static::normalizeUid($uid);
        if ($uid && static::where('uid', $uid)->where('id', '!=', $this->id)->exists()) $this->fail('Another card already has UID ' . $uid . '.');
        if ($uid && $this->uid && $uid !== $this->uid) $this->fail('This chip (UID ' . $uid . ') is not ' . $this->card_no . ' (UID ' . $this->uid . '). Check you are holding the right card.');
        $this->fill(['encode_status' => 'encoded', 'url_written' => $this->url(), 'encoded_at' => now(), 'uid' => $uid ?: $this->uid,
            'locked' => $this->locked || $locked, 'verify_status' => 'not_verified', 'verified_at' => null])->save();
        $this->log('encoded', 'Link written (' . $method . ')' . ($uid ? ' · UID ' . $uid : '') . ($locked ? ' · chip locked (read-only)' : ''), $userId);
        return $this;
    }

    public function markEncodeFailed(string $error, ?int $userId = null): self
    {
        $this->fill(['encode_status' => 'failed'])->save();
        $this->log('encode_failed', Str::limit($error, 300), $userId);
        return $this;
    }

    /** Check what was read back from the chip (Web NFC) or confirm manually ($readUrl null). */
    public function verify(?string $readUrl, ?string $uid, ?int $userId = null): bool
    {
        if ($this->encode_status !== 'encoded') $this->fail('Encode the card first.');
        $uid = static::normalizeUid($uid);
        if ($uid && $this->uid && $uid !== $this->uid) $this->fail('This is a different chip (UID ' . $uid . ', expected ' . $this->uid . ').');
        $ok = $readUrl === null || rtrim(trim($readUrl), '/') === rtrim($this->url(), '/');
        $this->fill(['verify_status' => $ok ? 'verified' : 'mismatch', 'verified_at' => $ok ? now() : null, 'uid' => $this->uid ?: $uid])->save();
        $this->log($ok ? 'verified' : 'verify_failed', $readUrl === null ? 'Confirmed by staff (tapped with a phone)' : ($ok ? 'Read back correctly' : 'Chip contains: ' . Str::limit($readUrl, 200)), $userId);
        return $ok;
    }

    public function activate(?int $userId = null): self
    {
        if (!$this->participant_id) $this->fail('Assign the card to a participant first.');
        if ($this->encode_status !== 'encoded') $this->fail('Encode the card before activating it.');
        if ($this->verify_status === 'mismatch') $this->fail('The chip has the wrong link. Encode it again.');
        if (!in_array($this->status, ['assigned', 'deactivated'], true)) $this->fail('Only assigned or deactivated cards can be activated.');
        $other = static::where('participant_id', $this->participant_id)->where('status', 'active')->where('id', '!=', $this->id)->first();
        if ($other) $this->fail('This participant already has active card ' . $other->card_no . '. Deactivate it first or use "Replace card".');
        $this->fill(['status' => 'active', 'activated_at' => now()])->save();
        $this->log('activated', $this->verify_status === 'verified' ? null : 'Activated without verification', $userId);
        return $this;
    }

    public function deactivate(string $reason, ?string $note, ?int $userId = null): self
    {
        if (!in_array($reason, ['lost', 'damaged', 'deactivated'], true)) $this->fail('Choose a reason.');
        if (in_array($this->status, ['lost', 'damaged'], true)) $this->fail('This card is already ' . $this->status . '.');
        if ($this->status === 'available') $this->fail('Cards in stock do not need deactivating.');
        $this->fill(['status' => $reason])->save();
        $this->log($reason === 'deactivated' ? 'deactivated' : 'marked_' . $reason, $note, $userId);
        return $this;
    }

    /** Lost / damaged / broken card: switch it off and give the same participant a new card (from stock). */
    public function replace(string $reason, ?NfcCard $new, ?int $userId = null): NfcCard
    {
        $p = $this->participant;
        if (!$p) $this->fail('This card has no participant.');
        return DB::transaction(function () use ($reason, $new, $userId, $p) {
            if (!in_array($this->status, ['lost', 'damaged', 'deactivated'], true)) $this->deactivate($reason, 'Replaced', $userId);
            $new = $new ?: static::takeFromStock($userId);
            if ($new->id === $this->id) $this->fail('Choose a different card.');
            $new->assignTo($p, $userId, $this->id);
            $this->log('replaced', 'Replaced by ' . $new->card_no, $userId);
            $new->log('replacement', 'Replaces ' . $this->card_no . ' (' . $reason . ')', $userId);
            return $new;
        });
    }

    public function setPrinted(bool $printed, ?int $userId = null): self
    {
        $this->fill(['print_status' => $printed ? 'printed' : 'not_printed', 'printed_at' => $printed ? now() : null])->save();
        $this->log($printed ? 'printed' : 'unprinted', null, $userId);
        return $this;
    }

    public function log(string $action, ?string $details = null, ?int $userId = null): void
    {
        DB::table('nfc_card_logs')->insert(['card_id' => $this->id, 'participant_id' => $this->participant_id, 'action' => $action,
            'details' => $details ? Str::limit($details, 490) : null, 'user_id' => $userId, 'created_at' => now()]);
    }

    private function fail(string $message): void
    {
        throw ValidationException::withMessages(['card' => $message]);
    }
}
