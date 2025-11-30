<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\MatchFeedback;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MatchFeedbackFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $entries = [
            [1, 'matches are not relevant', 'not_relevant', -1, new \DateTimeImmutable('-1 day')],
            [2, 'too far from my city', 'too_far', 0, new \DateTimeImmutable('-2 days')],
            [3, 'old and not actual', 'old_request', -1, new \DateTimeImmutable('-8 days')],
            [4, 'great match, very helpful', null, 2, new \DateTimeImmutable('-3 days')],
            [5, 'spam / nonsense', 'spam', -1, new \DateTimeImmutable('-10 days')],
            [1, 'language mismatch with candidates', 'language_mismatch', 0, new \DateTimeImmutable('-4 days')],
            [2, 'matched profile was accurate', null, 2, new \DateTimeImmutable('-5 days')],
            [3, 'too far to travel for meetings', 'too_far', 0, new \DateTimeImmutable('-6 days')],
            [4, 'follow-up was fast', null, 1, new \DateTimeImmutable('-7 days')],
            [5, 'request looks outdated', 'old_request', -1, new \DateTimeImmutable('-9 days')],
            [1, 'language barrier caused issues', 'language_mismatch', -1, new \DateTimeImmutable('-12 days')],
            [2, 'good quality suggestions', null, 1, new \DateTimeImmutable('-1 day')],
            [3, 'irrelevant offers received', 'not_relevant', -1, new \DateTimeImmutable('-2 days')],
            [4, 'distance was acceptable', null, 1, new \DateTimeImmutable('-15 days')],
            [5, 'inappropriate content', 'spam', -1, new \DateTimeImmutable('-3 days')],
            [1, 'old listing sent again', 'old_request', -1, new \DateTimeImmutable('-14 days')],
            [2, null, null, 0, new \DateTimeImmutable('-5 days')],
            [3, 'match was perfect', null, 2, new \DateTimeImmutable('-6 hours')],
            [4, 'did not match my language', 'language_mismatch', 0, new \DateTimeImmutable('-11 days')],
            [5, 'too far from location', 'too_far', 0, new \DateTimeImmutable('-13 days')],
            [1, 'relevant and timely', null, 2, new \DateTimeImmutable('-4 hours')],
            [2, 'comment missing but tracking', null, 0, new \DateTimeImmutable('-30 days')],
            [3, 'seems like spam message', 'spam', -1, new \DateTimeImmutable('-20 days')],
            [4, null, 'not_relevant', -1, new \DateTimeImmutable('-18 days')],
        ];

        foreach ($entries as [$userId, $comment, $reasonCode, $score, $createdAt]) {
            $feedback = new MatchFeedback($userId, $comment, $reasonCode, $score, $createdAt);
            $manager->persist($feedback);
        }

        $manager->flush();
    }
}
