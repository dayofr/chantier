<?php

namespace App\Activity;

use App\Entity\Activity;
use App\Entity\Project;
use App\Enum\ActivityType;
use App\Repository\ActivityRepository;

/**
 * Décisions d'un projet rangées par initiative et par epic, pour la vue projet.
 *
 * - epic : décisions posées sur l'epic et sur ses tickets ;
 * - initiative (direct) : décisions posées sur l'initiative elle-même ;
 * - initiative (total) : tout ce qui précède pour l'initiative et ses epics.
 */
final readonly class DecisionIndex
{
    public function __construct(private ActivityRepository $activities)
    {
    }

    /**
     * @return array{
     *     epics: array<string, list<Activity>>,
     *     initiatives: array<string, list<Activity>>,
     *     initiativeTotals: array<string, int>
     * }
     */
    public function for(Project $project): array
    {
        $epicInitiative = [];
        $initiativeKeys = [];
        foreach ($project->getInitiatives() as $initiative) {
            $initiativeKeys[$initiative->getKey()] = true;
            foreach ($initiative->getEpics() as $epic) {
                $epicInitiative[$epic->getKey()] = $initiative->getKey();
            }
        }

        $index = ['epics' => [], 'initiatives' => [], 'initiativeTotals' => []];
        $decisions = $this->activities->findBy(['project' => $project, 'type' => ActivityType::Decision], ['id' => 'ASC']);

        foreach ($decisions as $decision) {
            $subject = $decision->getSubjectKey();
            $epicKey = $decision->getTicket()?->getEpic()?->getKey() ?? (isset($epicInitiative[$subject]) ? $subject : null);

            if (null !== $epicKey) {
                $index['epics'][$epicKey][] = $decision;
                $initiativeKey = $epicInitiative[$epicKey] ?? null;
            } elseif (isset($initiativeKeys[$subject])) {
                $index['initiatives'][$subject][] = $decision;
                $initiativeKey = $subject;
            } else {
                // Décision sur le projet ou sur un ticket sans epic.
                continue;
            }

            if (null !== $initiativeKey) {
                $index['initiativeTotals'][$initiativeKey] = ($index['initiativeTotals'][$initiativeKey] ?? 0) + 1;
            }
        }

        return $index;
    }
}
