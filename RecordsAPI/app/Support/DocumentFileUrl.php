<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DocumentFileUrl
{
    public function make(int $versionId, ?Carbon $expiresAt = null): string
    {
        $expiresAt ??= now()->addMinutes((int) config('media-library.signed_url_expiry_minutes', 120));
        $path = route('documents.versions.file', ['version' => $versionId], absolute: false);
        $expires = $expiresAt->getTimestamp();

        return url($path).'?expires='.$expires.'&token='.$this->signature($path, $expires);
    }

    public function isValid(Request $request, int $versionId): bool
    {
        $path = route('documents.versions.file', ['version' => $versionId], absolute: false);
        $expires = (int) $request->query('expires');
        $token = (string) $request->query('token');
        $expected = $this->signature($path, $expires);

        \Illuminate\Support\Facades\Log::info('DocumentFileUrl::isValid check', [
            'versionId' => $versionId,
            'path' => $path,
            'request_path' => $request->path(),
            'expires' => $expires,
            'token' => $token,
            'expected' => $expected,
            'time_valid' => $expires > now()->getTimestamp(),
            'hash_match' => hash_equals($expected, $token),
        ]);

        return $expires > now()->getTimestamp()
            && hash_equals($expected, $token);
    }

    protected function signature(string $path, int $expires): string
    {
        return hash_hmac('sha256', "{$path}:{$expires}", (string) config('app.key'));
    }
}
