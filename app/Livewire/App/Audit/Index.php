<?php

namespace App\Livewire\App\Audit;

use App\Models\AuditLog;
use App\Models\Subcontractor;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Journal d\'activité - DocPilot')]
class Index extends Component
{
    use WithPagination;

    public string $action_type = '';

    public string $user_filter = '';

    public function mount(): void
    {
        $this->authorize('view', auth()->user()->company);
    }

    public function updatingActionType(): void
    {
        $this->resetPage();
    }

    public function updatingUserFilter(): void
    {
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('view', auth()->user()->company);

        $rows = $this->buildQuery()->limit(1000)->get();
        $usersById = User::where('company_id', auth()->user()->company_id)->get()->keyBy('id');
        $subsById = Subcontractor::all()->keyBy('id');

        $filename = 'audit-log-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $usersById, $subsById): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Horodatage', 'Utilisateur', 'Action', 'Cible', 'IP', 'Détails'], ';');

            foreach ($rows as $log) {
                $mapped = $this->formatAuditRow($log, $usersById, $subsById);
                fputcsv($out, [
                    $mapped['timestamp'],
                    $mapped['user'],
                    $mapped['action'],
                    $mapped['target'],
                    $mapped['ip'],
                    $mapped['details'],
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $paginator = $this->buildQuery()->paginate(25);
        $usersById = User::where('company_id', auth()->user()->company_id)->get()->keyBy('id');
        $subsById = Subcontractor::all()->keyBy('id');

        $auditLogs = $paginator->getCollection()->map(
            fn (AuditLog $log) => $this->formatAuditRow($log, $usersById, $subsById)
        );

        $usersList = $usersById->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->full_name ?: $u->name,
        ])->values()->all();

        return view('livewire.app.audit.index', [
            'auditLogs' => $auditLogs,
            'paginator' => $paginator,
            'usersList' => $usersList,
        ]);
    }

    private function buildQuery()
    {
        $query = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id');

        if ($this->action_type !== '') {
            $type = $this->action_type;
            $query->where(function ($q) use ($type): void {
                match ($type) {
                    'Connexion' => $q->where('action', 'like', 'auth.%'),
                    'Dépôt' => $q->where('action', 'like', '%uploaded%')->orWhere('action', 'like', 'portal.%'),
                    'Validation' => $q->where('action', 'like', '%validated%'),
                    'Rejet' => $q->where('action', 'like', '%rejected%'),
                    'Relance' => $q->where('action', 'like', '%reminder%')->orWhere('action', 'like', 'request.%'),
                    'Support' => $q->where('action', 'like', 'platform.support%'),
                    default => $q->where('action', 'like', '%'.$type.'%'),
                };
            });
        }

        if ($this->user_filter !== '') {
            if ($this->user_filter === 'system') {
                $query->whereNull('user_id');
            } elseif (is_numeric($this->user_filter)) {
                $query->where('user_type', 'user')->where('user_id', (int) $this->user_filter);
            }
        }

        return $query;
    }

    private function formatAuditRow(AuditLog $log, $usersById, $subsById): array
    {
        $userLabel = 'Système (Auto)';
        if ($log->user_type === 'user' && $log->user_id) {
            $u = $usersById[$log->user_id] ?? User::find($log->user_id);
            $userLabel = $u ? ($u->full_name ?: $u->name) : "Utilisateur #{$log->user_id}";
        } elseif ($log->user_type === 'subcontractor' && $log->user_id) {
            $s = $subsById[$log->user_id] ?? null;
            $userLabel = ($s ? ($s->contact_name ?: $s->name) : "Sous-traitant #{$log->user_id}").' (Portail)';
        }

        $actionLabel = match (true) {
            str_starts_with($log->action, 'auth.') => 'Authentification',
            str_contains($log->action, 'validated') => 'Validation',
            str_contains($log->action, 'rejected') => 'Rejet',
            str_contains($log->action, 'uploaded') || str_starts_with($log->action, 'portal.') => 'Dépôt',
            str_contains($log->action, 'reminder') || str_starts_with($log->action, 'request.') => 'Demande / Relance',
            str_starts_with($log->action, 'platform.support') => 'Accès Support',
            default => $log->action,
        };

        $target = $log->entity_type
            ? ucfirst(str_replace('_', ' ', $log->entity_type)).($log->entity_id ? " #{$log->entity_id}" : '')
            : 'Espace Entreprise';

        $details = $log->metadata
            ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : "Action {$log->action} enregistrée.";

        return [
            'id' => $log->id,
            'timestamp' => $log->created_at?->format('d/m/Y H:i:s') ?? '-',
            'user' => $userLabel,
            'action' => $actionLabel,
            'raw_action' => $log->action,
            'target' => $target,
            'ip' => $log->ip_address ?? '127.0.0.1',
            'details' => $details,
        ];
    }
}
