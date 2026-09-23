<?php

namespace App\Support\Security;

use Illuminate\Http\Request;

/**
 * Controleert de Svix-handtekening die Resend op zijn webhooks zet.
 *
 * Svix ondertekent de string "{id}.{timestamp}.{body}" met HMAC-SHA256 en
 * een secret dat base64 is, voorafgegaan door "whsec_". De header kan
 * meerdere handtekeningen bevatten (bij sleutelrotatie), gescheiden door
 * spaties en elk geprefixt met een versie: "v1,<base64>".
 *
 * Twee dingen zijn hier essentieel:
 *
 * - De vergelijking gebruikt hash_equals, zodat de duur van de controle
 *   niets verraadt over de juiste handtekening.
 * - Er zit een tijdvenster op. Zonder die check kan iemand een oud, geldig
 *   ondertekend verzoek eindeloos opnieuw afspelen.
 */
class SvixSignature
{
    /**
     * Hoeveel seconden een ondertekend verzoek geldig blijft.
     */
    public const TOLERANCE = 300;

    public function verify(Request $request, string $secret): bool
    {
        $id = $request->header('svix-id');
        $timestamp = $request->header('svix-timestamp');
        $signatures = $request->header('svix-signature');

        if (! is_string($id) || ! is_string($timestamp) || ! is_string($signatures)) {
            return false;
        }

        if (! $this->withinTolerance($timestamp)) {
            return false;
        }

        $key = base64_decode(str_starts_with($secret, 'whsec_')
            ? substr($secret, 6)
            : $secret, true);

        if ($key === false) {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            $id.'.'.$timestamp.'.'.$request->getContent(),
            $key,
            true,
        ));

        foreach (explode(' ', $signatures) as $candidate) {
            // Elke waarde ziet eruit als "v1,<base64>"; de versie laten we vallen.
            $parts = explode(',', $candidate, 2);

            if (count($parts) === 2 && hash_equals($expected, $parts[1])) {
                return true;
            }
        }

        return false;
    }

    private function withinTolerance(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= self::TOLERANCE;
    }
}
