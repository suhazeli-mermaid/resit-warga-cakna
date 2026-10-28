<?php

namespace Tests\Unit;

use App\Services\Billplz\XSignatureVerifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillplzXSignatureTest extends TestCase
{
    #[Test]
    public function it_matches_the_official_nested_redirect_signature_vector(): void
    {
        $payload = [
            'billplz' => [
                'id' => 'zq0tm2wc',
                'paid' => 'true',
                'paid_at' => '2018-09-27 15:15:09 +0800',
                'x_signature' => 'ignored-when-signing',
            ],
        ];

        $signature = (new XSignatureVerifier)->sign(
            $payload,
            'S-s7b4yWpp9h7rrkNM1i3Z_g',
        );

        $this->assertSame(
            '4aab095fe5a39b1d534500988f9a0cb085cd1b6d5bbb55dd4e02ea6fa102b47b',
            $signature,
        );
    }
}
