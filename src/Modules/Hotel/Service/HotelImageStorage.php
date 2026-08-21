<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelImage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class HotelImageStorage
{
    private const UPLOAD_ROOT = 'uploads/hotels';
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

    public function store(Hotel $hotel, UploadedFile $file): string
    {
        $hotelId = $hotel->getId();
        if ($hotelId === null) {
            throw new \LogicException('Hotel must be persisted before image upload.');
        }

        $mimeType = $file->getMimeType();
        if (!\is_string($mimeType) || !isset(self::ALLOWED_MIME_EXTENSIONS[$mimeType])) {
            throw new \InvalidArgumentException('Unsupported hotel image MIME type.');
        }

        $extension = self::ALLOWED_MIME_EXTENSIONS[$mimeType];
        $baseName = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        $safeBaseName = (string) $this->slugger->slug($baseName)->lower();
        $safeBaseName = $safeBaseName !== '' ? $safeBaseName : 'hotel-image';
        $filename = sprintf('%s-%s.%s', $safeBaseName, bin2hex(random_bytes(8)), strtolower($extension));
        $relativeDirectory = self::UPLOAD_ROOT . '/' . $hotelId;
        $targetDirectory = $this->publicPath($relativeDirectory);

        $this->filesystem->mkdir($targetDirectory);
        $file->move($targetDirectory, $filename);

        return $relativeDirectory . '/' . $filename;
    }

    public function removeIfOwnedLocalFile(HotelImage $image): void
    {
        $absolutePath = $this->ownedLocalAbsolutePath($image);
        if ($absolutePath !== null && is_file($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }

    public function isOwnedLocalFile(HotelImage $image): bool
    {
        return $this->ownedLocalAbsolutePath($image) !== null;
    }

    private function ownedLocalAbsolutePath(HotelImage $image): ?string
    {
        $path = str_replace('\\', '/', ltrim($image->getPath(), '/'));
        $hotel = $image->getHotel();

        if ($hotel === null || $hotel->getId() === null) {
            return null;
        }

        $ownedPrefix = self::UPLOAD_ROOT . '/' . $hotel->getId() . '/';
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
