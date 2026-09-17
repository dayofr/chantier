<?php

namespace App\Twig;

use App\Entity\Project;
use App\Enum\PlanStatus;
use App\Live\DataVersion;
use App\Enum\ProjectStatus;
use App\Enum\TicketStatus;
use App\Activity\ActivityGrouper;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Service\ProjectAlerts;
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
        private readonly DataVersion $dataVersion,
        private readonly ProjectAlerts $alerts,
        private readonly ActivityRepository $activities,
        private readonly ActivityGrouper $grouper,
    ) {
    }

    /**
     * Entrées du journal prêtes à afficher, avec les suites automatiques regroupées.
     *
     * @param list<\App\Entity\Activity> $entries
     */
    #[AsTwigFunction('activity_items')]
    public function activityItems(array $entries, bool $grouped = true): array
    {
        return $grouped
            ? $this->grouper->group($entries)
            : array_map(static fn ($e) => ['kind' => 'entry', 'entry' => $e], $entries);
    }

    /** Id de la dernière entrée du journal, pour le compteur de nouveautés. */
    #[AsTwigFunction('latest_activity_id')]
    public function latestActivityId(): int
    {
        return $this->activities->latestId();
    }

    /** @return list<array<string, mixed>> */
    #[AsTwigFunction('project_alerts')]
    public function projectAlerts(Project $project): array
    {
        return $this->alerts->for($project);
    }

    #[AsTwigFunction('live_version')]
    public function liveVersion(): string
    {
        return $this->dataVersion->get();
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

    /**
     * Surligne les termes cherchés dans du HTML déjà sûr, hors balises.
     * Insensible à la casse et aux accents : "decision" surligne "Décision".
     *
     * @param list<string> $terms termes normalisés (SearchText::terms)
     */
    #[AsTwigFilter('highlight', isSafe: ['html'], preEscape: 'html')]
    public function highlight(string $html, array $terms): string
    {
        if ([] === $terms) {
            return $html;
        }

        $variants = ['a' => 'aàáâãäå', 'c' => 'cç', 'e' => 'eèéêë', 'i' => 'iìíîï', 'n' => 'nñ', 'o' => 'oòóôõöø', 'u' => 'uùúûü', 'y' => 'yýÿ'];
        $patterns = [];
        foreach ($terms as $term) {
            $pattern = '';
            foreach (mb_str_split($term) as $char) {
                $pattern .= isset($variants[$char]) ? '['.$variants[$char].']' : preg_quote($char, '/');
            }
            $patterns[] = $pattern;
        }
        usort($patterns, static fn ($a, $b) => \strlen($b) <=> \strlen($a));
        $regex = '/('.implode('|', $patterns).')/iu';

        // Découpe en balises et texte ; seul le texte est surligné.
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        foreach ($parts as $i => $part) {
            if ('' !== $part && '<' !== $part[0]) {
                $parts[$i] = preg_replace($regex, '<mark>$1</mark>', $part) ?? $part;
            }
        }

        return implode('', $parts);
    }

    /** Durée lisible : "45 min", "2 h 05 min". */
    #[AsTwigFilter('duration')]
    public function duration(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);

        return $this->translator->trans('time.duration', ['hours' => intdiv($minutes, 60), 'minutes' => \sprintf('%02d', $minutes % 60), 'total' => $minutes]);
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
