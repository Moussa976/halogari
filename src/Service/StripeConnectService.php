<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeConnectService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    }

    /**
     * Le compte Connect doit être créé depuis l'admin, avec l'IBAN du conducteur.
     */
    public function creerCompteSiBesoin(User $user): void
    {
        if (!$user->getStripeAccountId()) {
            throw new \RuntimeException("Le compte Stripe Connect doit être créé depuis l'administration avec l'IBAN du conducteur.");
        }
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
        } catch (ApiErrorException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Only Stripe Connect platforms')) {
                $message = 'Stripe Connect n’est pas activé ou pas configuré sur le compte Stripe utilisé par HaloGari.';
            } elseif (str_contains($message, 'No such account')) {
                $message = 'Stripe ne retrouve pas ce compte Connect avec la clé actuelle.';
            }

            return [
                'invalid' => true,
                'error' => $message,
            ];
        } catch (\Exception $e) {
            return [
                'invalid' => true,
                'error' => 'Impossible de vérifier le compte Stripe Connect actuellement.',
            ];
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

        // Formatage du téléphone au format E.164
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
    public function supprimerCompteStripe(User $user): string
    {
        if (!$user->getStripeAccountId()) {
            throw new \RuntimeException("Aucun compte Stripe associé à cet utilisateur.");
        }

        $stripeAccountId = $user->getStripeAccountId();

        try {
            // 1. Récupère le compte existant
            $account = \Stripe\Account::retrieve($stripeAccountId);

            // 2. Supprime via l'objet
            $account->delete();

            // 3. Nettoie la base
            $user->setStripeAccountId(null);
            $this->em->flush();
            return 'Compte Stripe supprimé avec succès.';
        } catch (ApiErrorException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Only Stripe Connect platforms') || str_contains($message, 'No such account')) {
                $user->setStripeAccountId(null);
                $this->em->flush();

                return sprintf('Compte Stripe délié de HaloGari. L’ancien identifiant %s n’est pas utilisable avec la clé Stripe actuelle.', $stripeAccountId);
            }

            throw new \RuntimeException("Erreur Stripe : " . $message);
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
