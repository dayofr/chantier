<?php

namespace App\Tests\Ui;

use App\Entity\Activity;
use App\Entity\Epic;
use App\Entity\Initiative;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\TicketDependency;
use App\Entity\TicketLink;
use App\Enum\ActivityType;
use App\Enum\LinkType;
use App\Enum\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PagesTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $project = new Project('DEMO', 'Démo')->setDescription("Projet **démo**\n\n<script>alert(1)</script>");
        $initiative = new Initiative($project, 'Socle');
        $epic = new Epic($initiative, 'API');
        $a = new Ticket($project, 'Premier')->setEpic($epic)->setStatus(TicketStatus::InProgress);
        $b = new Ticket($project, 'Second')->setEpic($epic);
        $orphan = new Ticket($project, 'Orphelin');
        $a->addSubTask('Étape 1')->setDone(true);
        new TicketLink($a, LinkType::Commit, 'abc123');
        $note = new Activity($project, ActivityType::Decision, 'On garde *SQLite*.')->setTicket($a)->setSessionId('11111111-2222-3333-4444-555555555555')->setAuthor('claude-code');

        foreach ([$project, $initiative, $epic, $a, $b, $orphan, new TicketDependency($a, $b), $note] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();
    }

    public static function pages(): iterable
    {
        foreach (['fr', 'en'] as $locale) {
            yield "$locale portfolio" => ["/$locale", 'Démo'];
            yield "$locale overview" => ["/$locale/projects/DEMO", 'Socle'];
            yield "$locale board" => ["/$locale/projects/DEMO/board", 'Premier'];
            yield "$locale board filtered" => ["/$locale/projects/DEMO/board?epic=none", 'Orphelin'];
            yield "$locale ticket" => ["/$locale/tickets/DEMO-1", 'abc123'];
            yield "$locale project activity" => ["/$locale/projects/DEMO/activity", 'SQLite'];
            yield "$locale activity" => ["/$locale/activity?session=11111111-2222-3333-4444-555555555555", '#11111111'];
        }
    }

    #[DataProvider('pages')]
    public function testPageRenders(string $url, string $expected): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $expected);
    }

    public function testRootRedirectsToPreferredLanguage(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);

        self::assertResponseRedirects('/en');
    }

    public function testLabelsAreTranslated(): void
    {
        $this->client->request('GET', '/fr/projects/DEMO/board');
        self::assertSelectorTextContains('main', 'En cours');

        $this->client->request('GET', '/en/projects/DEMO/board');
        self::assertSelectorTextContains('main', 'In progress');
    }

    public function testMarkdownEscapesRawHtml(): void
    {
        $this->client->request('GET', '/fr/projects/DEMO');

        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('<strong>démo</strong>', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testUnknownItemsReturn404(): void
    {
        $this->client->request('GET', '/fr/projects/NOPE');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/fr/tickets/DEMO-99');
        self::assertResponseStatusCodeSame(404);
    }

    public function testNoWriteRoutesOutsideApiAndMcp(): void
    {
        $router = static::getContainer()->get('router');

        foreach ($router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            if (str_starts_with($path, '/api') || str_starts_with($path, '/mcp') || str_starts_with($name, '_')) {
                continue;
            }
            self::assertSame(['GET'], $route->getMethods(), "La route $name doit être en lecture seule.");
        }
    }
}
