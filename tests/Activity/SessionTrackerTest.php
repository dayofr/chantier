<?php

namespace App\Tests\Activity;

use App\Activity\SessionTracker;
use App\Repository\AgentSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SessionTrackerTest extends KernelTestCase
{
    use ResetDatabase;

    private MockClock $clock;
    private SessionTracker $tracker;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->clock = new MockClock('2026-09-16 10:00:00');
        $this->tracker = new SessionTracker(
            $container->get(AgentSessionRepository::class),
            $container->get(EntityManagerInterface::class),
            $this->clock,
            new LockFactory(new InMemoryStore()),
            idleHours: 4,
        );
    }

    public function testStatefulSessionIsCreatedThenTouchedWithThrottle(): void
    {
        $session = $this->tracker->track('abc-123', 'claude-code');
        self::assertEquals(new \DateTimeImmutable('2026-09-16 10:00:00'), $session->getStartedAt());

        $this->clock->sleep(SessionTracker::TOUCH_INTERVAL - 1);
        self::assertEquals(new \DateTimeImmutable('2026-09-16 10:00:00'), $this->tracker->track('abc-123', 'claude-code')->getLastSeenAt(), 'Pas d\'écriture avant l\'intervalle.');

        $this->clock->sleep(1);
        self::assertEquals($this->clock->now(), $this->tracker->track('abc-123', 'claude-code')->getLastSeenAt());
    }

    public function testStatelessCallsReuseActiveSessionUntilIdle(): void
    {
        $first = $this->tracker->current('claude-code');
        self::assertSame($first, $this->tracker->current('claude-code'));
        self::assertNotSame($first, $this->tracker->current('cursor'), 'Un autre client a sa propre séance.');

        // Actif toutes les 3 h : même séance.
        $this->clock->sleep(3 * 3600);
        self::assertSame($first->getSessionId(), $this->tracker->current('claude-code')->getSessionId());

        // Plus de 4 h sans activité : nouvelle séance.
        $this->clock->sleep(4 * 3600 + 1);
        self::assertNotSame($first->getSessionId(), $this->tracker->current('claude-code')->getSessionId());
    }

    public function testStartAlwaysOpensAndBecomesCurrent(): void
    {
        $old = $this->tracker->current('claude-code');
        $this->clock->sleep(1);
        $new = $this->tracker->start('claude-code');

        self::assertNotSame($old->getSessionId(), $new->getSessionId());
        self::assertSame($new->getSessionId(), $this->tracker->current('claude-code')->getSessionId());
        self::assertNull($this->tracker->find('inconnue'));
    }
}
