<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Participants › Organizations & categories: every value of a profile field with how many participants use it,
 * spelling variants grouped together ("Dhaka Medical College" / "dhaka medical college."), and rename / merge.
 */
class ParticipantValuesController extends Controller
{
    public const FIELDS = ['organization' => 'Organizations', 'category' => 'Categories', 'country' => 'Countries', 'designation' => 'Designations'];

    public function index(Request $request){
        $field = array_key_exists($request->input('field'), static::FIELDS) ? $request->input('field') : 'organization';
        return view('backend.pages.participants.values', ['field' => $field, 'fields' => static::FIELDS]);
    }

    /** Values with counts; "group" is the same for spelling variants. */
    public function data(Request $request){
        $field = $request->validate(['field' => ['required', Rule::in(array_keys(static::FIELDS))]])['field'];
        $rows = DB::table('participants')->whereNull('deleted_at')->whereNotNull($field)->where($field, '!=', '')
            ->select($field . ' as value', DB::raw('COUNT(*) as n'))->groupBy($field)->orderByDesc('n')->get();
        $groups = [];
        foreach ($rows as $r) $groups[static::key($r->value)][] = $r->value;
        return response()->json([
            'rows' => $rows->map(fn ($r) => ['value' => $r->value, 'n' => (int) $r->n, 'group' => static::key($r->value), 'variants' => count($groups[static::key($r->value)])]),
            'empty' => DB::table('participants')->whereNull('deleted_at')->where(fn ($w) => $w->whereNull($field)->orWhere($field, ''))->count(),
        ]);
    }

    /** Rename / merge: every participant with one of the "from" values gets "to". */
    public function rename(Request $request){
        $max = ['organization' => 190, 'category' => 100, 'country' => 100, 'designation' => 150][(string) $request->input('field')] ?? 100;
        $data = $request->validate([
            'field' => ['required', Rule::in(array_keys(static::FIELDS))],
            'from' => 'required|array|min:1|max:200', 'from.*' => 'required|string|max:190',
            'to' => 'nullable|string|max:' . $max,
        ], ['from.required' => 'Choose at least one value.']);
        $to = trim(preg_replace('/\s+/', ' ', (string) ($data['to'] ?? '')));
        $n = DB::transaction(function () use ($data, $to) {
            $ids = DB::table('participants')->whereIn($data['field'], $data['from'])->pluck('id');
            DB::table('participants')->whereIn('id', $ids)->update([$data['field'] => $to !== '' ? $to : null, 'updated_at' => now()]);
            if ($ids->count()) {
                DB::table('participant_changes')->insert($ids->map(fn ($id) => ['participant_id' => $id, 'source' => 'admin', 'user_id' => auth()->id(),
                    'changes' => json_encode([$data['field'] => [Str::limit(implode(' / ', $data['from']), 250), $to ?: null]]), 'ip' => request()->ip(), 'created_at' => now()])->all());
            }
            return $ids->count();
        });
        return response()->json(['success' => true, 'message' => $n . ' participant' . ($n === 1 ? '' : 's') . ' updated' . ($to !== '' ? ' to "' . $to . '"' : ' (value cleared)') . '.']);
    }

    /** Spelling-insensitive key: case, accents, punctuation, "&"/"and", extra spaces and a leading "The" ignored. */
    public static function key(string $v): string
    {
        $v = Str::lower(Str::ascii($v));
        $v = str_replace('&', ' and ', $v);
        $v = preg_replace('/[^a-z0-9]+/', ' ', $v);
        $v = preg_replace('/^the /', '', trim($v));
        return preg_replace('/\s+/', ' ', $v);
    }
}
