<?php

namespace App\Service;

use App\Entity\Trajet;
use Intervention\Image\ImageManager;
use Carbon\Carbon;

class AfficheService
{
    private string $outputDir;
    private ImageManager $manager;

    public function __construct(string $projectDir)
    {
        $this->outputDir = $projectDir . '/public/uploads/affiches';
        $this->manager = new ImageManager(['driver' => 'gd']);

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0775, true);
        }
    }

    public function generate(Trajet $trajet): string
    {
        $image = $this->manager->make(__DIR__ . '/../../public/images/modele_affiche.png');

        $date = $trajet->getDateTrajet();

         Carbon::setLocale('fr');
        $dateFr = Carbon::parse($date);
        $dateTrajet = $dateFr->translatedFormat('d F Y');


        $heure = $trajet->getHeureTrajet()->format('H:i');
        $places = $trajet->getPlacesDisponibles();
        $prix = number_format($trajet->getPrix(), 2, ',', ' ');

        // Texte à insérer
        $nbPlaces = "place";
        if ($places > 1) {
            $nbPlaces = "places";
        }
        $textDate = "$dateTrajet";
        $textHeure = "à $heure";
        $textPlaces = "$places $nbPlaces";
        $textPrix = "$prix €/place";

        // Style commun
        $fontPath = __DIR__ . '/../../public/fonts/OpenSans-Bold.ttf';

        // Écriture des textes (coordonnées à ajuster selon ton image)
        $image->text("Nouveau trajet disponible !", 520, 60, function ($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(34);
            $font->color('#f26522');
            $font->align('center');
        });
        
        $image->text("halogary.yt", 520, 964, function ($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(18);
            $font->color('#1a7508');
            $font->align('center');
        });

        $image->text($trajet->getDepart(), 165, 415, function ($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(30);
            $font->color('#333333');
            $font->align('center');
        });

        $image->text($trajet->getArrivee(), 860, 225, function ($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(24);
            $font->color('#333333');
            $font->align('center');
        });

        $image->text($textDate, 170, 750, function ($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(32);
            $font->color('#ffffff');
            $font->align('center');
        });

        $image->text($textHeure, 170, 780, function ($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(32);
            $font->color('#ffffff');
            $font->align('center');
        });

        $image->text($textPlaces, 510, 750, function ($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(32);
            $font->color('#ffffff');
            $font->align('center');
        });

        $image->text($textPrix, 840, 750, function ($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(32);
            $font->color('#ffffff');
            $font->align('center');
        });

        $fileName = 'trajet_' . uniqid() . '.png';
        $path = $this->outputDir . '/' . $fileName;
        // dd($path); 
        $image->save($path);

        return '/uploads/affiches/' . $fileName;
    }


}
