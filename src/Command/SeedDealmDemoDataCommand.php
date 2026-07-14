<?php

namespace App\Command;

use App\Entity\Trajet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SeedDealmDemoDataCommand extends Command
{
    protected static $defaultName = 'app:seed-dealm-demo-data';

    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private string $environment;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, string $environment)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->environment = $environment;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Crée des utilisateurs et trajets de démonstration pour les captures DEALM.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Autorise l’exécution en environnement prod/préprod.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->environment === 'prod' && !$input->getOption('force')) {
            $output->writeln('<error>Ajoutez --force pour exécuter cette commande en prod/préprod.</error>');

            return Command::FAILURE;
        }

        $drivers = [
            [
                'email' => 'dealm.moussa@halogari.test',
                'prenom' => 'Moussa',
                'nom' => 'TATA',
                'telephone' => '+262639000001',
                'vehicleBrand' => 'Renault',
                'vehicleModel' => 'Clio',
                'vehicleColor' => 'blanche',
                'vehicleSeats' => 4,
            ],
            [
                'email' => 'dealm.amina@halogari.test',
                'prenom' => 'Amina',
                'nom' => 'MADI',
                'telephone' => '+262639000002',
                'vehicleBrand' => 'Peugeot',
                'vehicleModel' => '208',
                'vehicleColor' => 'grise',
                'vehicleSeats' => 4,
            ],
            [
                'email' => 'dealm.yanis@halogari.test',
                'prenom' => 'Yanis',
                'nom' => 'ABDOU',
                'telephone' => '+262639000003',
                'vehicleBrand' => 'Dacia',
                'vehicleModel' => 'Sandero',
                'vehicleColor' => 'bleue',
                'vehicleSeats' => 4,
            ],
        ];

        $users = [];
        foreach ($drivers as $driver) {
            $users[$driver['email']] = $this->upsertDriver($driver);
        }

        $trips = [
            ['driver' => 'dealm.moussa@halogari.test', 'depart' => 'Hamjago', 'arrivee' => 'Dzoumogné', 'date' => '2026-07-20', 'time' => '07:20', 'places' => 2, 'price' => '3.00'],
            ['driver' => 'dealm.amina@halogari.test', 'depart' => 'Hamjago', 'arrivee' => 'Dzoumogné', 'date' => '2026-07-20', 'time' => '12:10', 'places' => 3, 'price' => '3.00'],
            ['driver' => 'dealm.yanis@halogari.test', 'depart' => 'Hamjago', 'arrivee' => 'Dzoumogné', 'date' => '2026-07-20', 'time' => '16:45', 'places' => 1, 'price' => '3.00'],
            ['driver' => 'dealm.moussa@halogari.test', 'depart' => 'M\'Tsamboro', 'arrivee' => 'Mamoudzou', 'date' => '2026-07-20', 'time' => '05:30', 'places' => 4, 'price' => '6.00'],
            ['driver' => 'dealm.amina@halogari.test', 'depart' => 'Koungou', 'arrivee' => 'Mamoudzou', 'date' => '2026-07-20', 'time' => '08:00', 'places' => 2, 'price' => '2.00'],
            ['driver' => 'dealm.yanis@halogari.test', 'depart' => 'Chirongui', 'arrivee' => 'Sada', 'date' => '2026-07-21', 'time' => '09:15', 'places' => 2, 'price' => '4.00'],
        ];

        $created = 0;
        $updated = 0;
        foreach ($trips as $trip) {
            $result = $this->upsertTrip($users[$trip['driver']], $trip);
            $created += $result === 'created' ? 1 : 0;
            $updated += $result === 'updated' ? 1 : 0;
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('<info>Données DEALM prêtes : %d trajet(s) créé(s), %d trajet(s) mis à jour.</info>', $created, $updated));
        $output->writeln('Recherche à capturer : /chercher/Hamjago/Dzoumogné/2026-07-20/any/1');

        return Command::SUCCESS;
    }

    private function upsertDriver(array $data): User
    {
        $repository = $this->entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $repository->findOneBy(['email' => $data['email']]);

        if (!$user) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'HaloGari-demo-2026!'));
            $this->entityManager->persist($user);
        }

        $user
            ->setPrenom($data['prenom'])
            ->setNom($data['nom'])
            ->setTelephone($data['telephone'])
            ->setRoles(['ROLE_USER'])
            ->setDateNaissance(new \DateTimeImmutable('1990-01-01'))
            ->setConducteurVerifie(true)
            ->setIsVerified(true)
            ->setPostalAddressLine1('Rue de la République')
            ->setPostalCode('97600')
            ->setPostalCity('Mamoudzou')
            ->setPostalCountry('Mayotte')
            ->setVehicleBrand($data['vehicleBrand'])
            ->setVehicleModel($data['vehicleModel'])
            ->setVehicleColor($data['vehicleColor'])
            ->setVehicleSeats($data['vehicleSeats'])
            ->setDescription('Conducteur de démonstration créé pour les captures de présentation HaloGari.');

        return $user;
    }

    private function upsertTrip(User $driver, array $data): string
    {
        $date = new \DateTimeImmutable($data['date']);
        $time = new \DateTimeImmutable($data['time']);

        /** @var Trajet|null $trip */
        $trip = $this->entityManager->getRepository(Trajet::class)
            ->createQueryBuilder('t')
            ->andWhere('t.conducteur = :driver')
            ->andWhere('t.depart = :depart')
            ->andWhere('t.arrivee = :arrivee')
            ->andWhere('t.dateTrajet = :date')
            ->andWhere('t.heureTrajet = :time')
            ->setParameter('driver', $driver)
            ->setParameter('depart', $data['depart'])
            ->setParameter('arrivee', $data['arrivee'])
            ->setParameter('date', $date)
            ->setParameter('time', $time)
            ->getQuery()
            ->getOneOrNullResult();

        $status = $trip ? 'updated' : 'created';
        if (!$trip) {
            $trip = new Trajet();
            $trip->setConducteur($driver);
            $this->entityManager->persist($trip);
        }

        $trip
            ->setDepart($data['depart'])
            ->setArrivee($data['arrivee'])
            ->setDateTrajet($date)
            ->setHeureTrajet($time)
            ->setPlaces($data['places'])
            ->setPlacesDisponibles($data['places'])
            ->setPrix($data['price'])
            ->setAnnule(false)
            ->setDescription('Trajet de démonstration pour présenter le fonctionnement de HaloGari : départ à l’heure, échanges possibles avec les passagers et paiement sécurisé.');

        return $status;
    }
}
