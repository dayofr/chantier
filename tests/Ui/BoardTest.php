<?php

namespace App\Tests\Ui;

use App\Service\KanbanBoard;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\Priority;
use App\Enum\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class BoardTest extends WebTestCase
{
    use ResetDatabase;

    public function testDoneColumnShowsMostRecentlyCompletedFirstAndCollapsesOlder(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $project = new Project('KB', 'Kanban');
        $em->persist($project);

        // 12 tickets terminés : le numéro 1 fini en dernier, les autres dans l'ordre inverse de leur numéro.
        $completedAt = new \ReflectionProperty(Ticket::class, 'completedAt');
        for ($i = 1; $i <= KanbanBoard::DONE_VISIBLE + 2; ++$i) {
            $ticket = new Ticket($project, "Fini $i")->setStatus(TicketStatus::Done)->setPriority(1 === $i ? Priority::Low : Priority::Urgent);
            $completedAt->setValue($ticket, new \DateTimeImmutable(1 === $i ? '-1 minute' : "-$i hours"));
            $em->persist($ticket);
        }
        $em->flush();
        $em->clear();

        $crawler = $client->request('GET', '/fr/projects/KB/board');
        self::assertResponseIsSuccessful();

        $done = $crawler->filter('section')->reduce(static fn ($s) => str_contains($s->filter('h2')->count() ? $s->filter('h2')->text() : '', 'Terminé'));
        $visible = $done->filterXPath('.//a[not(ancestor::details)]')->each(static fn ($a) => $a->filter('p')->first()->text());
        self::assertSame(['Fini 1', 'Fini 2', 'Fini 3'], \array_slice($visible, 0, 3), 'Le plus récent d\'abord, sans tenir compte de la priorité.');
        self::assertCount(KanbanBoard::DONE_VISIBLE, $visible);

        $older = $done->filter('details a')->each(static fn ($a) => $a->filter('p')->first()->text());
        self::assertSame(['Fini 11', 'Fini 12'], $older);
        self::assertStringContainsString('Afficher les 2 plus anciens', $done->filter('details summary')->text());
    }
}
