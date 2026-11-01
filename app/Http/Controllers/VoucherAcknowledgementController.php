<?php

namespace App\Http\Controllers;

use App\Models\VoucherAcknowledgement;
use App\Services\OrganizationBrandingService;
use App\Services\PaymentVoucherWorkflow;
use App\Services\VoucherNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VoucherAcknowledgementController extends Controller
{
    public function __construct(
        private readonly PaymentVoucherWorkflow $workflow,
        private readonly VoucherNotificationService $notifications,
        private readonly OrganizationBrandingService $branding,
    ) {}

    public function show(string $token)
    {
        $acknowledgement = $this->resolve($token);
        if (! $acknowledgement) {
            return $this->unavailable('invalid', 404);
        }
        if ($acknowledgement->used_at !== null) {
            return $this->unavailable('used', 410);
        }
        if ($acknowledgement->expires_at->isPast()) {
            return $this->unavailable('expired', 410);
        }

        return view('vouchers.acknowledge', ['acknowledgement' => $acknowledgement]);
    }

    public function store(Request $request, string $token)
    {
        $acknowledgement = $this->resolve($token);
        if (! $acknowledgement) {
            return $this->unavailable('invalid', 404);
        }
        if ($acknowledgement->used_at !== null) {
            return $this->unavailable('used', 410);
        }
        if ($acknowledgement->expires_at->isPast()) {
            return $this->unavailable('expired', 410);
        }
        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'accepted' => 'accepted',
            'notes' => 'nullable|string|max:2000',
        ]);
        try {
            $voucher = $this->workflow->acknowledge($acknowledgement, $validated['recipient_name'], $validated['notes'] ?? null);
        } catch (RuntimeException) {
            return $this->unavailable('used', 410);
        }

        try {
            $this->notifications->acknowledgementCompleted($voucher);
        } catch (Throwable $exception) {
            // Completion is authoritative and must not be rolled back by a
            // document or notification infrastructure failure.
            Log::warning('Voucher acknowledgement completion notification could not be processed.', [
                'acknowledgement_id' => $acknowledgement->id,
                'exception' => $exception::class,
            ]);
        }

        return view('vouchers.acknowledgement-complete', [
            'voucher' => $voucher,
            'acknowledgement' => $acknowledgement->fresh(),
            'branding' => $this->branding->documentBranding(),
        ]);
    }

    private function resolve(string $token): ?VoucherAcknowledgement
    {
        if (strlen($token) !== 64 || ! ctype_alnum($token)) {
            return null;
        }

        return VoucherAcknowledgement::query()
            ->with('voucher')
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    private function unavailable(string $reason, int $status)
    {
        return response()->view('vouchers.acknowledgement-unavailable', compact('reason'), $status);
    }
}
