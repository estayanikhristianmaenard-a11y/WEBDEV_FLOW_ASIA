<?php

namespace FlowAsia\Module4\Services;

use FlowAsia\Module4\Contracts\CompanyEventSource;
use FlowAsia\Module4\Models\CompanyEvent;
use Illuminate\Support\Collection;

class DatabaseCompanyEventSource implements CompanyEventSource
{
    public function between(string $from, string $to): Collection
    {
        return CompanyEvent::query()->overlapping($from, $to)->orderBy('starts_on')->orderBy('id')
            ->get()->map(fn (CompanyEvent $event): array => $event->toCalendarEvent());
    }

    public function paginate(int $page): array
    {
        return CompanyEvent::query()->orderBy('starts_on')->orderBy('id')->paginate(20, ['*'], 'page', $page)->toArray();
    }

    public function create(array $attributes, int|string $createdBy): array
    {
        $event = new CompanyEvent($attributes);
        $event->created_by = $createdBy;
        $event->save();

        return $event->toArray();
    }

    public function update(int $eventId, array $attributes): ?array
    {
        $event = CompanyEvent::find($eventId);
        if (! $event) {
            return null;
        }
        $event->update($attributes);

        return $event->toArray();
    }

    public function delete(int $eventId): bool
    {
        return CompanyEvent::whereKey($eventId)->delete() > 0;
    }
}
