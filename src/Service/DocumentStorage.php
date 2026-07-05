<?php

namespace App\Service;

use App\Entity\Document;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class DocumentStorage
{
    private string $documentsDirectory;
    private string $legacyDocumentsDirectory;
    private SluggerInterface $slugger;

    public function __construct(string $documentsDirectory, string $legacyDocumentsDirectory, SluggerInterface $slugger)
    {
        $this->documentsDirectory = rtrim($documentsDirectory, '/\\');
        $this->legacyDocumentsDirectory = rtrim($legacyDocumentsDirectory, '/\\');
        $this->slugger = $slugger;
    }

    public function store(UploadedFile $file, int $userId): string
    {
        if (!is_dir($this->documentsDirectory) && !mkdir($this->documentsDirectory, 0777, true) && !is_dir($this->documentsDirectory)) {
            throw new FileException('Impossible de préparer le dossier principal de stockage.');
        }

        @chmod($this->documentsDirectory, 0777);

        $directory = $this->documentsDirectory . DIRECTORY_SEPARATOR . $userId;
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new FileException('Impossible de préparer le dossier de stockage.');
        }

        @chmod($directory, 0777);
        clearstatcache(true, $directory);
        if (!is_writable($directory)) {
            throw new FileException('Le dossier de stockage des documents n’est pas accessible en écriture.');
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = strtolower((string) $this->slugger->slug($originalName ?: 'document'));
        $safeName = substr($safeName, 0, 80) ?: 'document';
        $clientExtension = strtolower((string) $file->getClientOriginalExtension());
        $extension = in_array($clientExtension, ['pdf', 'jpg', 'jpeg', 'png'], true)
            ? $clientExtension
            : strtolower((string) ($file->guessExtension() ?: 'bin'));
        $filename = sprintf('%s-%s.%s', $safeName, bin2hex(random_bytes(16)), $extension);

        $file->move($directory, $filename);

        return $userId . '/' . $filename;
    }

    public function resolvePath(Document $document): ?string
    {
        $filename = (string) $document->getFilenameDocument();
        if ($filename === '' || str_contains($filename, '..')) {
            return null;
        }

        $privatePath = $this->documentsDirectory . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename);
        if (is_file($privatePath)) {
            return $privatePath;
        }

        $legacyPath = $this->legacyDocumentsDirectory . DIRECTORY_SEPARATOR . basename($filename);
        if (is_file($legacyPath)) {
            return $legacyPath;
        }

        return null;
    }
}
