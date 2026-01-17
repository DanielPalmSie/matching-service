<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Request;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

class RequestFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $users = $manager->getRepository(User::class)->findAll();

        if ($users === []) {
            throw new RuntimeException('Cannot load Request fixtures: no users found.');
        }

        $cities = [
            ['city' => 'London', 'country' => 'GBR'],
            ['city' => 'Paris', 'country' => 'FRA'],
            ['city' => 'Berlin', 'country' => 'DEU'],
            ['city' => 'Madrid', 'country' => 'ESP'],
            ['city' => 'Rome', 'country' => 'ITA'],
            ['city' => 'Lisbon', 'country' => 'PRT'],
            ['city' => 'Amsterdam', 'country' => 'NLD'],
            ['city' => 'Dublin', 'country' => 'IRL'],
            ['city' => 'Warsaw', 'country' => 'POL'],
            ['city' => 'Vienna', 'country' => 'AUT'],
        ];

        $rawTexts = [
            'Looking for a cozy coffee shop to host a small book club meetup this weekend.',
            'Seeking recommendations for a beginner-friendly gym with personal trainers.',
            'Trying to find affordable theater tickets for a modern drama next Friday.',
            'Need suggestions for a weekend getaway within a short train ride.',
            'Looking for a language exchange partner for weekly practice sessions.',
            'Searching for a quiet coworking space with good Wi-Fi and meeting rooms.',
            'Need help finding a family-friendly museum with interactive exhibits.',
            'Interested in a yoga studio that offers early morning classes.',
            'Planning a casual networking dinner for tech professionals.',
            'Want to find a scenic running route near the city center.',
            'Looking for a local guide to show historic landmarks over two days.',
            'Seeking a reliable tailor for minor alterations on work clothes.',
            'Need recommendations for a vegetarian-friendly brunch spot.',
            'Searching for a photography workshop focused on street scenes.',
            'Planning a small birthday dinner and need a cozy restaurant.',
            'Looking for a beginner pottery class with evening sessions.',
            'Need a dog-friendly park with open space for training.',
            'Seeking advice on public transport passes for a week-long visit.',
            'Trying to book a guided day trip to nearby hiking trails.',
            'Looking for a volunteer opportunity at a local community center.',
        ];

        for ($i = 0; $i < 20; $i++) {
            $location = $cities[array_rand($cities)];
            $request = new Request();
            $request
                ->setOwner($users[array_rand($users)])
                ->setRawText($rawTexts[array_rand($rawTexts)])
                ->setCity($location['city'])
                ->setCountry($location['country'])
                ->setStatus('active')
                ->setCreatedAt($this->randomRecentDate());

            $manager->persist($request);
        }

        $manager->flush();
    }

    private function randomRecentDate(): DateTimeImmutable
    {
        $days = random_int(0, 13);
        $hours = random_int(0, 23);
        $minutes = random_int(0, 59);

        return (new DateTimeImmutable())->modify(sprintf('-%d days -%d hours -%d minutes', $days, $hours, $minutes));
    }
}
