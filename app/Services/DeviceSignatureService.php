<?php

namespace App\Services;

class DeviceSignatureService
{
    public function payload(string $challengeId, string $publicKey): string
    {
        return json_encode([
            'challenge_id' => $challengeId,
            'public_key' => $this->normalizePublicKey($publicKey),
        ], JSON_THROW_ON_ERROR);
    }

    public function verify(string $challengeId, string $publicKey, string $signature): bool
    {
        $decodedSignature = base64_decode($signature, true);

        if ($decodedSignature === false) {
            return false;
        }

        $normalizedPublicKey = $this->normalizePublicKey($publicKey);
        $publicKeyResource = openssl_pkey_get_public($normalizedPublicKey);

        if ($publicKeyResource === false) {
            return false;
        }

        return openssl_verify(
            $this->payload($challengeId, $normalizedPublicKey),
            $decodedSignature,
            $publicKeyResource,
            OPENSSL_ALGO_SHA256,
        ) === 1;
    }

    public function normalizePublicKey(string $publicKey): string
    {
        return trim(str_replace("\r\n", "\n", $publicKey));
    }
}
