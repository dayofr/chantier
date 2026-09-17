<?php

namespace App\Mcp\Processor;

use App\Activity\ActorContext;
use App\Entity\Activity;
use App\Enum\ActivityType;
use App\Mcp\ToolError;
use App\Mcp\Tool\LogActivity;

/** @extends AbstractToolProcessor<LogActivity> */
final class LogActivityProcessor extends AbstractToolProcessor
{
    private const array ALLOWED = [ActivityType::Note, ActivityType::Decision, ActivityType::Progress, ActivityType::Blocker, ActivityType::Commit, ActivityType::Test];

    public function __construct(private readonly ActorContext $actor)
    {
    }

    protected function handle(object $data): array
    {
        $type = $this->enum(ActivityType::class, $data->type, 'type') ?? ActivityType::Note;
        if (!\in_array($type, self::ALLOWED, true)) {
            throw new ToolError(\sprintf('type : "%s" est réservé au journal automatique.', $type->value));
        }

        $subject = trim((string) ($data->subject ?: $data->ticket));
        if ('' !== $subject) {
            $activity = $this->activityOn($subject, $type, $data->message);
        } elseif (null !== $data->project && '' !== $data->project) {
            $activity = $this->actor->stamp(new Activity($this->lookup->project($data->project), $type, $data->message));
        } else {
            throw new ToolError('Préciser subject (ticket, epic ou initiative) ou project.');
        }
        $this->save($activity);

        return $this->presenter->activity($activity);
    }
}
