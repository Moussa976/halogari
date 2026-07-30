<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeConnectService
{
    private EntityManagerInterface $em;
    private StripeConfigService $stripeConfig;

    public function __construct(EntityManagerInterface $em, StripeConfigService $stripeConfig)
    {
        $this->em = $em;
        $this->stripeConfig = $stripeConfig;
        Stripe::setApiKey($stripeConfig->secretKey());
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

    public function creerCompteAvecRIB(
        User $user,
        array $adresse,
        string $iban,
        string $titulaire,
        string $telephone,
        string $secteur,
        string $siteWeb,
        ?string $tosIp = null,
        ?string $tosUserAgent = null
    ): void {
        if ($user->getStripeAccountId()) {
            throw new \RuntimeException("L'utilisateur a déjà un compte Stripe.");
        }

        if (!$user->getDateNaissance()) {
            throw new \RuntimeException("La date de naissance est obligatoire pour créer le compte Stripe Connect.");
        }

        $telephone = $this->normalizePhone($telephone);

        $accountData = [
            'type' => 'custom',
            'country' => 'FR',
            'email' => $user->getEmail(),
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
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'business_profile' => [
                'url' => $siteWeb,
                'mcc' => '4789',
                'product_description' => $secteur,
            ],
        ];

        if ($tosIp) {
            $accountData['tos_acceptance'] = [
                'date' => time(),
                'ip' => $tosIp,
            ];

            if ($tosUserAgent) {
                $accountData['tos_acceptance']['user_agent'] = mb_substr($tosUserAgent, 0, 500);
            }
        }

        // En mode live, Stripe refuse les account_token créés côté serveur.
        $account = \Stripe\Account::create($accountData);

        $bankToken = \Stripe\Token::create([
            'bank_account' => [
                'country' => 'FR',
                'currency' => 'eur',
                'account_holder_name' => $titulaire,
                'account_holder_type' => 'individual',
                'account_number' => $iban,
            ],
        ]);

        \Stripe\Account::update($account->id, [
            'external_account' => $bankToken->id,
        ]);

        $user->setStripeAccountId($account->id);
        $this->em->flush();
    }

    public function supprimerCompteStripe(User $user): string
    {
        if (!$user->getStripeAccountId()) {
            throw new \RuntimeException('Aucun compte Stripe associé à cet utilisateur.');
        }

        $stripeAccountId = $user->getStripeAccountId();

        try {
            $account = \Stripe\Account::retrieve($stripeAccountId);
            $account->delete();

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

            throw new \RuntimeException('Erreur Stripe : ' . $message);
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur Stripe : ' . $e->getMessage());
        }
    }

    public function ajouterPieceIdentite(User $user, string $cheminFichier): void
    {
        if (!$user->getStripeAccountId()) {
            throw new \RuntimeException("Ce compte n'a pas encore de compte Stripe.");
        }

        Stripe::setApiKey($this->stripeConfig->secretKey());

        $fichier = \Stripe\File::create([
            'purpose' => 'identity_document',
            'file' => fopen($cheminFichier, 'r'),
        ]);

        // En mode live, Stripe refuse les account_token créés côté serveur.
        \Stripe\Account::update($user->getStripeAccountId(), [
            'individual' => [
                'verification' => [
                    'document' => [
                        'front' => $fichier->id,
                    ],
                ],
            ],
        ]);
    }

    private function normalizePhone(string $telephone): string
    {
        $telephone = preg_replace('/\s+/', '', $telephone);

        if (preg_match('/^0(639|692|693)/', $telephone)) {
            return '+262' . substr($telephone, 1);
        }

        if (preg_match('/^0\d+/', $telephone)) {
            return '+33' . substr($telephone, 1);
        }

        return $telephone;
    }
}
