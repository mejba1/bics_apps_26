<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** System › Audit log (permission "audit.view"): who changed what in the admin area, and admin sign-ins. */
class AuditController extends Controller
{
    public function index(){
        return view('backend.pages.audit', [
            'users' => DB::table('audit_logs')->whereNotNull('user_name')->select('user_id', 'user_name')->distinct()->orderBy('user_name')->get(),
            'areas' => collect(Audit::AREAS)->unique()->sort(),
            'keep' => PlatformSettings::get('audit_days'),
        ]);
    }

    private function query(Request $request)
    {
        $q = DB::table('audit_logs');
        if ($request->filled('user')) $q->where('user_id', (int) $request->input('user'));
        if ($request->filled('area')) {
            $keys = array_keys(array_filter(Audit::AREAS, fn ($label) => $label === $request->input('area')));
            $q->whereIn('area', $keys ?: ['-']);
        }
        if ($request->filled('from')) $q->where('created_at', '>=', $request->date('from')->startOfDay());
        if ($request->filled('to')) $q->where('created_at', '<=', $request->date('to')->endOfDay());
        if ($request->boolean('problems')) $q->where(fn ($w) => $w->where('status', '>=', 400)->orWhere('action', 'Failed sign-in'));
        if ($s = trim((string) $request->input('q'))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
            $q->where(fn ($w) => $w->where('action', 'like', $like)->orWhere('subject', 'like', $like)->orWhere('user_name', 'like', $like)->orWhere('details', 'like', $like)->orWhere('ip', 'like', $like));
        }
        return $q;
    }

    public function data(Request $request){
        $q = $this->query($request);
        $total = (clone $q)->count();
        $per = 50;
        $page = max(1, min((int) $request->input('page', 1), (int) ceil(max(1, $total) / $per)));
        $rows = $q->orderByDesc('id')->offset(($page - 1) * $per)->limit($per)->get();
        return response()->json([
            'total' => $total, 'page' => $page, 'pages' => (int) ceil(max(1, $total) / $per),
            'rows' => $rows->map(fn ($r) => [
                'id' => $r->id, 'at' => \Carbon\Carbon::parse($r->created_at)->format('j M Y, H:i:s'), 'ago' => \Carbon\Carbon::parse($r->created_at)->diffForHumans(),
                'user' => $r->user_name, 'area' => Audit::AREAS[$r->area] ?? $r->area, 'action' => $r->action, 'subject' => $r->subject, 'status' => $r->status,
                'details' => $r->details ? json_decode($r->details, true) : null, 'ip' => $r->ip, 'agent' => $r->user_agent, 'route' => $r->route, 'method' => $r->method,
            ]),
        ]);
    }

    public function export(Request $request){
        $q = $this->query($request)->orderByDesc('id')->limit(20000);
        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['When', 'User', 'Area', 'Action', 'Record', 'Result', 'Details', 'IP address']);
            foreach ($q->cursor() as $r) {
                $row = [$r->created_at, $r->user_name, Audit::AREAS[$r->area] ?? $r->area, $r->action, $r->subject, $r->status, $r->details, $r->ip];
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row));
            }
            fclose($out);
        }, 'audit-log-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
