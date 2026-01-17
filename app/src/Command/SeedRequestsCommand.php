<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Request;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:requests',
    description: 'Seed request records for existing users.',
)]
final class SeedRequestsCommand extends Command
{
    private const RAW_TEXTS = [
        'Looking for a photographer for a small outdoor wedding in June.',
        'Need a personal trainer who can create a 6-week strength program.',
        'Searching for a bilingual tutor to help with business English presentations.',
        'Looking for a reliable electrician to inspect a new apartment.',
        'Need a graphic designer for a minimal logo and brand kit.',
        'Looking for a dog walker available weekday mornings.',
        'Seeking a freelance developer to build a simple booking website.',
        'Need help planning a 3-day family trip with kid-friendly activities.',
        'Looking for a dietitian to create a meal plan for marathon training.',
        'Need a handyman to assemble furniture and mount shelves.',
        'Looking for a photographer for product shots of handmade jewelry.',
        'Searching for a Spanish tutor for weekly conversation practice.',
        'Need a cleaner for a one-time deep clean before moving in.',
        'Looking for a landscaper to redesign a small backyard garden.',
        'Need a plumber to fix a leaking kitchen faucet.',
        'Looking for a UI/UX designer to review a mobile app prototype.',
        'Seeking a stylist for a professional headshot session.',
        'Need a copywriter for website landing page updates.',
        'Looking for a virtual assistant to manage calendar and email.',
        'Need a math tutor for high school calculus support.',
        'Looking for a videographer for a short promotional clip.',
        'Need help translating a short document from French to English.',
        'Looking for a yoga instructor for private sessions.',
        'Searching for a caterer for a small office event.',
        'Need a mechanic to inspect a used car before purchase.',
        'Looking for a makeup artist for an evening event.',
        'Need a social media manager to plan monthly content.',
        'Looking for a web developer to optimize site performance.',
        'Need a babysitter for occasional weekend evenings.',
        'Looking for a career coach to prepare for interviews.',
        'Need a tailor to adjust a suit for an upcoming wedding.',
        'Looking for a translator for subtitles on a short video.',
        'Need a nutritionist to help with a plant-based diet plan.',
        'Looking for a photographer for a family portrait session.',
        'Need a decorator for a small studio apartment refresh.',
        'Looking for a language exchange partner for German practice.',
        'Need a personal chef for weekly meal prep.',
        'Looking for a fitness coach for remote training sessions.',
        'Need a consultant to review a startup pitch deck.',
        'Looking for a tour guide for a walking city tour.',
    ];

    private const LOCATIONS = [
        ['city' => 'Berlin', 'country' => 'DEU'],
        ['city' => 'Paris', 'country' => 'FRA'],
        ['city' => 'Tokyo', 'country' => 'JPN'],
        ['city' => 'Lisbon', 'country' => 'PRT'],
        ['city' => 'London', 'country' => 'GBR'],
        ['city' => 'Madrid', 'country' => 'ESP'],
        ['city' => 'Rome', 'country' => 'ITA'],
        ['city' => 'Amsterdam', 'country' => 'NLD'],
        ['city' => 'Copenhagen', 'country' => 'DNK'],
        ['city' => 'Oslo', 'country' => 'NOR'],
        ['city' => 'Stockholm', 'country' => 'SWE'],
        ['city' => 'Helsinki', 'country' => 'FIN'],
        ['city' => 'Vienna', 'country' => 'AUT'],
        ['city' => 'Prague', 'country' => 'CZE'],
        ['city' => 'Warsaw', 'country' => 'POL'],
        ['city' => 'Zurich', 'country' => 'CHE'],
        ['city' => 'Geneva', 'country' => 'CHE'],
        ['city' => 'Dublin', 'country' => 'IRL'],
        ['city' => 'Brussels', 'country' => 'BEL'],
        ['city' => 'Athens', 'country' => 'GRC'],
        ['city' => 'Budapest', 'country' => 'HUN'],
        ['city' => 'Reykjavik', 'country' => 'ISL'],
        ['city' => 'Tallinn', 'country' => 'EST'],
        ['city' => 'Riga', 'country' => 'LVA'],
        ['city' => 'Vilnius', 'country' => 'LTU'],
        ['city' => 'Singapore', 'country' => 'SGP'],
        ['city' => 'Sydney', 'country' => 'AUS'],
        ['city' => 'Melbourne', 'country' => 'AUS'],
        ['city' => 'Toronto', 'country' => 'CAN'],
        ['city' => 'Vancouver', 'country' => 'CAN'],
        ['city' => 'New York', 'country' => 'USA'],
        ['city' => 'Chicago', 'country' => 'USA'],
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', null, InputOption::VALUE_OPTIONAL, 'Number of requests to create.', 20)
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Status to assign to requests.', 'active')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Random createdAt within last N days.', 14);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');
        $status = (string) $input->getOption('status');
        $days = (int) $input->getOption('days');

        if ($count < 1) {
            $io->error('Count must be a positive integer.');

            return Command::INVALID;
        }

        if ($days < 1) {
            $io->error('Days must be a positive integer.');

            return Command::INVALID;
        }

        $users = $this->userRepository->findAll();
        $userCount = count($users);

        if ($userCount === 0) {
            $io->error('No users found. Please create at least one user first.');

            return Command::FAILURE;
        }

        $io->writeln(sprintf('Found %d users.', $userCount));

        $shuffledUsers = $users;
        shuffle($shuffledUsers);

        $samples = [];
        $now = new DateTimeImmutable();
        $maxOffset = $days * 86400;

        for ($index = 0; $index < $count; $index++) {
            $owner = $shuffledUsers[$index % $userCount];
            $location = self::LOCATIONS[array_rand(self::LOCATIONS)];
            $createdAt = $now->setTimestamp($now->getTimestamp() - random_int(0, $maxOffset));

            $request = new Request();
            $request
                ->setOwner($owner)
                ->setRawText(self::RAW_TEXTS[array_rand(self::RAW_TEXTS)])
                ->setCity($location['city'])
                ->setCountry($location['country'])
                ->setStatus($status)
                ->setCreatedAt($createdAt);

            $this->entityManager->persist($request);

            if (count($samples) < 3) {
                $samples[] = $request;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Created %d requests.', $count));

        if ($samples !== []) {
            $io->writeln('Sample requests (id/ownerId/city):');
            foreach ($samples as $sample) {
                $io->writeln(sprintf(
                    '- %d/%d/%s',
                    $sample->getId(),
                    $sample->getOwner()->getId(),
                    $sample->getCity() ?? '-'
                ));
            }
        }

        return Command::SUCCESS;
    }
}
