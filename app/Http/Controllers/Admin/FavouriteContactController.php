<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FavouriteContact;
use App\Models\PaymentVoucher;
use App\Services\FavouriteContactService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FavouriteContactController extends Controller
{
    public function __construct(private readonly FavouriteContactService $contacts) {}

    public function index(Request $request)
    {
        $this->admin($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'usage' => ['nullable', Rule::in(['donor', 'voucher', 'both'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $query = FavouriteContact::query();

        if (filled($filters['search'] ?? null)) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function (Builder $search) use ($term) {
                $search->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        match ($filters['usage'] ?? null) {
            'donor' => $query->where('is_donor_favourite', true)->where('is_voucher_recipient_favourite', false),
            'voucher' => $query->where('is_donor_favourite', false)->where('is_voucher_recipient_favourite', true),
            'both' => $query->where('is_donor_favourite', true)->where('is_voucher_recipient_favourite', true),
            default => null,
        };
        match ($filters['status'] ?? null) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        return view('admin.favourite-contacts.index', [
            'contacts' => $query->latest('updated_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        $this->admin($request);

        return view('admin.favourite-contacts.create');
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $contact = $this->contacts->saveManaged($this->validatedContact($request), $request->user());

        return redirect()->route('admin.favourite-contacts.edit', $contact)
            ->with('success', 'Favourite baharu berjaya disimpan.');
    }

    public function edit(Request $request, FavouriteContact $favouriteContact)
    {
        $this->admin($request);

        return view('admin.favourite-contacts.edit', ['contact' => $favouriteContact]);
    }

    public function update(Request $request, FavouriteContact $favouriteContact)
    {
        $this->admin($request);
        $this->contacts->saveManaged($this->validatedContact($request), $request->user(), $favouriteContact);

        return back()->with('success', 'Favourite berjaya dikemas kini. Rekod transaksi lama tidak berubah.');
    }

    public function toggle(Request $request, FavouriteContact $favouriteContact)
    {
        $this->admin($request);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $favouriteContact->forceFill([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('success', $favouriteContact->is_active ? 'Favourite diaktifkan.' : 'Favourite dinyahaktifkan.');
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'usage' => ['required', Rule::in([FavouriteContact::USAGE_DONOR, FavouriteContact::USAGE_VOUCHER])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $this->authorizeSelection($request, $validated['usage']);

        $query = FavouriteContact::query()->active()->forUsage($validated['usage']);
        if (filled($validated['q'] ?? null)) {
            $term = '%'.addcslashes((string) $validated['q'], '%_\\').'%';
            $query->where(function (Builder $search) use ($term) {
                $search->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return response()->json([
            'data' => $query->orderByDesc('last_used_at')->orderBy('name')->limit(15)->get()->map(fn (FavouriteContact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'usage' => $contact->usageLabel(),
                'identity_masked' => $contact->maskedIdentity(),
                'bank_account_masked' => $contact->maskedBankAccount(),
            ]),
        ]);
    }

    public function prefill(Request $request, FavouriteContact $favouriteContact)
    {
        $validated = $request->validate([
            'usage' => ['required', Rule::in([FavouriteContact::USAGE_DONOR, FavouriteContact::USAGE_VOUCHER])],
        ]);
        $this->authorizeSelection($request, $validated['usage']);
        abort_unless($favouriteContact->is_active, 404);
        abort_unless(
            $validated['usage'] === FavouriteContact::USAGE_DONOR
                ? $favouriteContact->is_donor_favourite
                : $favouriteContact->is_voucher_recipient_favourite,
            404,
        );

        $data = [
            'name' => $favouriteContact->name,
            'identity_document' => $favouriteContact->identityValue(),
            'phone' => $favouriteContact->phone,
            'email' => $favouriteContact->email,
            'address' => $favouriteContact->address,
        ];
        if ($validated['usage'] === FavouriteContact::USAGE_VOUCHER) {
            $data['bank_name'] = $favouriteContact->bank_name;
            $data['bank_account_no'] = $favouriteContact->bank_account_no;
        }

        return response()->json(['data' => $data]);
    }

    private function validatedContact(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'identity_document' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_no' => ['nullable', 'string', 'max:100'],
            'is_donor_favourite' => ['nullable', 'boolean'],
            'is_voucher_recipient_favourite' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! ($validated['is_donor_favourite'] ?? false) && ! ($validated['is_voucher_recipient_favourite'] ?? false)) {
            throw ValidationException::withMessages(['usage' => 'Pilih sekurang-kurangnya satu kategori Favourite.']);
        }

        return $validated;
    }

    private function authorizeSelection(Request $request, string $usage): void
    {
        if ($usage === FavouriteContact::USAGE_DONOR) {
            abort_unless($request->user()?->canCreateReceipt(), 403);

            return;
        }

        Gate::forUser($request->user())->authorize('create', PaymentVoucher::class);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
