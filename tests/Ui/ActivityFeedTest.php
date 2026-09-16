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

    public function testNoMatchMessage(): void
    {
        $this->client->request('GET', '/fr/projects/FEED/activity?type[]=commit');
        self::assertSelectorTextContains('main', 'Aucune entrée ne correspond aux filtres.');
    }
}
