<?php

namespace App\Tests\Activity;

use App\Activity\ActivityGrouper;
use App\Entity\Activity;
use App\Entity\Project;
use App\Enum\ActivityType;
use PHPUnit\Framework\TestCase;

final class ActivityGrouperTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        $this->project = new Project('G', 'Groupes');
    }

    public function testGroupsAtLeastThreeConsecutiveAutomaticEvents(): void
    {
        $items = new ActivityGrouper()->group([
            $this->entry(ActivityType::Note, message: 'Avant'),
            $this->entry(ActivityType::Created),
            $this->entry(ActivityType::Created),
            $this->entry(ActivityType::StatusChanged),
            $this->entry(ActivityType::Decision, message: 'Coupe'),
            $this->entry(ActivityType::Created),
            $this->entry(ActivityType::StatusChanged),
        ]);

        self::assertSame(['entry', 'group', 'entry', 'entry', 'entry'], array_column($items, 'kind'));
        self::assertCount(3, $items[1]['entries']);
        self::assertSame(2, $items[1]['created']);
        self::assertSame(1, $items[1]['statusChanged']);
    }

    public function testSessionAndDayBreakGroups(): void
    {
        $items = new ActivityGrouper('Europe/Paris')->group([
            $this->entry(ActivityType::Created, session: 'a'),
            $this->entry(ActivityType::Created, session: 'a'),
            $this->entry(ActivityType::Created, session: 'b'),
            $this->entry(ActivityType::Created, session: 'b'),
            $this->entry(ActivityType::Created, session: 'b'),
            // 21 h 30 UTC = 23 h 30 à Paris : même jour, rejoint le groupe.
            $this->entry(ActivityType::Created, session: 'b', at: '2026-09-16 21:30:00'),
            // 22 h 30 UTC = lendemain à Paris : le groupe s'arrête, deux entrées isolées.
            $this->entry(ActivityType::Created, session: 'b', at: '2026-09-16 22:30:00'),
            $this->entry(ActivityType::Created, session: 'b', at: '2026-09-16 22:45:00'),
        ]);

        self::assertSame(['entry', 'entry', 'group', 'entry', 'entry'], array_column($items, 'kind'));
        self::assertCount(4, $items[2]['entries']);
    }

    private function entry(ActivityType $type, ?string $message = null, ?string $session = 's', string $at = '2026-09-16 10:00:00'): Activity
    {
        $activity = new Activity($this->project, $type, $message)->setSessionId($session);
        new \ReflectionProperty(Activity::class, 'createdAt')->setValue($activity, new \DateTimeImmutable($at, new \DateTimeZone('UTC')));

        return $activity;
    }
}
