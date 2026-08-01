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

    public function creerCompteSiBesoin(User $user): void
    {
        if (!$user->getStripeAccountId()) {
            throw new \RuntimeException('Le conducteur doit activer ses versements via Stripe Express avant de recevoir sa part.');
        }
    }

    public function creerCompteExpress(User $user): string
    {
        if ($user->getStripeAccountId()) {
            return $user->getStripeAccountId();
        }

        $account = \Stripe\Account::create([
            'type' => 'express',
            'country' => 'FR',
            'email' => $user->getEmail(),
            'business_type' => 'individual',
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'business_profile' => [
                'url' => 'https://halogari.yt',
                'mcc' => '4789',
                'product_description' => 'Covoiturage local entre particuliers à Mayotte',
            ],
            'metadata' => [
                'platform' => 'halogari',
                'user_id' => (string) $user->getId(),
            ],
        ]);

        $user->setStripeAccountId($account->id);
        $this->em->flush();

        return $account->id;
    }

    public function creerLienOnboardingExpress(User $user, string $refreshUrl, string $returnUrl): string
    {
        $accountId = $this->creerCompteExpress($user);
        $accountLink = \Stripe\AccountLink::create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return $accountLink->url;
    }

    public function creerLienDashboardExpress(User $user): string
    {
        if (!$user->getStripeAccountId()) {
            throw new \RuntimeException('Aucun compte Stripe Express associé à cet utilisateur.');
        }

        $loginLink = \Stripe\Account::createLoginLink($user->getStripeAccountId());

        return $loginLink->url;
    }

    public function getStatutCompte(User $user): ?array
    {
        if (!$user->getStripeAccountId()) {
            return null;
        }

        try {
            $account = \Stripe\Account::retrieve($user->getStripeAccountId());

            return [
                'charges_enabled' => (bool) $account->charges_enabled,
                'payouts_enabled' => (bool) $account->payouts_enabled,
                'details_submitted' => (bool) $account->details_submitted,
                'email' => $account->email,
                'type' => $account->type,
                'requirements_due' => $account->requirements->currently_due ?? [],
                'requirements_past_due' => $account->requirements->past_due ?? [],
            ];
        } catch (ApiErrorException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Only Stripe Connect platforms')) {
                $message = 'Stripe Connect n’est pas activé ou pas configuré sur le compte Stripe utilisé par HaloGari.';
            } elseif (str_contains($message, 'No such account')) {
                $message = 'Stripe ne retrouve pas ce compte Express avec la clé actuelle.';
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
        throw new \RuntimeException('L’ancien flux manuel dans HaloGari est désactivé. Utilisez Stripe Express pour collecter les coordonnées bancaires du conducteur.');
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
        throw new \RuntimeException('Avec Stripe Express, les pièces demandées par Stripe sont ajoutées directement dans le parcours sécurisé Stripe.');
    }
}
