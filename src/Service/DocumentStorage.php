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
        $directory = $this->documentsDirectory . DIRECTORY_SEPARATOR . $userId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new FileException('Impossible de préparer le dossier de stockage.');
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = strtolower((string) $this->slugger->slug($originalName ?: 'document'));
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'));
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
