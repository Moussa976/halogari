<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class DocumentVerificationService
{
    /**
     * Pré-vérification automatique des documents envoyés.
     * Une vérification KYC externe pourra compléter ce contrôle plus tard.
     *
     * @return array{valid: bool, reason: string}
     */
    public function verify(UploadedFile $file, string $type): array
    {
        $mimeType = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        $extension = strtolower((string) $file->guessExtension());

        $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($mimeType, $allowedMime, true)) {
            return ['valid' => false, 'reason' => 'Type MIME non autorisé pour ce document.'];
        }

        if (!in_array($extension, $allowedExt, true)) {
            return ['valid' => false, 'reason' => 'Extension non autorisée.'];
        }

        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return ['valid' => false, 'reason' => 'Fichier vide ou trop volumineux (max 2 Mo).'];
        }

        return ['valid' => true, 'reason' => 'Pré-vérification automatique validée.'];
    }
}
