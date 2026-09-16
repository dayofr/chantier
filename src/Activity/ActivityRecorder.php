<?php

namespace App\Activity;

use App\Entity\Activity;
use App\Entity\Epic;
use App\Entity\Initiative;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\ActivityType;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Journalise automatiquement les créations et les changements de statut.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class ActivityRecorder
{
    public function __construct(private ActorContext $actor)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $metadata = $em->getClassMetadata(Activity::class);
        $activities = [];

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (null !== $subject = $this->describe($entity)) {
                $activities[] = $this->build($entity, ActivityType::Created, ['kind' => $subject['kind'], 'title' => $subject['title']]);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (null === $subject = $this->describe($entity)) {
                continue;
            }
            $changes = $uow->getEntityChangeSet($entity);
            if (!isset($changes['status'])) {
                continue;
            }
            [$from, $to] = $changes['status'];
            if ($from === $to) {
                continue;
            }
            $activities[] = $this->build($entity, ActivityType::StatusChanged, [
                'kind' => $subject['kind'],
                'from' => $from instanceof \BackedEnum ? $from->value : $from,
                'to' => $to instanceof \BackedEnum ? $to->value : $to,
            ]);
        }

        foreach ($activities as $activity) {
            $em->persist($activity);
            $uow->computeChangeSet($metadata, $activity);
        }
    }

    /** @return array{kind: string, title: string}|null */
    private function describe(object $entity): ?array
    {
        return match (true) {
            $entity instanceof Project => ['kind' => 'project', 'title' => $entity->getName()],
            $entity instanceof Initiative => ['kind' => 'initiative', 'title' => $entity->getTitle()],
            $entity instanceof Epic => ['kind' => 'epic', 'title' => $entity->getTitle()],
            $entity instanceof Ticket => ['kind' => 'ticket', 'title' => $entity->getTitle()],
            default => null,
        };
    }

    private function build(Project|Initiative|Epic|Ticket $entity, ActivityType $type, array $data): Activity
    {
        $project = $entity instanceof Project ? $entity : $entity->getProject();
        $activity = new Activity($project, $type);
        if ($entity instanceof Ticket) {
            $activity->setTicket($entity);
        } else {
            $activity->setSubjectKey($entity->getKey());
        }

        return $this->actor->stamp($activity->setData($data));
    }
}
