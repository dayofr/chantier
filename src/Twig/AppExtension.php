<?php

namespace App\Twig;

use App\Entity\Project;
use App\Enum\PlanStatus;
use App\Enum\ProjectStatus;
use App\Enum\TicketStatus;
use App\Repository\ProjectRepository;
use App\Service\TicketStats;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    private ?GithubFlavoredMarkdownConverter $markdown = null;

    /** @var list<Project>|null */
    private ?array $projects = null;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @return list<Project> projets de la barre latérale, archivés en dernier */
    #[AsTwigFunction('sidebar_projects')]
    public function sidebarProjects(): array
    {
        return $this->projects ??= $this->projectRepository->findForSidebar();
    }

    /** @param iterable<\App\Entity\Ticket> $tickets */
    #[AsTwigFunction('ticket_stats')]
    public function ticketStats(iterable $tickets): TicketStats
    {
        return TicketStats::of($tickets);
    }

    /** Ton de couleur d'un statut : done, progress, review, planned, blocked, muted. */
    #[AsTwigFunction('tone')]
    public function tone(TicketStatus|PlanStatus|ProjectStatus $status): string
    {
        return match ($status) {
            TicketStatus::Done, PlanStatus::Done, ProjectStatus::Done => 'done',
            TicketStatus::InProgress, PlanStatus::InProgress, ProjectStatus::Active => 'progress',
            TicketStatus::InReview, PlanStatus::InReview => 'review',
            TicketStatus::Todo, TicketStatus::Backlog, PlanStatus::Planned, ProjectStatus::Planning => 'planned',
            TicketStatus::Blocked => 'blocked',
            default => 'muted',
        };
    }

    /** Durée relative traduite : "il y a 5 minutes". */
    #[AsTwigFilter('ago')]
    public function ago(\DateTimeInterface $date): string
    {
        $seconds = time() - $date->getTimestamp();

        [$unit, $count] = match (true) {
            $seconds < 60 => [null, 0],
            $seconds < 3600 => ['minute', intdiv($seconds, 60)],
            $seconds < 86400 => ['hour', intdiv($seconds, 3600)],
            default => ['day', intdiv($seconds, 86400)],
        };

        return null === $unit
            ? $this->translator->trans('time.just_now')
            : $this->translator->trans('time.ago', ['unit' => $unit, 'count' => $count]);
    }

    /** Markdown en HTML, sans HTML brut ni liens dangereux. */
    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function markdown(?string $text): string
    {
        if (null === $text || '' === trim($text)) {
            return '';
        }

        $this->markdown ??= new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);

        return (string) $this->markdown->convert($text);
    }
}
