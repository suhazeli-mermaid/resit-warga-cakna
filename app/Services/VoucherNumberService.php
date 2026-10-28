<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoucherNumberService
{
    public function next(CarbonInterface $date): string
    {
        $key = $date->toDateString();

        $sequence = DB::transaction(function () use ($key): int {
            $row = DB::table('voucher_number_sequences')
                ->where('voucher_date', $key)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $inserted = DB::table('voucher_number_sequences')->insertOrIgnore([
                    'voucher_date' => $key,
                    'next_number' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($inserted === 1) {
                    return 1;
                }

                $row = DB::table('voucher_number_sequences')
                    ->where('voucher_date', $key)
                    ->lockForUpdate()
                    ->first();
            }

            if ($row === null) {
                throw new RuntimeException('Urutan nombor baucar tidak dapat dikunci.');
            }

            $number = (int) $row->next_number;

            DB::table('voucher_number_sequences')
                ->where('voucher_date', $key)
                ->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return $number;
        }, 5);

        return sprintf('B-%s-%03d', $date->format('Y-md'), $sequence);
    }
}
