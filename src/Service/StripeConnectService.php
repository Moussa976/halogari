<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\Token;
use Stripe\Account;

class StripeConnectService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    }

    /**
     * Crée un compte Stripe Connect Custom si l'utilisateur n'en a pas encore.
     * @param User $user
     */
    public function creerCompteSiBesoin(User $user): void
    {
        if ($user->getStripeAccountId()) {
            return; // Déjà créé
        }

        // Création du token avec infos personnelles (obligatoire en France)
        $token = Token::create([
            'account' => [
                'business_type' => 'individual',
                'individual' => [
                    'first_name' => $user->getPrenom(),
                    'last_name' => $user->getNom(),
                    'email' => $user->getEmail(),
                    'dob' => [
                        'day' => (int) $user->getDateNaissance()->format('d'),
                        'month' => (int) $user->getDateNaissance()->format('m'),
                        'year' => (int) $user->getDateNaissance()->format('Y'),
                    ],
                    'address' => [
                        'line1' => '1 rue fictive',
                        'city' => 'Mamoudzou',
                        'postal_code' => '97600',
                        'country' => 'FR',
                    ],
                ],
                'tos_shown_and_accepted' => true,
            ]
        ]);

        // Création du compte Connect avec ce token
        $account = Account::create([
            'type' => 'custom',
            'country' => 'FR',
            'email' => $user->getEmail(),
            'account_token' => $token->id,
            'capabilities' => [
                'transfers' => ['requested' => true],
                'card_payments' => ['requested' => true],
            ],
        ]);

        // Enregistrement en base
        $user->setStripeAccountId($account->id);
        $this->em->flush();
    }

    // Vérification du statut account
    public function getStatutCompte(User $user): ?array
    {
        if (!$user->getStripeAccountId()) {
            return null;
        }

        try {
            $account = \Stripe\Account::retrieve($user->getStripeAccountId());

            return [
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'details_submitted' => $account->details_submitted,
                'email' => $account->email,
                'type' => $account->type,
                'verification_document' => $account->individual->verification->document->front ?? null,

            ];
        } catch (\Exception $e) {
            return null; // ou logger l'erreur
        }
    }

    /**
     * Crée un compte Stripe Connect Custom avec les données bancaires (IBAN)
     * @param User $user
     * @param array $donneesAdresse [line1, city, postal_code, country]
     * @param string $iban
     * @return string|null ID du compte Stripe ou null en cas d'échec
     */
    public function creerCompteAvecRIB(
        User $user,
        array $adresse,
        string $iban,
        string $titulaire,
        string $telephone,
        string $secteur,
        string $siteWeb
    ): void {
        if ($user->getStripeAccountId()) {
            throw new \RuntimeException("L'utilisateur a déjà un compte Stripe.");
        }

        // ✅ Formatage du téléphone au format E.164
        $telephone = preg_replace('/\s+/', '', $telephone); // Supprimer les espaces
        if (preg_match('/^0(639|692|693)/', $telephone)) {
            $telephone = '+262' . substr($telephone, 1); // Mayotte ou Réunion
        } elseif (preg_match('/^0\d+/', $telephone)) {
            $telephone = '+33' . substr($telephone, 1); // France métropolitaine
        }

        // 1. Créer le token de compte avec les données personnelles et adresse
        $accountToken = \Stripe\Token::create([
            'account' => [
                'business_type' => 'individual',
                'individual' => [
                    'first_name' => $user->getPrenom(),
                    'last_name' => $user->getNom(),
                    'email' => $user->getEmail(),
                    'phone' => $telephone,
                    'dob' => [
                        'day' => (int) $user->getDateNaissance()->format('d'),
                        'month' => (int) $user->getDateNaissance()->format('m'),
                        'year' => (int) $user->getDateNaissance()->format('Y'),
                    ],
                    'address' => [
                        'line1' => $adresse['line1'],
                        'city' => $adresse['city'],
                        'postal_code' => $adresse['postal_code'],
                        'country' => $adresse['country'] ?? 'FR',
                    ],
                ],
                'tos_shown_and_accepted' => true,
            ]
        ]);

        // 2. Créer le compte Stripe avec ce token
        $account = \Stripe\Account::create([
            'type' => 'custom',
            'country' => 'FR',
            'email' => $user->getEmail(),
            'account_token' => $accountToken->id,
            'capabilities' => [
                'transfers' => ['requested' => true],
                'card_payments' => ['requested' => true],
            ],
            'business_profile' => [
                'url' => $siteWeb,
                'mcc' => '4789', // Transport services (optionnel mais recommandé)
                'product_description' => $secteur,
            ],
        ]);

        // 3. Créer le token bancaire (RIB)
        $bankToken = \Stripe\Token::create([
            'bank_account' => [
                'country' => 'FR',
                'currency' => 'eur',
                'account_holder_name' => $titulaire,
                'account_holder_type' => 'individual',
                'account_number' => $iban,
            ],
        ]);

        // 4. Lier le RIB au compte
        \Stripe\Account::update($account->id, [
            'external_account' => $bankToken->id,
        ]);

        // 5. Sauvegarde
        $user->setStripeAccountId($account->id);
        $this->em->flush();
    }



    /*
     * Supprimer compte stripe
     */
    public function supprimerCompteStripe(User $user): void
    {
        if (!$user->getStripeAccountId()) {
            throw new \RuntimeException("Aucun compte Stripe associé à cet utilisateur.");
        }

        try {
            // 1. Récupère le compte existant
            $account = \Stripe\Account::retrieve($user->getStripeAccountId());

            // 2. Supprime via l'objet
            $account->delete();

            // 3. Nettoie la base
            $user->setStripeAccountId(null);
            $this->em->flush();
        } catch (\Exception $e) {
            throw new \RuntimeException("Erreur Stripe : " . $e->getMessage());
        }
    }

    public function ajouterPieceIdentite(User $user, string $cheminFichier): void
    {
        if (!$user->getStripeAccountId()) {
            throw new \RuntimeException("Ce compte n'a pas encore de compte Stripe.");
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        // 1. Upload du fichier d'identité
        $fichier = \Stripe\File::create([
            'purpose' => 'identity_document',
            'file' => fopen($cheminFichier, 'r'),
        ]);

        // 2. Création d’un token avec ce document
        $accountToken = \Stripe\Token::create([
            'account' => [
                'individual' => [
                    'verification' => [
                        'document' => [
                            'front' => $fichier->id,
                        ]
                    ]
                ]
            ]
        ]);

        // 3. Mise à jour du compte avec ce token
        \Stripe\Account::update($user->getStripeAccountId(), [
            'account_token' => $accountToken->id,
        ]);
    }





}
