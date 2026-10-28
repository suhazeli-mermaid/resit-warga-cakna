<?php

namespace App\Http\Controllers;

use App\Models\VoucherAcknowledgement;
use App\Services\OrganizationBrandingService;
use App\Services\PaymentVoucherWorkflow;
use App\Services\VoucherNotificationService;
use Illuminate\Http\Request;

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
        abort_unless($acknowledgement->isUsable(), 410, 'Pautan akuan telah luput atau telah digunakan.');

        return view('vouchers.acknowledge', ['acknowledgement' => $acknowledgement]);
    }

    public function store(Request $request, string $token)
    {
        $acknowledgement = $this->resolve($token);
        abort_unless($acknowledgement->isUsable(), 410, 'Pautan akuan telah luput atau telah digunakan.');
        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'accepted' => 'accepted',
            'notes' => 'nullable|string|max:2000',
        ]);
        $voucher = $this->workflow->acknowledge($acknowledgement, $validated['recipient_name'], $validated['notes'] ?? null);
        $this->notifications->acknowledgementCompleted($voucher);

        return view('vouchers.acknowledgement-complete', [
            'voucher' => $voucher,
            'acknowledgement' => $acknowledgement->fresh(),
            'branding' => $this->branding->documentBranding(),
        ]);
    }

    private function resolve(string $token): VoucherAcknowledgement
    {
        abort_unless(strlen($token) === 64 && ctype_alnum($token), 404);

        return VoucherAcknowledgement::query()
            ->with('voucher')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
    }
}
