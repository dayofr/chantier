<?php

namespace App\Tests\Activity;

use App\Activity\SessionTracker;
use App\Repository\AgentSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SessionTrackerTest extends KernelTestCase
{
    use ResetDatabase;

    public function testCreatesThenThrottlesLastSeen(): void
    {
        $container = static::getContainer();
        $clock = new MockClock('2026-09-16 10:00:00');
        $tracker = new SessionTracker($container->get(AgentSessionRepository::class), $container->get(EntityManagerInterface::class), $clock);

        $session = $tracker->track('abc-123', 'claude-code');
        self::assertSame('claude-code', $session->getClient());
        self::assertEquals(new \DateTimeImmutable('2026-09-16 10:00:00'), $session->getStartedAt());

        $clock->sleep(SessionTracker::TOUCH_INTERVAL - 1);
        self::assertEquals(new \DateTimeImmutable('2026-09-16 10:00:00'), $tracker->track('abc-123', 'claude-code')->getLastSeenAt(), 'Pas d\'écriture avant l\'intervalle.');

        $clock->sleep(1);
        self::assertEquals($clock->now(), $tracker->track('abc-123', 'claude-code')->getLastSeenAt());
        self::assertCount(1, $container->get(AgentSessionRepository::class)->findAll());
    }
}
