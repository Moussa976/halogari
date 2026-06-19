<?php

namespace App\Service;

use App\Entity\Trajet;
use Carbon\Carbon;
use Intervention\Image\ImageManager;

class AfficheService
{
    private string $outputDir;
    private ImageManager $manager;
    private string $fontPath;
    private string $logoPath;
    private string $silhouettePath;

    public function __construct(string $projectDir)
    {
        $this->outputDir = $projectDir . '/public/uploads/affiches';
        $this->logoPath = $projectDir . '/public/images/logo.png';
        $this->silhouettePath = $projectDir . '/public/images/mayotte_silhouette_orange.png';
        $this->fontPath = $projectDir . '/public/fonts/OpenSans-Bold.ttf';
        $this->manager = new ImageManager(['driver' => 'gd']);

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0775, true);
        }
    }

    public function generate(Trajet $trajet): string
    {
        $image = $this->manager->canvas(1080, 1350, '#edf6ef');
        $this->drawNaturalPattern($image);
        $this->insertMayotteSilhouette($image);

        Carbon::setLocale('fr');
        $dateTrajet = Carbon::parse($trajet->getDateTrajet())->translatedFormat('d F Y');
        $heure = $trajet->getHeureTrajet()->format('H:i');
        $places = (int) $trajet->getPlacesDisponibles();
        $prix = number_format((float) $trajet->getPrix(), 2, ',', ' ');

        $this->writeCentered($image, 'Nouveau trajet disponible', 540, 74, 42, '#f26522');
        $this->drawRoutePanel($image, (string) $trajet->getDepart(), (string) $trajet->getArrivee());

        $this->drawInfoCard($image, 54, 745, 'date', 'DATE', $dateTrajet, $heure);
        $this->drawInfoCard($image, 390, 745, 'people', 'PLACES', sprintf('%d %s', $places, $places > 1 ? 'places' : 'place'), 'disponible' . ($places > 1 ? 's' : ''));
        $this->drawInfoCard($image, 726, 745, 'price', 'PRIX', $prix . ' €', 'par place');

        $this->insertLogo($image);
        $this->writeCentered($image, 'Réservez sur halogari.yt', 540, 1280, 30, '#245c36');

        $fileName = 'trajet_' . ($trajet->getId() ?: 'preview') . '_' . uniqid() . '.jpg';
        $path = $this->outputDir . '/' . $fileName;
        $image->save($path, 88);

        return '/uploads/affiches/' . $fileName;
    }

    private function insertMayotteSilhouette($image): void
    {
        if (!is_file($this->silhouettePath)) {
            return;
        }

        $silhouette = $this->manager->make($this->silhouettePath)
            ->resize(980, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

        $this->removeWhiteBackground($silhouette);
        $silhouette->opacity(30);

        $image->insert($silhouette, 'top-left', 150, 330);

        $core = $image->getCore();
        $orange = $this->allocateColor($core, '#f26522', 24);
        $green = $this->allocateColor($core, '#18b94d', 18);

        $this->drawPolyline($core, [[255, 700], [390, 630], [520, 555], [670, 470], [835, 380]], $orange, 13);
        imagefilledellipse($core, 255, 700, 28, 28, $orange);
        imagefilledellipse($core, 835, 380, 28, 28, $green);
        imagesetthickness($core, 1);
    }

    private function removeWhiteBackground($image): void
    {
        $core = $image->getCore();
        imagealphablending($core, false);
        imagesavealpha($core, true);

        $transparent = imagecolorallocatealpha($core, 255, 255, 255, 127);
        $width = imagesx($core);
        $height = imagesy($core);

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgba = imagecolorat($core, $x, $y);
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                if ($red > 238 && $green > 238 && $blue > 238) {
                    imagesetpixel($core, $x, $y, $transparent);
                }
            }
        }

        imagealphablending($core, true);
    }

    private function drawNaturalPattern($image): void
    {
        $core = $image->getCore();
        $green = $this->allocateColor($core, '#245c36', 104);
        $orange = $this->allocateColor($core, '#f58220', 110);

        for ($i = 0; $i < 7; ++$i) {
            $x = 40 + ($i * 155);
            imagefilledellipse($core, $x, 116 + ($i % 2) * 26, 96, 26, $green);
            imagefilledellipse($core, $x + 42, 98 + ($i % 3) * 18, 70, 20, $orange);
        }

        for ($i = 0; $i < 5; ++$i) {
            $x = 90 + ($i * 220);
            imagefilledellipse($core, $x, 1110 + ($i % 2) * 34, 120, 32, $green);
            imagefilledellipse($core, $x + 54, 1088 + ($i % 3) * 22, 78, 22, $orange);
        }
    }

    private function drawRoutePanel($image, string $depart, string $arrivee): void
    {
        $this->drawRoundedPanel($image, 58, 132, 964, 236, 34, '#ffffff', '#dfe9dd', 2);

        $this->writeCentered($image, 'DÉPART', 300, 194, 22, '#f26522');
        $this->writeCentered($image, 'ARRIVÉE', 780, 194, 22, '#f26522');
        $this->drawPinIcon($image, 130, 266, '#f26522');
        $this->drawPinIcon($image, 950, 266, '#18b94d');
        $this->drawArrow($image, 470, 266, 610, 266);

        $this->writeBoxText($image, $depart, 155, 228, 290, 78, 42, '#245c36');
        $this->writeBoxText($image, $arrivee, 635, 228, 290, 78, 42, '#245c36');
    }

    private function drawInfoCard($image, int $x, int $y, string $icon, string $label, string $main, string $sub): void
    {
        $this->drawRoundedPanel($image, $x, $y, 300, 230, 28, '#ffffff', '#dfe9dd', 2);
        $center = $x + 150;

        $this->drawCardIcon($image, $icon, $center, $y + 54);
        $this->writeCentered($image, $label, $center, $y + 104, 20, '#245c36');
        $this->writeCentered($image, $main, $center, $y + 154, 32, '#f26522');
        $this->writeCentered($image, $sub, $center, $y + 195, 24, '#245c36');
    }

    private function drawCardIcon($image, string $icon, int $x, int $y): void
    {
        $core = $image->getCore();
        $green = $this->allocateColor($core, '#18b94d');
        $orange = $this->allocateColor($core, '#f26522');
        $white = $this->allocateColor($core, '#ffffff');

        imagefilledellipse($core, $x, $y, 70, 70, $green);

        if ($icon === 'date') {
            imagefilledrectangle($core, $x - 20, $y - 14, $x + 20, $y + 20, $white);
            imagefilledrectangle($core, $x - 20, $y - 14, $x + 20, $y - 4, $orange);
            imagesetthickness($core, 3);
            imageline($core, $x - 10, $y - 21, $x - 10, $y - 8, $white);
            imageline($core, $x + 10, $y - 21, $x + 10, $y - 8, $white);
            imagesetthickness($core, 1);
            return;
        }

        if ($icon === 'people') {
            imagefilledellipse($core, $x, $y - 8, 24, 24, $white);
            imagefilledellipse($core, $x - 21, $y + 2, 20, 20, $white);
            imagefilledellipse($core, $x + 21, $y + 2, 20, 20, $white);
            imagefilledellipse($core, $x, $y + 22, 46, 32, $white);
            imagefilledellipse($core, $x - 24, $y + 24, 32, 24, $white);
            imagefilledellipse($core, $x + 24, $y + 24, 32, 24, $white);
            return;
        }

        $this->writeCentered($image, '€', $x, $y + 18, 58, '#ffffff');
    }

    private function drawPinIcon($image, int $x, int $y, string $color): void
    {
        $core = $image->getCore();
        $fill = $this->allocateColor($core, $color);
        $white = $this->allocateColor($core, '#ffffff');

        imagefilledellipse($core, $x, $y - 18, 46, 46, $fill);
        imagefilledpolygon($core, [$x - 15, $y - 2, $x + 15, $y - 2, $x, $y + 34], $fill);
        imagefilledellipse($core, $x, $y - 18, 16, 16, $white);
    }

    private function drawArrow($image, int $x1, int $y1, int $x2, int $y2): void
    {
        $core = $image->getCore();
        $orange = $this->allocateColor($core, '#f26522');

        imagesetthickness($core, 6);
        imageline($core, $x1, $y1, $x2, $y2, $orange);
        imagefilledpolygon($core, [$x2, $y2, $x2 - 24, $y2 - 16, $x2 - 24, $y2 + 16], $orange);
        imagesetthickness($core, 1);
    }

    private function insertLogo($image): void
    {
        if (!is_file($this->logoPath)) {
            return;
        }

        $logo = $this->manager->make($this->logoPath)->resize(180, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $image->insert($logo, 'top-left', 450, 1108);
    }

    private function drawRoundedPanel($image, int $x, int $y, int $width, int $height, int $radius, string $background, string $border, int $borderWidth): void
    {
        $core = $image->getCore();
        $shadow = $this->allocateColor($core, '#245c36', 102);
        $borderColor = $this->allocateColor($core, $border);
        $backgroundColor = $this->allocateColor($core, $background);

        $this->filledRoundedRectangle($core, $x + 8, $y + 10, $x + $width + 8, $y + $height + 10, $radius, $shadow);
        $this->filledRoundedRectangle($core, $x, $y, $x + $width, $y + $height, $radius, $borderColor);
        $this->filledRoundedRectangle($core, $x + $borderWidth, $y + $borderWidth, $x + $width - $borderWidth, $y + $height - $borderWidth, max(1, $radius - $borderWidth), $backgroundColor);
    }

    private function filledRoundedRectangle($core, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($core, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($core, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($core, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($core, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($core, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($core, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    /**
     * @param array<int, array{0:int, 1:int}> $points
     */
    private function drawPolyline($core, array $points, int $color, int $thickness): void
    {
        imagesetthickness($core, $thickness);

        for ($i = 1, $count = count($points); $i < $count; ++$i) {
            imageline($core, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $color);
        }

        imagesetthickness($core, 1);
    }

    private function writeBoxText($image, string $text, int $x, int $y, int $width, int $height, int $maxSize, string $color): void
    {
        $text = trim($text);
        $size = $maxSize;
        $lines = [];

        while ($size >= 16) {
            $lines = $this->wrapText($text, $size, $width);
            if (count($lines) <= 2 && count($lines) * ($size + 8) <= $height) {
                break;
            }

            --$size;
        }

        $lineHeight = $size + 8;
        $startY = $y + (int) (($height - count($lines) * $lineHeight) / 2) + $size;

        foreach ($lines as $index => $line) {
            $this->writeCentered($image, $line, $x + (int) ($width / 2), $startY + $index * $lineHeight, $size, $color);
        }
    }

    private function writeCentered($image, string $text, int $x, int $y, int $size, string $color): void
    {
        $image->text($text, $x, $y, function ($font) use ($size, $color) {
            $font->file($this->fontPath);
            $font->size($size);
            $font->color($color);
            $font->align('center');
        });
    }

    /**
     * @return string[]
     */
    private function wrapText(string $text, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line . ' ' . $word);
            if ($line !== '' && $this->textWidth($candidate, $fontSize) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
                continue;
            }

            $line = $candidate;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [$text];
    }

    private function textWidth(string $text, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $this->fontPath, $text);

        if (!$box) {
            return 0;
        }

        return abs($box[2] - $box[0]);
    }

    private function allocateColor($core, string $hex, int $alpha = 0): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocatealpha(
            $core,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            $alpha
        );
    }
}
