<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\ValueObject\HotelCandidate;

final readonly class HotelCandidatePayloadSigner
{
    public function __construct(private string $secret)
    {
    }

    /**
     * @return array{payload: string, signature: string}
     */
    public function sign(HotelCandidate $candidate): array
    {
        $json = json_encode($candidate->toPayload(), JSON_THROW_ON_ERROR);
        $payload = base64_encode($json);

        return [
            'payload' => $payload,
            'signature' => $this->signature($payload),
        ];
    }

    public function verify(string $payload, string $signature): ?HotelCandidate
    {
        if (!hash_equals($this->signature($payload), $signature)) {
            return null;
        }

        $json = base64_decode($payload, true);
        if (!\is_string($json)) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? HotelCandidate::fromPayload($data) : null;
    }

    private function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}
