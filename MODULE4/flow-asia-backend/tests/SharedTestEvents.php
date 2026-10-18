<?php

namespace Tests;

use Carbon\CarbonImmutable;
use FlowAsia\Module4\Contracts\CompanyEventSource;
use Illuminate\Support\Collection;

class SharedTestEvents implements CompanyEventSource
{
    public array $records = [];

    public function between(string $from, string $to): Collection
    {
        return collect($this->records)->filter(fn (array $event): bool => $event['starts_on'] <= $to && $event['ends_on'] >= $from)
            ->map(fn (array $event): array => [
                'id' => 'company-event-'.$event['id'], 'title' => $event['title'],
                'start' => $event['starts_on'], 'end' => CarbonImmutable::parse($event['ends_on'])->addDay()->toDateString(),
                'allDay' => true, 'classNames' => ['company-event'],
                'extendedProps' => ['kind' => 'company_event', 'eventId' => $event['id'], 'type' => $event['type'], 'description' => $event['description'] ?? null],
            ])->values();
    }

    public function paginate(int $page): array
    {
        return ['data' => array_values($this->records), 'current_page' => $page];
    }

    public function create(array $attributes, int|string $createdBy): array
    {
        $id = count($this->records) + 1;

        return $this->records[$id] = ['id' => $id, 'created_by' => $createdBy, ...$attributes];
    }

    public function update(int $eventId, array $attributes): ?array
    {
        return isset($this->records[$eventId]) ? $this->records[$eventId] = array_replace($this->records[$eventId], $attributes) : null;
    }

    public function delete(int $eventId): bool
    {
        $exists = isset($this->records[$eventId]);
        unset($this->records[$eventId]);

        return $exists;
    }
}
