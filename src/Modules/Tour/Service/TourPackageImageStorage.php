<?php

namespace App\Modules\Tour\Service;

use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Entity\TourPackageImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class TourPackageImageStorage
{
    private const UPLOAD_ROOT = 'uploads/tours';
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private SluggerInterface $slugger,
        private Filesystem $filesystem,
    ) {
    }

    public function store(TourPackage $package, UploadedFile $file): string
    {
        $packageId = $package->getId();
        if ($packageId === null) {
            throw new \LogicException('Tour package must be persisted before image upload.');
        }

        $mimeType = $file->getMimeType();
        if (!\is_string($mimeType) || !isset(self::ALLOWED_MIME_EXTENSIONS[$mimeType])) {
            throw new \InvalidArgumentException('Unsupported tour package image MIME type.');
        }

        $extension = self::ALLOWED_MIME_EXTENSIONS[$mimeType];
        $baseName = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        $safeBaseName = (string) $this->slugger->slug($baseName)->lower();
        $safeBaseName = $safeBaseName !== '' ? $safeBaseName : 'tour-package-image';
        $filename = sprintf('%s-%s.%s', $safeBaseName, bin2hex(random_bytes(8)), strtolower($extension));
        $relativeDirectory = self::UPLOAD_ROOT . '/' . $packageId;
        $targetDirectory = $this->publicPath($relativeDirectory);

        $this->filesystem->mkdir($targetDirectory);
        $file->move($targetDirectory, $filename);

        return $relativeDirectory . '/' . $filename;
    }

    public function removeIfOwnedLocalFile(TourPackageImage $image): void
    {
        $absolutePath = $this->ownedLocalAbsolutePath($image);
        if ($absolutePath !== null && is_file($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }

    public function isOwnedLocalFile(TourPackageImage $image): bool
    {
        return $this->ownedLocalAbsolutePath($image) !== null;
    }

    private function ownedLocalAbsolutePath(TourPackageImage $image): ?string
    {
        $path = str_replace('\\', '/', ltrim($image->getPath(), '/'));
        $package = $image->getTourPackage();

        if ($package === null || $package->getId() === null) {
            return null;
        }

        $ownedPrefix = self::UPLOAD_ROOT . '/' . $package->getId() . '/';
        if (!str_starts_with($path, $ownedPrefix)) {
            return null;
        }

        $absolutePath = $this->publicPath($path);
        $uploadRoot = realpath($this->publicPath($ownedPrefix));
        $resolvedPath = realpath($absolutePath);

        if ($uploadRoot === false || $resolvedPath === false) {
            return null;
        }

        $uploadRoot = rtrim(str_replace('\\', '/', $uploadRoot), '/') . '/';
        $resolvedPath = str_replace('\\', '/', $resolvedPath);

        if (!str_starts_with($resolvedPath, $uploadRoot)) {
            return null;
        }

        return $resolvedPath;
    }

    private function publicPath(string $path): string
    {
        return $this->projectDir . '/public/' . str_replace('\\', '/', ltrim($path, '/'));
    }
}
