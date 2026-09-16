<?php

namespace App\Tests\Service;

use App\Entity\Epic;
use App\Entity\Initiative;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\TicketDependency;
use App\Enum\TicketStatus;
use App\Repository\ActivityRepository;
use App\Service\ProjectAlerts;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ProjectAlertsTest extends KernelTestCase
{
    use ResetDatabase;

    public function testRules(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $project = new Project('AL', 'Alertes');
        $epic = new Epic(new Initiative($project, 'I'), 'E');

        $bottleneck = new Ticket($project, 'Goulot')->setEpic($epic)->setStatus(TicketStatus::InProgress);
        $b1 = new Ticket($project, 'Attend 1')->setEpic($epic);
        $b2 = new Ticket($project, 'Attend 2')->setEpic($epic);
        $explicit = new Ticket($project, 'Bloqué sans raison')->setEpic($epic)->setStatus(TicketStatus::Blocked);
        $orphan = new Ticket($project, 'Orphelin');
        $doneOrphan = new Ticket($project, 'Orphelin fini')->setStatus(TicketStatus::Done);
        $review = new Ticket($project, 'En revue récente')->setEpic($epic)->setStatus(TicketStatus::InReview);

        foreach ([$project, $epic->getInitiative(), $epic, $bottleneck, $b1, $b2, $explicit, $orphan, $doneOrphan, $review,
            new TicketDependency($bottleneck, $b1), new TicketDependency($bottleneck, $b2)] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();
        $project = $em->getRepository(Project::class)->findOneBy(['key' => 'AL']);

        // "Goulot" et "En revue" ont bougé à t0. 50 h plus tard, seuil 48 h : les deux sont signalés.
        $alerts = $this->alerts($project, '+50 hours');
        $found = array_map(static fn ($a) => $a['type'].':'.$a['ticket']->getTitle().':'.$a['severity'], $alerts);

        self::assertSame([
            'blocked:Bloqué sans raison:high',
            'stale:Goulot:medium',
            'stale:En revue récente:medium',
            'blocked:Attend 1:low',
            'blocked:Attend 2:low',
            'orphan:Orphelin:low',
        ], array_values(array_diff($found, ['bottleneck:Goulot:medium'])));
        self::assertContains('bottleneck:Goulot:medium', $found);

        // Moins de 48 h : pas d'alerte "stale".
        self::assertEmpty(array_filter($this->alerts($project, '+10 hours'), static fn ($a) => 'stale' === $a['type']));

        // Au-delà de 3 fois le seuil : sévérité haute.
        $stale = array_values(array_filter($this->alerts($project, '+200 hours'), static fn ($a) => 'stale' === $a['type']));
        self::assertSame('high', $stale[0]['severity']);
        self::assertGreaterThanOrEqual(200, $stale[0]['idleHours']);
    }

    private function alerts(Project $project, string $offset): array
    {
        $clock = new MockClock(new \DateTimeImmutable($offset));

        return new ProjectAlerts(static::getContainer()->get(ActivityRepository::class), $clock, 48)->for($project);
    }
}
