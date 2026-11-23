<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Audit\AuditLogFilterRequest;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const EXPORT_LIMIT = 5000;

    public function __construct(private readonly AuditLogQuery $auditLogQuery)
    {
    }

    public function index(AuditLogFilterRequest $request): View
    {
        $user = $request->user();

        return view('admin.audit.index', [
            'activities' => $this->auditLogQuery->build($user, $request->validated())->paginate(25)->withQueryString(),
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name'),
            'filters' => $request->validated(),
            'canExport' => $user->can('export', Activity::class),
        ]);
    }

    public function export(AuditLogFilterRequest $request): StreamedResponse
    {
        Gate::authorize('export', Activity::class);

        $activities = $this->auditLogQuery
            ->build($request->user(), $request->validated())
            ->limit(self::EXPORT_LIMIT)
            ->get();

        return response()->streamDownload(function () use ($activities): void {
            $output = fopen('php://output', 'w');

            // UTF-8 byte order mark, so Excel shows Bangla text correctly.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['ID', 'Time', 'Log', 'Description', 'User', 'Subject type', 'Subject ID', 'Branch ID', 'Details']);

            foreach ($activities as $activity) {
                $details = json_encode(
                    $activity->attribute_changes?->toArray() ?: $activity->properties?->toArray() ?: [],
                    JSON_UNESCAPED_UNICODE
                );

                fputcsv($output, array_map($this->sanitizeCsvCell(...), [
                    $activity->id,
                    $activity->created_at?->format('Y-m-d H:i:s'),
                    $activity->log_name,
                    $activity->description,
                    $activity->causer?->name ?? '',
                    $activity->subject_type ? class_basename($activity->subject_type) : '',
                    $activity->subject_id,
                    $activity->branch_id,
                    $details,
                ]));
            }

            fclose($output);
        }, 'audit-log-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stops spreadsheet formula injection: a cell starting with = + - @ would be executed by Excel.
     */
    private function sanitizeCsvCell(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'" . $text
            : $text;
    }
}
