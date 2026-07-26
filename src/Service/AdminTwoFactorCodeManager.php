<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;

class AdminTwoFactorCodeManager
{
    private const SESSION_USER_ID = 'admin_2fa_user_id';
    private const SESSION_CODE_HASH = 'admin_2fa_code_hash';
    private const SESSION_EXPIRES_AT = 'admin_2fa_expires_at';
    private const SESSION_VERIFIED_USER_ID = 'admin_2fa_verified_user_id';
    private const SESSION_TARGET_PATH = 'admin_2fa_target_path';
    private const CODE_TTL_SECONDS = 600;

    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function start(Request $request, User $user, ?string $targetPath = null): \DateTimeImmutable
    {
        $code = (string) random_int(100000, 999999);
        $expiresAt = new \DateTimeImmutable('+' . self::CODE_TTL_SECONDS . ' seconds');
        $session = $request->getSession();

        $session->set(self::SESSION_USER_ID, $user->getId());
        $session->set(self::SESSION_CODE_HASH, hash('sha256', $code));
        $session->set(self::SESSION_EXPIRES_AT, $expiresAt->getTimestamp());
        $session->remove(self::SESSION_VERIFIED_USER_ID);

        if ($targetPath) {
            $session->set(self::SESSION_TARGET_PATH, $targetPath);
        }

        $this->sendCode($user, $code, $expiresAt);

        return $expiresAt;
    }

    public function verify(Request $request, User $user, string $code): bool
    {
        $session = $request->getSession();
        $code = preg_replace('/\D+/', '', $code) ?? '';

        if (!$this->isPendingFor($request, $user) || $this->isExpired($request) || strlen($code) !== 6) {
            return false;
        }

        $expectedHash = (string) $session->get(self::SESSION_CODE_HASH, '');
        if (!hash_equals($expectedHash, hash('sha256', $code))) {
            return false;
        }

        $session->set(self::SESSION_VERIFIED_USER_ID, $user->getId());
        $session->remove(self::SESSION_CODE_HASH);
        $session->remove(self::SESSION_EXPIRES_AT);
        $session->remove(self::SESSION_USER_ID);

        return true;
    }

    public function isVerified(Request $request, User $user): bool
    {
        return (int) $request->getSession()->get(self::SESSION_VERIFIED_USER_ID, 0) === $user->getId();
    }

    public function isPendingFor(Request $request, User $user): bool
    {
        return (int) $request->getSession()->get(self::SESSION_USER_ID, 0) === $user->getId()
            && (string) $request->getSession()->get(self::SESSION_CODE_HASH, '') !== '';
    }

    public function isExpired(Request $request): bool
    {
        $expiresAt = (int) $request->getSession()->get(self::SESSION_EXPIRES_AT, 0);

        return $expiresAt <= time();
    }

    public function getExpiresAt(Request $request): ?\DateTimeImmutable
    {
        $expiresAt = (int) $request->getSession()->get(self::SESSION_EXPIRES_AT, 0);

        return $expiresAt > 0 ? (new \DateTimeImmutable())->setTimestamp($expiresAt) : null;
    }

    public function getTargetPath(Request $request): string
    {
        $targetPath = (string) $request->getSession()->get(self::SESSION_TARGET_PATH, '/admin');
        $request->getSession()->remove(self::SESSION_TARGET_PATH);

        if ($targetPath === '' || !str_starts_with($targetPath, '/') || str_starts_with($targetPath, '//')) {
            return '/admin';
        }

        if (str_starts_with($targetPath, '/connexion') || str_starts_with($targetPath, '/logout') || str_starts_with($targetPath, '/admin/verification')) {
            return '/admin';
        }

        return $targetPath;
    }

    private function sendCode(User $user, string $code, \DateTimeImmutable $expiresAt): void
    {
        $email = (new TemplatedEmail())
            ->from(MailAddressProvider::adminSender())
            ->to((string) $user->getEmail())
            ->subject('Code de connexion admin HaloGari')
            ->htmlTemplate('emails/admin_two_factor_code.html.twig')
            ->context([
                'user' => $user,
                'code' => $code,
                'expiresAt' => $expiresAt,
            ]);

        $this->mailer->send($email);
    }
}
