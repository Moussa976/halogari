<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class DocumentVerificationService
{
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /**
     * Pré-vérification automatique des documents envoyés.
     * Une vérification KYC externe pourra compléter ce contrôle plus tard.
     *
     * @return array{valid: bool, reason: string}
     */
    public function verify(UploadedFile $file, string $type): array
    {
        $size = (int) $file->getSize();

        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return ['valid' => false, 'reason' => 'Fichier vide ou trop volumineux (max 2 Mo).'];
        }

        if (!$this->isAllowedDocumentFile($file)) {
            return ['valid' => false, 'reason' => 'Format non autorisé. Seuls les PDF, JPG ou PNG sont acceptés.'];
        }

        return ['valid' => true, 'reason' => 'Pré-vérification automatique validée.'];
    }

    public function isAllowedDocumentFile(UploadedFile $file): bool
    {
        $extension = $this->safeExtension($file);
        if ($extension === null) {
            return false;
        }

        $mimeType = (string) $file->getMimeType();
        if (in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return true;
        }

        return $this->extensionMatchesSignature($file, $extension);
    }

    public function safeExtension(UploadedFile $file): ?string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return in_array($extension, self::ALLOWED_EXTENSIONS, true) ? $extension : null;
    }

    private function extensionMatchesSignature(UploadedFile $file, string $extension): bool
    {
        $path = $file->getPathname();
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return false;
        }

        $bytes = (string) fread($handle, 12);
        fclose($handle);

        if ($extension === 'pdf') {
            return str_starts_with($bytes, '%PDF-');
        }

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return str_starts_with($bytes, "\xFF\xD8\xFF");
        }

        if ($extension === 'png') {
            return str_starts_with($bytes, "\x89PNG\r\n\x1A\n");
        }

        return false;
    }
}
