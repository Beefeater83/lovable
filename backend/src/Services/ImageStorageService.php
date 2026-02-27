<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;

class ImageStorageService
{
    private Filesystem $filesystem;

    public function __construct(
        private string $uploadDir,
        Filesystem $filesystem
    ) {
        $this->filesystem = $filesystem;
    }

    public function upload(UploadedFile $file): string
    {
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $newFilename = uniqid('', true) . '.' . $extension;
        $file->move($this->uploadDir, $newFilename);
        return $newFilename;
    }

    public function remove(string $filename): void
    {
        $basename = basename($filename);
        $fullPath = $this->uploadDir . '/' . $basename;
        $this->filesystem->remove($fullPath);
    }
}
