<?php

namespace App\Http\Controllers;

use App\Exceptions\PotentialDuplicateFavouriteException;
use App\Exceptions\VoucherReadinessException;
use App\Models\FavouriteContact;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Services\FavouriteContactService;
use App\Services\OrganizationBrandingService;
use App\Services\PaymentVoucherAuditService;
use App\Services\PaymentVoucherWorkflow;
use App\Services\VoucherNotificationService;
use App\Services\VoucherNumberService;
use App\Services\VoucherSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class PaymentVoucherController extends Controller
{
    public function __construct(
        private readonly PaymentVoucherWorkflow $workflow,
        private readonly PaymentVoucherAuditService $audits,
        private readonly VoucherNumberService $numbers,
        private readonly VoucherNotificationService $notifications,
        private readonly VoucherSnapshotService $snapshots,
        private readonly OrganizationBrandingService $branding,
        private readonly FavouriteContactService $favouriteContacts,
    ) {}

    public function dashboard(Request $request)
    {
        Gate::authorize('viewAny', PaymentVoucher::class);
        $base = PaymentVoucher::query()->visibleTo($request->user());
        $stats = [
            'total' => (clone $base)->count(),
            'pending_approval' => (clone $base)->where('status', PaymentVoucher::PENDING_APPROVAL)->count(),
            'pending_payment' => (clone $base)->whereIn('status', [PaymentVoucher::APPROVED, PaymentVoucher::ISSUED])->count(),
            'pending_ack' => (clone $base)->where('status', PaymentVoucher::AWAITING_ACKNOWLEDGEMENT)->count(),
            'completed' => (clone $base)->where('status', PaymentVoucher::COMPLETED)->count(),
            'paid_total' => (clone $base)->whereIn('status', [PaymentVoucher::AWAITING_ACKNOWLEDGEMENT, PaymentVoucher::COMPLETED, PaymentVoucher::ARCHIVED])->sum('paid_amount'),
        ];

        return view('vouchers.dashboard', compact('stats'));
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', PaymentVoucher::class);
        $filters = $this->validatedListFilters($request, true);
        $base = PaymentVoucher::query()
            ->with(['applicant', 'approver', 'latestAcknowledgement'])
            ->visibleTo($request->user())
            ->where('status', '!=', PaymentVoucher::ARCHIVED);
        $this->applyListFilters($base, $filters);

        return view('vouchers.index', [
            'vouchers' => $base->latest('voucher_date')->latest('id')->paginate(15)->withQueryString(),
            'statuses' => array_values(array_diff($this->statuses(), [PaymentVoucher::ARCHIVED])),
            'applicants' => $this->filterApplicants($request),
        ]);
    }

    public function archiveIndex(Request $request)
    {
        Gate::authorize('viewAny', PaymentVoucher::class);
        $filters = $this->validatedListFilters($request, false);
        $base = PaymentVoucher::query()
            ->with(['applicant', 'approver', 'latestAcknowledgement'])
            ->visibleTo($request->user())
            ->where('status', PaymentVoucher::ARCHIVED);
        $this->applyListFilters($base, $filters);

        return view('vouchers.archive', [
            'vouchers' => $base->latest('archived_at')->latest('id')->paginate(15)->withQueryString(),
            'applicants' => $this->filterApplicants($request),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', PaymentVoucher::class);

        return view('vouchers.create', [
            'applicants' => $request->user()->isAdmin()
                ? User::query()
                    ->where(fn ($query) => $query->where('role', 'admin')->orWhere('can_create_voucher', true))
                    ->orderBy('name')
                    ->get()
                : null,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', PaymentVoucher::class);
        $validated = $this->validateVoucher($request);
        $favourite = $request->validate([
            'favourite_contact_id' => 'nullable|integer|exists:donors,id',
            'save_as_favourite' => 'nullable|boolean',
            'duplicate_contact_id' => 'nullable|integer|exists:donors,id',
            'confirm_favourite_duplicate' => 'sometimes|accepted',
        ]);
        if ($request->boolean('save_as_favourite')) {
            abort_unless($request->user()->isAdmin(), 403);
        }
        $applicantId = $request->user()->isAdmin()
            ? (int) ($validated['applicant_id'] ?? $request->user()->id)
            : $request->user()->id;

        try {
            $voucher = DB::transaction(function () use ($validated, $favourite, $applicantId, $request) {
                $date = Carbon::parse($validated['voucher_date'])->startOfDay();
                $voucher = PaymentVoucher::query()->create(array_merge(
                    collect($validated)->except('applicant_id')->all(),
                    [
                        'voucher_no' => $this->numbers->next($date),
                        'status' => PaymentVoucher::DRAFT,
                        'applicant_id' => $applicantId,
                    ],
                ));
                $this->audits->record($voucher, 'created', $request->user());
                $selected = null;

                if (filled($favourite['favourite_contact_id'] ?? null)) {
                    $selected = FavouriteContact::query()
                        ->active()
                        ->forUsage(FavouriteContact::USAGE_VOUCHER)
                        ->findOrFail($favourite['favourite_contact_id']);
                }

                if ($request->boolean('save_as_favourite')) {
                    $this->favouriteContacts->saveFromTransaction(
                        data: [
                            'name' => $voucher->recipient_name,
                            'identity_document' => $voucher->recipient_ic,
                            'phone' => $voucher->recipient_phone,
                            'email' => $voucher->recipient_email,
                            'address' => $voucher->recipient_address,
                            'bank_name' => $voucher->bank_name,
                            'bank_account_no' => $voucher->bank_account_no,
                        ],
                        usage: FavouriteContact::USAGE_VOUCHER,
                        actor: $request->user(),
                        selectedId: $selected?->id,
                        confirmedDuplicateId: $request->boolean('confirm_favourite_duplicate')
                            ? (int) ($favourite['duplicate_contact_id'] ?? 0)
                            : null,
                    );
                } elseif ($selected) {
                    $this->favouriteContacts->markUsed($selected);
                }

                return $voucher;
            });
        } catch (PotentialDuplicateFavouriteException $exception) {
            return back()->withInput()->withErrors([
                'save_as_favourite' => 'Favourite yang mungkin sama telah ditemui. Semak rekod dan sahkan jika anda mahu mengemas kini rekod tersebut.',
            ])->with('favourite_duplicate_candidate', [
                'id' => $exception->contact->id,
                'name' => $exception->contact->name,
                'phone' => $exception->contact->phone,
                'email' => $exception->contact->email,
            ]);
        }

        return redirect()->route('vouchers.show', $voucher)->with('success', 'Draf baucar berjaya dicipta.');
    }

    public function show(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('view', $voucher);
        $voucher->load(['applicant', 'approver', 'paymentConfirmer', 'audits.actor', 'acknowledgements', 'latestAcknowledgement']);

        return view('vouchers.show', [
            'voucher' => $voucher,
            'canViewSensitive' => Gate::allows('viewSensitive', $voucher),
            'applicantReadiness' => $this->snapshots->userReadiness($voucher->applicant),
            'currentUserReadiness' => $this->snapshots->userReadiness($request->user()),
            'issueReadiness' => $this->snapshots->issueReadiness($voucher),
            'officialStampReady' => $this->snapshots->hasOfficialStamp(),
        ]);
    }

    public function edit(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('update', $voucher);

        return view('vouchers.edit', compact('voucher'));
    }

    public function update(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('update', $voucher);
        $voucher->update(collect($this->validateVoucher($request))->except(['applicant_id', 'voucher_date'])->all());

        return redirect()->route('vouchers.show', $voucher)->with('success', 'Draf baucar dikemas kini.');
    }

    public function submit(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('submit', $voucher);
        try {
            $this->workflow->submit($voucher, $request->user());
        } catch (VoucherReadinessException $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        return back()->with('success', 'Baucar dihantar untuk kelulusan.');
    }

    public function approve(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('approve', $voucher);
        try {
            $this->workflow->approve($voucher, $request->user());
        } catch (VoucherReadinessException $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        return back()->with('success', 'Baucar diluluskan.');
    }

    public function reject(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('approve', $voucher);
        $validated = $request->validate(['reason' => 'required|string|max:2000']);
        $this->workflow->reject($voucher, $request->user(), $validated['reason']);

        return back()->with('success', 'Baucar ditolak dan sebab direkodkan.');
    }

    public function issue(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('issue', $voucher);
        try {
            $voucher = $this->workflow->issue($voucher, $request->user());
        } catch (VoucherReadinessException $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }
        $this->notifications->issued($voucher);

        return back()->with('success', 'Baucar rasmi dikeluarkan dan snapshot dikunci.');
    }

    public function confirmPayment(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('confirmPayment', $voucher);
        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0.01|max:99999999999.99',
            'transaction_date' => 'required|date|before_or_equal:today',
            'transaction_reference' => 'required|string|max:255',
            'payment_proof' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(4096)],
        ]);
        if ($voucher->status === PaymentVoucher::ISSUED && $request->hasFile('payment_proof')) {
            $validated['payment_proof_path'] = $request->file('payment_proof')->store("vouchers/{$voucher->id}/payment-proofs", 'local');
        }
        [$voucher, $token, $acknowledgementActivated] = $this->workflow->confirmPayment($voucher, $request->user(), $validated);
        if (! $acknowledgementActivated) {
            return back()->with('success', 'Bayaran telah disahkan dan permintaan Akuan sedia ada kekal aktif. Tiada e-mel pendua dihantar.');
        }
        $sent = $this->notifications->paymentConfirmed($voucher, $token);
        if ($sent) {
            $this->audits->record($voucher, 'acknowledgement_sent', $request->user());
        } else {
            $this->audits->record($voucher, 'acknowledgement_notification_failed', $request->user(), 'E-mel tidak dapat dihantar oleh mailer selamat yang dikonfigurasi.');
        }

        return back()->with(
            $sent ? 'success' : 'error',
            $sent
                ? 'Bayaran disahkan dan pautan akuan satu kali telah dihantar.'
                : 'Bayaran disahkan, tetapi e-mel akuan gagal dihantar. Gunakan Hantar Semula Pautan Akuan.',
        );
    }

    public function resendAcknowledgement(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('resendAcknowledgement', $voucher);
        [$voucher, $token] = $this->workflow->refreshAcknowledgementToken($voucher, $request->user());
        $sent = $this->notifications->paymentConfirmed($voucher, $token, resentBy: $request->user());
        $this->audits->record(
            $voucher,
            $sent ? 'acknowledgement_sent' : 'acknowledgement_notification_failed',
            $request->user(),
            $sent ? null : 'E-mel tidak dapat dihantar oleh mailer selamat yang dikonfigurasi.',
        );

        return back()->with(
            $sent ? 'success' : 'error',
            $sent ? 'Pautan akuan baharu telah dihantar; pautan lama dibatalkan.' : 'Pautan baharu dijana tetapi e-mel gagal dihantar.',
        );
    }

    public function cancel(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('cancel', $voucher);
        $validated = $request->validate(['reason' => 'required|string|max:2000']);
        $this->workflow->cancel($voucher, $request->user(), $validated['reason']);

        return back()->with('success', 'Baucar dibatalkan tanpa memadam rekod kewangan.');
    }

    public function reissue(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('reissue', $voucher);
        $new = $this->workflow->reissue($voucher, $request->user());

        return redirect()->route('vouchers.edit', $new)->with('success', 'Draf gantian dicipta dengan nombor baharu.');
    }

    public function archive(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('archive', $voucher);
        $validated = $request->validate(['reason' => 'nullable|string|max:2000']);
        $this->workflow->archive($voucher, $request->user(), $validated['reason'] ?? null);

        return redirect()->route('vouchers.archive.index')->with('success', 'Baucar diarkibkan; rekod tidak dipadam.');
    }

    public function document(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('view', $voucher);
        abort_unless($voucher->isIssuedDocument(), 409);

        return view('vouchers.document', [
            'voucher' => $voucher,
            'images' => $this->snapshots->documentImages($voucher),
            'branding' => $this->branding->documentBranding(),
        ]);
    }

    public function documentPdf(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('view', $voucher);
        abort_unless($voucher->isIssuedDocument(), 409);
        $pdf = Pdf::loadView('pdf.payment-voucher', [
            'voucher' => $voucher,
            'images' => $this->snapshots->documentImages($voucher),
            'branding' => $this->branding->documentBranding(),
        ])
            ->setPaper('a4');

        return $pdf->download("Baucar-{$voucher->voucher_no}.pdf");
    }

    public function acknowledgementPdf(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('view', $voucher);
        abort_unless(in_array($voucher->status, [PaymentVoucher::COMPLETED, PaymentVoucher::ARCHIVED], true), 409);
        $acknowledgement = $voucher->acknowledgements()->whereNotNull('used_at')->latest('used_at')->firstOrFail();
        $acknowledgement->setRelation('voucher', $voucher);

        return Pdf::loadView('pdf.voucher-acknowledgement', [
            'voucher' => $voucher,
            'acknowledgement' => $acknowledgement,
            'images' => $this->snapshots->documentImages($voucher),
            'branding' => $this->branding->documentBranding(),
        ])->setPaper('a4')
            ->download("Akuan-{$acknowledgement->acknowledgement_no}.pdf");
    }

    public function paymentProof(Request $request, PaymentVoucher $voucher)
    {
        Gate::authorize('viewSensitive', $voucher);
        abort_unless($voucher->payment_proof_path && Storage::disk('local')->exists($voucher->payment_proof_path), 404);

        return Storage::disk('local')->download($voucher->payment_proof_path, 'Bukti-Bayaran-'.$voucher->voucher_no.'.'.pathinfo($voucher->payment_proof_path, PATHINFO_EXTENSION));
    }

    private function validateVoucher(Request $request): array
    {
        return $request->validate([
            'voucher_date' => 'required|date',
            'applicant_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:50',
            'recipient_email' => 'required|email|max:255',
            'recipient_address' => 'nullable|string|max:2000',
            'recipient_ic' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:120',
            'bank_account_no' => 'nullable|string|max:100',
            'purpose' => 'required|string|max:3000',
            'amount' => 'required|numeric|min:0.01|max:99999999999.99',
            'committee_approval_date' => 'nullable|date|before_or_equal:today',
        ]);
    }

    private function validatedListFilters(Request $request, bool $withStatus): array
    {
        $rules = [
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'applicant_id' => 'nullable|integer|exists:users,id',
        ];

        if ($withStatus) {
            $rules['status'] = ['nullable', Rule::in(array_values(array_diff($this->statuses(), [PaymentVoucher::ARCHIVED])))];
        }

        return $request->validate($rules);
    }

    private function applyListFilters(Builder $query, array $filters): void
    {
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['search'] ?? null)) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function (Builder $search) use ($term) {
                $search->where('voucher_no', 'like', $term)
                    ->orWhere('recipient_name', 'like', $term)
                    ->orWhere('purpose', 'like', $term)
                    ->orWhereHas('applicant', fn (Builder $applicant) => $applicant->where('name', 'like', $term));
            });
        }
        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('voucher_date', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('voucher_date', '<=', $filters['date_to']);
        }
        if (filled($filters['applicant_id'] ?? null)) {
            $query->where('applicant_id', $filters['applicant_id']);
        }
    }

    private function filterApplicants(Request $request)
    {
        if (! ($request->user()->isAdmin() || $request->user()->isSecretary())) {
            return collect();
        }

        return User::query()
            ->whereIn('role', ['ajk', 'admin', 'setiausaha'])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function statuses(): array
    {
        return [
            PaymentVoucher::DRAFT, PaymentVoucher::PENDING_APPROVAL, PaymentVoucher::APPROVED,
            PaymentVoucher::ISSUED, PaymentVoucher::PAYMENT_CONFIRMED,
            PaymentVoucher::AWAITING_ACKNOWLEDGEMENT, PaymentVoucher::COMPLETED,
            PaymentVoucher::REJECTED, PaymentVoucher::CANCELLED, PaymentVoucher::ARCHIVED,
        ];
    }
}
