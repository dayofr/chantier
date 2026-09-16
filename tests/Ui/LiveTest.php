<?php

namespace App\Tests\Ui;

use App\Entity\Project;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LiveTest extends WebTestCase
{
    use ResetDatabase;

    public function testVersionChangesOnEveryWrite(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $v1 = $this->version($client);
        self::assertSame($v1, $this->version($client), 'Sans écriture, la version ne bouge pas.');

        $project = new Project('LIVE', 'Live');
        $ticket = new Ticket($project, 'Un');
        $em->persist($project);
        $em->persist($ticket);
        $em->flush();
        $v2 = $this->version($client);
        self::assertNotSame($v1, $v2);

        // Modification d'une collection seule (sous-tâche) : doit aussi changer la version.
        // L'EntityManager est réinitialisé entre deux requêtes : on recharge le ticket.
        usleep(2000);
        $ticket = $em->getRepository(Ticket::class)->findOneBy(['key' => 'LIVE-1']);
        $ticket->addSubTask('Étape');
        $em->flush();
        self::assertNotSame($v2, $this->version($client));
    }

    public function testPagesExposeLiveVersion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr');

        self::assertSelectorExists('[data-live][data-live-url="/_live/version"][data-live-version]');
        self::assertSelectorExists('#live-main[data-live-region]');
        self::assertSelectorExists('#live-sidebar[data-live-region]');
    }

    private function version($client): string
    {
        $client->request('GET', '/_live/version');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');

        return json_decode($client->getResponse()->getContent(), true)['version'];
    }
}
