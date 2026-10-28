<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Services\Email\EmailRelatedRecordResolver;
use App\Services\Email\EmailResendService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class EmailLogController extends Controller
{
    public function __construct(
        private readonly EmailResendService $resender,
        private readonly EmailRelatedRecordResolver $relatedRecords,
    ) {}

    public function index(Request $request)
    {
        $this->admin($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([EmailLog::PENDING, EmailLog::SENT, EmailLog::FAILED])],
            'mail_type' => ['nullable', Rule::in(array_keys(EmailLog::typeOptions()))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = EmailLog::query()->with(['resentBy', 'resendOf']);
        if (filled($filters['search'] ?? null)) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function (Builder $search) use ($term) {
                $search->where('recipient_name', 'like', $term)
                    ->orWhere('recipient_email', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('related_reference', 'like', $term)
                    ->orWhere('mail_type', 'like', $term);
            });
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['mail_type'] ?? null)) {
            $query->where('mail_type', $filters['mail_type']);
        }
        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return view('admin.email-logs.index', [
            'logs' => $query->latest()->paginate(20)->withQueryString(),
            'types' => EmailLog::typeOptions(),
        ]);
    }

    public function show(Request $request, EmailLog $emailLog)
    {
        $this->admin($request);
        $emailLog->load(['resendOf', 'resends.resentBy', 'resentBy']);

        return view('admin.email-logs.show', [
            'emailLog' => $emailLog,
            'relatedLink' => $this->relatedRecords->link($emailLog, $request->user()),
        ]);
    }

    public function resend(Request $request, EmailLog $emailLog)
    {
        $this->admin($request);
        $request->validate(['confirmed' => ['accepted']]);

        try {
            $sent = $this->resender->resend($emailLog, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable) {
            return back()->with('error', 'E-mel tidak dapat dihantar semula daripada rekod sumber yang dibenarkan.');
        }

        return back()->with(
            $sent ? 'success' : 'error',
            $sent
                ? 'E-mel baharu telah dijana daripada rekod sumber dan diserahkan kepada mail transport.'
                : 'E-mel gagal dihantar atau keadaan rekod tidak lagi membenarkan penghantaran semula.',
        );
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
