<?php

namespace App\Tests\Ui;

use App\Entity\Activity;
use App\Entity\AgentSession;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\ActivityType;
use App\Enum\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SessionPageTest extends WebTestCase
{
    use ResetDatabase;

    public function testShowsSummaryDecisionsAndReport(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $actor = static::getContainer()->get(\App\Activity\ActorContext::class);
        $actor->set('claude-code', 'sess-1');

        $session = new AgentSession('sess-1', 'claude-code', new \DateTimeImmutable('-90 minutes'))
            ->setTitle('Séance de test')
            ->setBranch('feat/x')
            ->setSummary("**Demande** : tester\n\n<script>alert(1)</script>");
        $project = new Project('SES', 'Sessions');
        $created = new Ticket($project, 'Créé pendant la séance');
        foreach ([$session, $project, $created] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $created->setStatus(TicketStatus::Done);
        $em->persist($actor->stamp(new Activity($project, ActivityType::Decision, 'Garder SQLite.')->setTicket($created)));
        $em->flush();

        $crawler = $client->request('GET', '/fr/sessions/sess-1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Séance de test');
        self::assertSelectorTextContains('main', 'feat/x');
        self::assertSelectorTextContains('main', 'Durée 1 h 30 min');
        self::assertStringContainsString('<strong>Demande</strong>', $client->getResponse()->getContent());
        self::assertStringNotContainsString('<script>alert(1)</script>', $client->getResponse()->getContent());
        self::assertSelectorTextContains('main', 'Garder SQLite.');

        // Bilan : créé puis terminé dans la séance.
        $report = $crawler->filter('main aside dd')->each(static fn ($n) => (int) $n->text());
        self::assertSame([1, 1, 0, 0], $report);

        // Le journal renvoie vers la fiche, la fiche vers le journal filtré.
        self::assertSelectorExists('#activity-feed a[href="/fr/sessions/sess-1"]');
        self::assertSelectorExists('a[href="/fr/activity?session=sess-1"]');
    }

    public function testUnknownSessionIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/sessions/nope');

        self::assertResponseStatusCodeSame(404);
    }
}
