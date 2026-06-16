<?php

namespace App\Service;

use App\Entity\Paiement;
use App\Entity\PaiementEvenement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PaiementEventLogger
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function log(
        Paiement $paiement,
        string $type,
        string $titre,
        ?string $message = null,
        ?User $acteur = null,
        array $metadata = []
    ): PaiementEvenement {
        $event = new PaiementEvenement();
        $event->setPaiement($paiement);
        $event->setType($type);
        $event->setTitre($titre);
        $event->setMessage($message);
        $event->setActeur($acteur);
        $event->setMetadata($metadata);

        $this->em->persist($event);

        return $event;
    }
}
