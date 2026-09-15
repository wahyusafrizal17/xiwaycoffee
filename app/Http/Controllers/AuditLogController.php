<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);

        $filters = $request->only(['search', 'module', 'user', 'date']);

        $logs = AuditLog::query()
            ->with('user')
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where('action', 'like', '%'.$filters['search'].'%'))
            ->when(filled($filters['module'] ?? null), fn ($q) => $q->where('module', $filters['module']))
            ->when(filled($filters['user'] ?? null), function ($q) use ($filters) {
                $q->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$filters['user'].'%'));
            })
            ->when(filled($filters['date'] ?? null), fn ($q) => $q->whereDate('created_at', $filters['date']))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $focusLog = $request->filled('log')
            ? AuditLog::query()->with('user')->find($request->integer('log'))
            : null;

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'stats' => [
                'total' => AuditLog::query()->count(),
                'today' => AuditLog::query()->whereDate('created_at', today())->count(),
                'modules' => AuditLog::query()->distinct()->count('module'),
            ],
            'focusPayload' => $focusLog?->toModalArray(),
        ]);
    }
}
