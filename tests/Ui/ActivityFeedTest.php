<?php

namespace App\Tests\Ui;

use App\Entity\Activity;
use App\Entity\Epic;
use App\Entity\Initiative;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\ActivityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ActivityFeedTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $project = new Project('FEED', 'Journal');
        $other = new Project('OTHER', 'Autre');
        $initiative = new Initiative($project, 'Initiative');
        $epic = new Epic($initiative, 'Epic');
        $inEpic = new Ticket($project, 'Dans l\'epic')->setEpic($epic);
        $outside = new Ticket($project, 'Hors epic');

        foreach ([$project, $other, $initiative, $epic, $inEpic, $outside] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $notes = [
            new Activity($project, ActivityType::Decision, 'Décision sur l\'epic')->setTicket($inEpic)->setSessionId('s1'),
            new Activity($project, ActivityType::Note, 'Note hors epic')->setTicket($outside)->setSessionId('s2'),
            new Activity($project, ActivityType::Blocker, 'Blocage ancien')->setTicket($outside)->setSessionId('s2'),
            new Activity($other, ActivityType::Note, 'Note autre projet'),
        ];
        foreach ($notes as $note) {
            $em->persist($note);
        }
        $em->flush();

        // Le blocage date de 10 jours.
        $em->createQuery('UPDATE '.Activity::class.' a SET a.createdAt = :old WHERE a.message = :m')
            ->setParameters(['old' => new \DateTimeImmutable('-10 days'), 'm' => 'Blocage ancien'])
            ->execute();
    }

    /** Messages visibles dans le journal. */
    private function messages(string $url): array
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return $crawler->filter('main section.card ol li.group .prose-md')->each(static fn ($n) => trim($n->text()));
    }

    public function testFilterByType(): void
    {
        self::assertSame(['Décision sur l\'epic'], $this->messages('/fr/projects/FEED/activity?type[]=decision'));
        self::assertEqualsCanonicalizing(['Décision sur l\'epic', 'Blocage ancien'], $this->messages('/fr/projects/FEED/activity?type[]=decision&type[]=blocker'));
    }

    public function testUnknownTypeIsIgnored(): void
    {
        self::assertCount(3, $this->messages('/fr/projects/FEED/activity?type[]=nope'));
    }

    public function testFilterBySubjectCoversEpicAndInitiative(): void
    {
        self::assertSame(['Décision sur l\'epic'], $this->messages('/fr/activity?ticket=feed-e1'));
        self::assertSame(['Décision sur l\'epic'], $this->messages('/fr/activity?ticket=FEED-I1'));
        self::assertEqualsCanonicalizing(['Note hors epic', 'Blocage ancien'], $this->messages('/fr/activity?ticket=FEED-2'));
    }

    public function testFilterByPeriod(): void
    {
        self::assertEqualsCanonicalizing(['Décision sur l\'epic', 'Note hors epic'], $this->messages('/fr/projects/FEED/activity?period=7d'));
        self::assertCount(3, $this->messages('/fr/projects/FEED/activity?period=30d'));
    }

    public function testFiltersCombineWithSessionAndKeepEachOther(): void
    {
        $crawler = $this->client->request('GET', '/fr/projects/FEED/activity?session=s2&type[]=note');
        self::assertSame(['Note hors epic'], $crawler->filter('main section.card ol li.group .prose-md')->each(static fn ($n) => trim($n->text())));

        // Retirer la session garde le filtre de type.
        self::assertSame('/fr/projects/FEED/activity?type%5B0%5D=note', $crawler->filter('main a[aria-label="Retirer le filtre"]')->attr('href'));
        // Réinitialiser garde la session.
        self::assertSame('/fr/projects/FEED/activity?session=s2', $crawler->filter('form[data-autosubmit] a')->attr('href'));
    }

    public function testTypeCountsIgnoreTypeSelection(): void
    {
        $crawler = $this->client->request('GET', '/fr/projects/FEED/activity?type[]=decision');

        $counts = [];
        $crawler->filter('form[data-autosubmit] input[name="type[]"]')->each(function ($input) use (&$counts) {
            $counts[$input->attr('value')] = (int) $input->closest('label')->filter('span')->text();
        });
        self::assertSame(1, $counts['decision']);
        self::assertSame(1, $counts['note']);
        self::assertSame(1, $counts['blocker']);
    }

    public function testSearchIsAccentAndCaseInsensitiveAndHighlights(): void
    {
        self::assertSame(['Décision sur l\'epic'], $this->messages('/fr/activity?q=DECISION'));
        self::assertSame(['Note hors epic'], $this->messages('/fr/projects/FEED/activity?q=note+hors'));
        self::assertSame([], $this->messages('/fr/projects/FEED/activity?q=note+absent'));

        $crawler = $this->client->request('GET', '/fr/activity?q=decision');
        self::assertSame('Décision', $crawler->filter('main li.group .prose-md mark')->text());
        // Recherche combinée aux filtres et conservée par les liens de session.
        self::assertStringContainsString('q=decision', $crawler->filter('aside a[href*="session=s1"]')->attr('href'));
    }

    public function testSearchIndexIncludesTicketTitle(): void
    {
        self::assertEqualsCanonicalizing(['Note hors epic', 'Blocage ancien'], $this->messages('/fr/projects/FEED/activity?q=hors+epic&period=30d'));
    }

    public function testLoadMoreFragmentAndUntil(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $project = $em->getRepository(Project::class)->findOneBy(['key' => 'OTHER']);
        for ($i = 1; $i <= 60; ++$i) {
            $em->persist(new Activity($project, ActivityType::Progress, "Étape $i"));
        }
        $em->flush();

        // Première page : 50 entrées et un lien AJAX vers la suite.
        $crawler = $this->client->request('GET', '/fr/projects/OTHER/activity');
        self::assertCount(50, $crawler->filter('#activity-feed [data-entry]'));
        $next = $crawler->filter('a[data-load-more="activity-feed"]');
        self::assertStringContainsString('before=', $next->attr('href'));

        // Fragment : les 12 suivantes (10 étapes, la note et la création du projet), sans layout ni nouveau lien.
        $fragment = $this->client->request('GET', $next->attr('href').'&fragment=1');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $fragment->filter('aside, header, form'));
        self::assertCount(12, $fragment->filter('[data-feed-items] [data-entry]'));
        self::assertCount(1, $fragment->filter('[data-feed-items] [data-day]'));
        self::assertCount(0, $fragment->filter('a[data-load-more]'));

        // Rafraîchissement d'un journal étendu : tout jusqu'à l'id demandé, lien suivant conservé s'il en reste.
        $ids = $crawler->filter('#activity-feed [data-entry]')->each(static fn ($n) => (int) $n->attr('data-entry'));
        $until = $this->client->request('GET', '/fr/projects/OTHER/activity?until='.$ids[49]);
        self::assertCount(50, $until->filter('#activity-feed [data-entry]'));
        self::assertCount(1, $until->filter('a[data-load-more]'));

        $all = $this->client->request('GET', '/fr/projects/OTHER/activity?until=1');
        self::assertCount(62, $all->filter('#activity-feed [data-entry]'));
        self::assertCount(0, $all->filter('a[data-load-more]'));
    }

    public function testNoMatchMessage(): void
    {
        $this->client->request('GET', '/fr/projects/FEED/activity?type[]=commit');
        self::assertSelectorTextContains('main', 'Aucune entrée ne correspond aux filtres.');
    }
}
