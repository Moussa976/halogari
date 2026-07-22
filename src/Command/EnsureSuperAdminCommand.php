<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EnsureSuperAdminCommand extends Command
{
    protected static $defaultName = 'app:ensure-super-admin';

    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private UserRepository $users;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $users
    ) {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->users = $users;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create or update a HaloGari superadmin account.')
            ->addArgument('email', InputArgument::REQUIRED, 'Superadmin e-mail address')
            ->addArgument('password', InputArgument::REQUIRED, 'Temporary password')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'First name', 'HaloGari')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Last name', 'Admin')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Phone number', '+262639000000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = strtolower(trim((string) $input->getArgument('email')));
        $plainPassword = (string) $input->getArgument('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Invalid e-mail address.</error>');

            return Command::FAILURE;
        }

        if (strlen($plainPassword) < 12) {
            $output->writeln('<error>The password must contain at least 12 characters.</error>');

            return Command::FAILURE;
        }

        $user = $this->users->findOneBy(['email' => $email]);
        $created = false;

        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $created = true;
        }

        $user
            ->setPrenom(trim((string) $input->getOption('first-name')) ?: 'HaloGari')
            ->setNom(trim((string) $input->getOption('last-name')) ?: 'Admin')
            ->setTelephone(trim((string) $input->getOption('phone')) ?: '+262639000000')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setPassword($this->passwordHasher->hashPassword($user, $plainPassword))
            ->setIsVerified(true)
            ->setConducteurVerifie(false);

        if ($created) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        $output->writeln(sprintf(
            '<info>%s superadmin account: %s</info>',
            $created ? 'Created' : 'Updated',
            $email
        ));

        return Command::SUCCESS;
    }
}
