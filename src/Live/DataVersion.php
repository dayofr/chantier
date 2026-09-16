<?php

namespace App\Live;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Version globale des données, changée à chaque écriture Doctrine.
 * Les pages l'interrogent pour savoir s'il faut se rafraîchir.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class DataVersion
{
    private const string KEY = 'chantier.data_version';

    private bool $dirty = false;

    public function __construct(private readonly CacheItemPoolInterface $cacheApp)
    {
    }

    public function get(): string
    {
        $item = $this->cacheApp->getItem(self::KEY);

        return $item->isHit() ? (string) $item->get() : $this->bump();
    }

    public function bump(): string
    {
        $version = \sprintf('%.6f', microtime(true));
        $this->cacheApp->save($this->cacheApp->getItem(self::KEY)->set($version));

        return $version;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        $this->dirty = $this->dirty
            || [] !== $uow->getScheduledEntityInsertions()
            || [] !== $uow->getScheduledEntityUpdates()
            || [] !== $uow->getScheduledEntityDeletions()
            || [] !== $uow->getScheduledCollectionUpdates()
            || [] !== $uow->getScheduledCollectionDeletions();
    }

    public function postFlush(): void
    {
        if ($this->dirty) {
            $this->dirty = false;
            $this->bump();
        }
    }
}
