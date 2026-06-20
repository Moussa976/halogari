<?php

namespace App\Command;

use App\Repository\AdminAuditLogRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class SendAdminAuditDigestCommand extends Command
{
    protected static $defaultName = 'halogari:admin-audit:digest';

    private AdminAuditLogRepository $auditLogRepository;
    private AdminAuditLogger $auditLogger;
    private MailerInterface $mailer;
    private EntityManagerInterface $em;
    private string $projectDir;

    public function __construct(
        AdminAuditLogRepository $auditLogRepository,
        AdminAuditLogger $auditLogger,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        string $projectDir
    ) {
        parent::__construct();
        $this->auditLogRepository = $auditLogRepository;
        $this->auditLogger = $auditLogger;
        $this->mailer = $mailer;
        $this->em = $em;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Envoie un résumé hebdomadaire des actions admin non envoyées.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte les actions sans envoyer le résumé.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $until = new \DateTimeImmutable();
        $since = $until->modify('-7 days');
        $logs = $this->auditLogRepository->findPendingDigest($since, $until);

        if (!$logs) {
            $output->writeln('Aucune action admin à envoyer.');
            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $output->writeln(sprintf('%d action(s) seraient envoyée(s) dans le résumé admin.', count($logs)));
            return Command::SUCCESS;
        }

        $summary = [];
        $rows = [];
        foreach ($logs as $log) {
            $label = $this->auditLogger->humanizeAction((string) $log->getAction());
            $summary[$label] = ($summary[$label] ?? 0) + 1;
            $rows[] = [
                'log' => $log,
                'label' => $label,
                'actor' => $log->getActor() ? trim($log->getActor()->getPrenom() . ' ' . $log->getActor()->getNom()) : 'Admin inconnu',
                'target' => $log->getTargetUser() ? trim($log->getTargetUser()->getPrenom() . ' ' . $log->getTargetUser()->getNom()) : '-',
            ];
        }

        $email = (new TemplatedEmail())
            ->from(new Address('moussa@halogari.yt', 'HaloGari Admin'))
            ->to('moussa@halogari.yt')
            ->subject('[HaloGari Admin] Résumé hebdomadaire')
            ->htmlTemplate('emails/admin_audit_digest.html.twig')
            ->context([
                'rows' => $rows,
                'summary' => $summary,
                'since' => $since,
                'until' => $until,
            ])
            ->embedFromPath($this->projectDir . '/public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $sentAt = new \DateTimeImmutable();
        foreach ($logs as $log) {
            $log->setDigestSentAt($sentAt);
        }

        $this->em->flush();

        $output->writeln(sprintf('%d action(s) envoyée(s) dans le résumé admin.', count($logs)));

        return Command::SUCCESS;
    }
}
