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

    public function reportPayload(string $clientPhoneNumber, string $feature, string $publicKey): string
    {
        return json_encode([
            'client_phone_number' => $clientPhoneNumber,
            'feature' => $feature,
            'public_key' => $this->normalizePublicKey($publicKey),
        ], JSON_THROW_ON_ERROR);
    }

    public function blacklistCheckPayload(string $targetHash, string $publicKey): string
    {
        return json_encode([
            'target_hash' => strtolower($targetHash),
            'public_key' => $this->normalizePublicKey($publicKey),
        ], JSON_THROW_ON_ERROR);
    }

    public function verify(string $challengeId, string $publicKey, string $signature): bool
    {
        return $this->verificationDiagnostics($challengeId, $publicKey, $signature)['verified'];
    }

    public function verifyReport(string $clientPhoneNumber, string $feature, string $publicKey, string $signature): bool
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
            $this->reportPayload($clientPhoneNumber, $feature, $normalizedPublicKey),
            $decodedSignature,
            $publicKeyResource,
            OPENSSL_ALGO_SHA256,
        ) === 1;
    }

    public function verifyBlacklistCheck(string $targetHash, string $publicKey, string $signature): bool
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
            $this->blacklistCheckPayload($targetHash, $normalizedPublicKey),
            $decodedSignature,
            $publicKeyResource,
            OPENSSL_ALGO_SHA256,
        ) === 1;
    }

    public function normalizePublicKey(string $publicKey): string
    {
        $normalizedLineEndings = str_replace("\r\n", "\n", $publicKey);
        $lines = preg_split('/\n/', $normalizedLineEndings);

        if ($lines === false) {
            return trim($normalizedLineEndings);
        }

        $normalizedLines = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ), static fn (string $line): bool => $line !== ''));

        return implode("\n", $normalizedLines);
    }

    public function verificationDiagnostics(string $challengeId, string $publicKey, string $signature): array
    {
        $normalizedPublicKey = $this->normalizePublicKey($publicKey);
        $payload = $this->payload($challengeId, $normalizedPublicKey);
        $decodedSignature = base64_decode($signature, true);

        $diagnostics = [
            'challenge_id' => $challengeId,
            'raw_public_key_sha256' => hash('sha256', $publicKey),
            'normalized_public_key_sha256' => hash('sha256', $normalizedPublicKey),
            'public_key_first_line' => $this->publicKeyFirstLine($normalizedPublicKey),
            'public_key_last_line' => $this->publicKeyLastLine($normalizedPublicKey),
            'public_key_line_count' => $this->publicKeyLineCount($normalizedPublicKey),
            'public_key_body_length' => $this->publicKeyBodyLength($normalizedPublicKey),
            'public_key_block_type' => $this->publicKeyBlockType($normalizedPublicKey),
            'canonical_payload_sha256' => hash('sha256', $payload),
            'canonical_payload_length' => strlen($payload),
            'signature_base64_sha256' => hash('sha256', $signature),
            'signature_base64_length' => strlen($signature),
            'signature_decoded' => $decodedSignature !== false,
            'signature_sha256' => $decodedSignature === false ? null : hash('sha256', $decodedSignature),
            'signature_length' => $decodedSignature === false ? null : strlen($decodedSignature),
            'signature_first_byte' => $decodedSignature === false || $decodedSignature === ''
                ? null
                : strtolower(bin2hex($decodedSignature[0])),
            'public_key_loaded' => false,
            'openssl_key_type' => null,
            'openssl_verify_result' => null,
            'openssl_errors' => [],
            'verified' => false,
        ];

        if ($decodedSignature === false) {
            return $diagnostics;
        }

        $this->clearOpenSslErrors();
        $publicKeyResource = openssl_pkey_get_public($normalizedPublicKey);

        if ($publicKeyResource === false) {
            $diagnostics['openssl_errors'] = $this->collectOpenSslErrors();

            return $diagnostics;
        }

        $diagnostics['public_key_loaded'] = true;

        $publicKeyDetails = openssl_pkey_get_details($publicKeyResource);

        if (is_array($publicKeyDetails) && array_key_exists('type', $publicKeyDetails)) {
            $diagnostics['openssl_key_type'] = $publicKeyDetails['type'];
        }

        $this->clearOpenSslErrors();
        $verifyResult = openssl_verify(
            $payload,
            $decodedSignature,
            $publicKeyResource,
            OPENSSL_ALGO_SHA256,
        );

        $diagnostics['openssl_verify_result'] = $verifyResult;
        $diagnostics['openssl_errors'] = $this->collectOpenSslErrors();
        $diagnostics['verified'] = $verifyResult === 1;

        return $diagnostics;
    }

    private function clearOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {
        }
    }

    private function collectOpenSslErrors(): array
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return $errors;
    }

    private function publicKeyFirstLine(string $publicKey): ?string
    {
        $lines = preg_split('/\n/', $publicKey);

        return $lines[0] ?? null;
    }

    private function publicKeyLastLine(string $publicKey): ?string
    {
        $lines = preg_split('/\n/', $publicKey);

        if ($lines === false || $lines === []) {
            return null;
        }

        return $lines[count($lines) - 1] ?: null;
    }

    private function publicKeyLineCount(string $publicKey): int
    {
        $lines = preg_split('/\n/', $publicKey);

        return $lines === false ? 0 : count($lines);
    }

    private function publicKeyBodyLength(string $publicKey): int
    {
        $lines = preg_split('/\n/', $publicKey);

        if ($lines === false || count($lines) < 3) {
            return 0;
        }

        return strlen(implode('', array_slice($lines, 1, -1)));
    }

    private function publicKeyBlockType(string $publicKey): ?string
    {
        if (preg_match('/-----BEGIN ([A-Z0-9 ]+)-----/', $publicKey, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
