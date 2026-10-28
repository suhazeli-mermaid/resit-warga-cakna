<?php

namespace App\Services;

use App\Exceptions\PotentialDuplicateFavouriteException;
use App\Models\FavouriteContact;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FavouriteContactService
{
    /**
     * Store or update a shared favourite only when explicitly requested.
     * Transaction records never retain a live relationship to this contact.
     */
    public function saveFromTransaction(
        array $data,
        string $usage,
        User $actor,
        ?int $selectedId = null,
        ?int $confirmedDuplicateId = null,
    ): FavouriteContact {
        abort_unless($actor->canManageFavourites(), 403);

        return DB::transaction(function () use ($data, $usage, $actor, $selectedId, $confirmedDuplicateId) {
            if ($selectedId) {
                $contact = FavouriteContact::query()->lockForUpdate()->findOrFail($selectedId);
                abort_unless($contact->is_active, 409, 'Favourite yang dipilih tidak lagi aktif.');

                return $this->updateContact($contact, $data, $usage, $actor);
            }

            $duplicate = $this->findDuplicate($data);
            if ($duplicate && ! $duplicate['strong']) {
                if (! $confirmedDuplicateId || $duplicate['contact']->id !== $confirmedDuplicateId) {
                    throw new PotentialDuplicateFavouriteException($duplicate['contact']);
                }
            }

            if ($duplicate) {
                $contact = FavouriteContact::query()->lockForUpdate()->findOrFail($duplicate['contact']->id);

                return $this->updateContact($contact, $data, $usage, $actor);
            }

            return FavouriteContact::query()->create(array_merge(
                $this->attributes($data),
                $this->usageAttributes($usage),
                [
                    'is_active' => true,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'last_used_at' => now(),
                ],
            ));
        });
    }

    public function markUsed(FavouriteContact $contact): void
    {
        if ($contact->is_active) {
            $contact->forceFill(['last_used_at' => now()])->save();
        }
    }

    public function saveManaged(array $data, User $actor, ?FavouriteContact $contact = null): FavouriteContact
    {
        abort_unless($actor->canManageFavourites(), 403);
        $duplicate = $this->findDuplicate($data, $contact?->id);
        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => "Kemungkinan rekod pendua: {$duplicate['contact']->name}. Kemaskini rekod sedia ada.",
            ]);
        }

        $attributes = array_merge($this->attributes($data), [
            'is_donor_favourite' => (bool) ($data['is_donor_favourite'] ?? false),
            'is_voucher_recipient_favourite' => (bool) ($data['is_voucher_recipient_favourite'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'updated_by' => $actor->id,
        ]);

        if ($contact) {
            $contact->fill($attributes);
            if (array_key_exists('identity_document', $data)) {
                $contact->setAttribute('ic_passport', null);
            }
            $contact->save();

            return $contact;
        }

        return FavouriteContact::query()->create(array_merge($attributes, ['created_by' => $actor->id]));
    }

    /** @return array{contact: FavouriteContact, strong: bool}|null */
    public function findDuplicate(array $data, ?int $ignoreId = null): ?array
    {
        $hashes = $this->hashes($data);
        $query = FavouriteContact::query()->when($ignoreId, fn ($builder) => $builder->whereKeyNot($ignoreId));
        $query->where(function ($match) use ($hashes) {
            foreach ($hashes as $column => $hash) {
                if ($hash) {
                    $match->orWhere($column, $hash);
                }
            }
        });
        $candidates = collect($hashes)->filter()->isEmpty() ? collect() : $query->get();

        // Legacy donor rows have no lookup hashes. Compare normalized values in
        // memory without modifying those rows or exposing them to the client.
        $legacy = FavouriteContact::query()
            ->when($ignoreId, fn ($builder) => $builder->whereKeyNot($ignoreId))
            ->whereNull('email_lookup_hash')
            ->whereNull('phone_lookup_hash')
            ->whereNull('identity_lookup_hash')
            ->get();

        foreach ($candidates->merge($legacy)->unique('id') as $candidate) {
            $identityMatch = filled($data['identity_document'] ?? null)
                && $this->normalizeIdentity($data['identity_document']) === $this->normalizeIdentity($candidate->identityValue());
            $emailMatch = filled($data['email'] ?? null)
                && $this->normalizeEmail($data['email']) === $this->normalizeEmail($candidate->email);
            $phoneMatch = filled($data['phone'] ?? null)
                && $this->normalizePhone($data['phone']) === $this->normalizePhone($candidate->phone);
            $nameMatch = $this->normalizeName($data['name'] ?? '') === $this->normalizeName($candidate->name);

            if ($identityMatch || ($emailMatch && $phoneMatch)) {
                return ['contact' => $candidate, 'strong' => true];
            }

            if (($emailMatch || $phoneMatch) && $nameMatch) {
                return ['contact' => $candidate, 'strong' => true];
            }

            if ($emailMatch || $phoneMatch) {
                return ['contact' => $candidate, 'strong' => false];
            }
        }

        return null;
    }

    private function updateContact(FavouriteContact $contact, array $data, string $usage, User $actor): FavouriteContact
    {
        $contact->fill(array_merge($this->attributes($data), [
            'is_donor_favourite' => $contact->is_donor_favourite || $usage === FavouriteContact::USAGE_DONOR,
            'is_voucher_recipient_favourite' => $contact->is_voucher_recipient_favourite || $usage === FavouriteContact::USAGE_VOUCHER,
            'updated_by' => $actor->id,
            'last_used_at' => now(),
        ]));
        if (array_key_exists('identity_document', $data)) {
            $contact->setAttribute('ic_passport', null);
        }
        $contact->save();

        return $contact;
    }

    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, [
            'name', 'phone', 'email', 'identity_document', 'address', 'bank_name', 'bank_account_no',
        ]);

        return array_merge($attributes, $this->hashes($data));
    }

    /** @return array<string, string|null> */
    private function hashes(array $data): array
    {
        return [
            'email_lookup_hash' => $this->lookupHash($this->normalizeEmail($data['email'] ?? null)),
            'phone_lookup_hash' => $this->lookupHash($this->normalizePhone($data['phone'] ?? null)),
            'identity_lookup_hash' => $this->lookupHash($this->normalizeIdentity($data['identity_document'] ?? null)),
        ];
    }

    private function usageAttributes(string $usage): array
    {
        return [
            'is_donor_favourite' => $usage === FavouriteContact::USAGE_DONOR,
            'is_voucher_recipient_favourite' => $usage === FavouriteContact::USAGE_VOUCHER,
        ];
    }

    private function lookupHash(?string $normalized): ?string
    {
        return filled($normalized)
            ? hash_hmac('sha256', $normalized, (string) config('app.key'))
            : null;
    }

    private function normalizeName(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }

    private function normalizeEmail(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function normalizePhone(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function normalizeIdentity(?string $value): string
    {
        return mb_strtoupper(preg_replace('/[^\pL\pN]+/u', '', (string) $value) ?? '');
    }
}
