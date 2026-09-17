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
        $a = new Ticket($project, 'Premier')->setEpic($epic)->setStatus(TicketStatus::InProgress)->setLabels(['ui'])->setAssignee('claude-code');
        $b = new Ticket($project, 'Second')->setEpic($epic);
        $orphan = new Ticket($project, 'Orphelin');
        $a->addSubTask('Étape 1')->setDone(true);
        new TicketLink($a, LinkType::Commit, 'abc123');
        new TicketLink($a, LinkType::PullRequest, 'https://example.test/pr/1', 'PR');
        $b->addSubTask('Pas fait');
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
            yield "$locale initiative" => ["/$locale/initiatives/DEMO-I1", 'Premier'];
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

    public function testTicketShowsDependencyGraph(): void
    {
        $this->client->request('GET', '/fr/tickets/DEMO-1');
        self::assertSelectorExists('svg[role="img"] a[href="/fr/tickets/DEMO-2"]');

        $this->client->request('GET', '/fr/tickets/DEMO-3');
        self::assertSelectorNotExists('svg[role="img"]');
    }

    public function testNewSinceLastVisitHooks(): void
    {
        $crawler = $this->client->request('GET', '/fr/activity');

        $latest = (int) $crawler->filter('[data-live]')->attr('data-latest-activity');
        $ids = $crawler->filter('#activity-feed [data-entry]')->each(static fn ($n) => (int) $n->attr('data-entry'));
        self::assertSame(max($ids), $latest);
        self::assertSame('Déjà vu lors de votre dernière visite', $crawler->filter('#activity-feed')->attr('data-label-seen'));
        self::assertCount(1, $crawler->filter('aside a[href="/fr/activity"] [data-new-count][hidden]'));
    }

    public function testInitiativePageShowsBoardAndDecisions(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $project = $em->getRepository(Project::class)->findOneBy(['key' => 'DEMO']);
        $em->persist(new Activity($project, ActivityType::Decision, 'Décision d\'initiative')->setSubjectKey('DEMO-I1'));
        $em->persist(new Activity($project, ActivityType::Decision, 'Décision d\'epic')->setSubjectKey('DEMO-E1'));
        $em->flush();

        // La vue projet mène à la vue initiative et n'affiche plus les décisions.
        $overview = $this->client->request('GET', '/fr/projects/DEMO');
        // Titre + bouton « Ouvrir » visible.
        self::assertCount(2, $overview->filter('main a[href="/fr/initiatives/DEMO-I1"]'));
        self::assertStringEndsWith('Ouvrir', trim($overview->filter('main summary a[href="/fr/initiatives/DEMO-I1"]')->last()->text()));
        // Pas d'initiatives dans la barre latérale (choix de l'utilisateur).
        self::assertCount(0, $overview->filter('aside a[href^="/fr/initiatives/"]'));
        self::assertCount(1, $overview->filter('main a[href="/fr/initiatives/DEMO-I1?epic=DEMO-E1"]'));
        self::assertStringNotContainsString('Décision d\'initiative', $overview->filter('main')->text());

        $crawler = $this->client->request('GET', '/fr/initiatives/DEMO-I1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Socle');

        // Le détail d'un ticket mène à son initiative et à son epic dans l'initiative.
        $ticket = $this->client->request('GET', '/fr/tickets/DEMO-1');
        self::assertCount(1, $ticket->filter('main dd a[href="/fr/initiatives/DEMO-I1"]'));
        self::assertCount(1, $ticket->filter('main dd a[href="/fr/initiatives/DEMO-I1?epic=DEMO-E1"]'));

        // Décisions : initiative, epic et ticket (setUp : décision sur DEMO-1), plus récente d'abord.
        $decisions = $crawler->filter('#decisions li .prose-md')->each(static fn ($n) => trim($n->text()));
        self::assertSame(['Décision d\'epic', 'Décision d\'initiative', 'On garde SQLite.'], $decisions);

        // Kanban : tickets de l'initiative seulement (l'orphelin DEMO-3 n'y est pas).
        $keys = $crawler->filter('main a.card .key')->each(static fn ($n) => $n->text());
        self::assertEqualsCanonicalizing(['DEMO-1', 'DEMO-2'], $keys);
    }

    public function testInitiativeEpicFilterAndNotFound(): void
    {
        $crawler = $this->client->request('GET', '/fr/initiatives/DEMO-I1?epic=DEMO-E1');
        self::assertResponseIsSuccessful();
        self::assertSame('/fr/initiatives/DEMO-I1', $crawler->filter('nav[aria-label="Filtrer par epic"] a')->first()->attr('href'));

        $this->client->request('GET', '/fr/initiatives/DEMO-I1?epic=DEMO-E99');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/fr/initiatives/DEMO-I9');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSidebarCanBeCollapsed(): void
    {
        $this->client->request('GET', '/en');

        self::assertSelectorExists('aside#sidebar');
        self::assertSelectorExists('[data-sidebar-toggle][aria-controls="sidebar"][data-label-expand="Expand menu"]');
    }

    /** Chaque icône affichée doit être dans le sous-ensemble téléchargé (bin/download-fonts). */
    public function testIconsAreInSelfHostedSubset(): void
    {
        $manifest = array_filter(array_map('trim', file(__DIR__.'/../../assets/fonts/icons.txt')));
        $used = [];

        foreach (self::pages() as [$url]) {
            $this->client->request('GET', $url);
            $html = $this->client->getResponse()->getContent();
            self::assertStringNotContainsString('fonts.googleapis.com', $html);
            preg_match_all('~<span class="icon[^"]*"[^>]*>\s*([a-z0-9_]+)\s*</span>~', $html, $m);
            array_push($used, ...$m[1]);
        }
        // Icônes posées par JavaScript (thème, barre latérale).
        foreach (glob(__DIR__.'/../../assets/*.js') as $file) {
            foreach (file($file) as $line) {
                if (str_contains($line, 'icon') && preg_match_all("~'([a-z][a-z0-9_]+)'~", $line, $m)) {
                    array_push($used, ...array_diff($m[1], ['click', 'change', 'keydown', 'system', 'light', 'dark', 'collapsed', 'expanded']));
                }
            }
        }

        self::assertSame([], array_values(array_diff(array_unique($used), $manifest)), 'Ajouter ces icônes à assets/fonts/icons.txt puis lancer php bin/download-fonts.');
    }

    public function testSessionFilterCanBeCleared(): void
    {
        $crawler = $this->client->request('GET', '/fr/activity?session=11111111-2222-3333-4444-555555555555');

        $clear = $crawler->filter('main a[aria-label="Retirer le filtre"]');
        self::assertCount(1, $clear);
        self::assertSame('/fr/activity', $clear->attr('href'));
        self::assertSelectorExists('aside a[aria-current="true"][href$="session=11111111-2222-3333-4444-555555555555"]');

        $this->client->request('GET', '/fr/activity');
        self::assertSelectorTextContains('aside a[aria-current="true"]', 'Toutes les sessions');
    }

    public function testPathsWithoutLanguageRedirect(): void
    {
        $this->client->request('GET', '/activity?session=abc', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);
        self::assertResponseRedirects('/en/activity?session=abc');

        $this->client->request('GET', '/projects/DEMO/board', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR']);
        self::assertResponseRedirects('/fr/projects/DEMO/board');

        // Page inexistante, API et MCP : pas de redirection.
        $this->client->request('GET', '/nimporte-quoi');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/tickets/NOPE-1');
        self::assertResponseStatusCodeSame(404);
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
