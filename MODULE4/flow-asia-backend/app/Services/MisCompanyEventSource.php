<?php

namespace FlowAsia\Module4\Services;

use Carbon\CarbonImmutable;
use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use FlowAsia\Module4\Contracts\CompanyEventSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MisCompanyEventSource implements CompanyEventSource
{
    public function between(string $from, string $to): Collection
    {
        return $this->query()->where('start_datetime', '<=', $to.' 23:59:59')
            ->whereRaw('COALESCE(end_datetime, start_datetime) >= ?', [$from.' 00:00:00'])
            ->orderBy('start_datetime')->orderBy('company_event_id')->get()->map(function (object $event): array {
                $start = CarbonImmutable::parse($event->start_datetime, config('module4.timezone'));
                $end = CarbonImmutable::parse($event->end_datetime ?? $event->start_datetime, config('module4.timezone'));
                $exclusiveEnd = $event->is_all_day || ! $event->end_datetime || ! $end->isStartOfDay() || $end->lte($start)
                    ? $end->addDay() : $end;

                return [
                    'id' => 'company-event-'.$event->company_event_id, 'title' => $event->event_title,
                    'start' => $start->toDateString(), 'end' => $exclusiveEnd->toDateString(),
                    'allDay' => true,
                    'classNames' => [config('module4_calendar.company_event.class')],
                    'extendedProps' => [
                        'kind' => 'company_event', 'eventId' => (int) $event->company_event_id,
                        'type' => $event->event_type, 'description' => $event->description,
                        'sourceAllDay' => (bool) $event->is_all_day,
                        'marker' => config('module4_calendar.company_event.marker'),
                    ],
                ];
            });
    }

    public function paginate(int $page): array
    {
        $paginator = $this->query()->where('is_all_day', true)->orderBy('start_datetime')->orderBy('company_event_id')
            ->paginate(20, ['*'], 'page', $page);
        $paginator->setCollection($paginator->getCollection()->map(fn (object $event): array => $this->record($event)));

        return $paginator->toArray();
    }

    public function create(array $attributes, int|string $createdBy): array
    {
        $this->requireLinkedUser();
        abort_unless((string) $createdBy === (string) Auth::user()->getAuthIdentifier(), 403);
        $createdBy = $this->actorId();
        $id = DB::connection(config('module4.mis_connection'))->table('company_events')->insertGetId([
            ...$this->attributes($attributes), 'created_by' => $createdBy, 'is_all_day' => true,
            'status' => 'ACTIVE', 'created_at' => now(config('module4.timezone'))->format('Y-m-d H:i:s'),
        ], 'company_event_id');

        return $this->record($this->query()->where('company_event_id', $id)->first());
    }

    public function update(int $eventId, array $attributes): ?array
    {
        $this->requireLinkedUser();
        $event = $this->query()->where('company_event_id', $eventId)->first();
        if (! $event) {
            return null;
        }
        $this->requireAllDay($event);
        $this->query()->where('company_event_id', $eventId)->update($this->attributes($attributes));

        return $this->record($this->query()->where('company_event_id', $eventId)->first());
    }

    public function delete(int $eventId): bool
    {
        $this->requireLinkedUser();
        $event = $this->query()->where('company_event_id', $eventId)->first();
        if (! $event) {
            return false;
        }
        $this->requireAllDay($event);

        return $this->query()->where('company_event_id', $eventId)->update([
            'status' => 'CANCELLED', 'updated_at' => now(config('module4.timezone'))->format('Y-m-d H:i:s'),
        ]) > 0;
    }

    private function query(): Builder
    {
        return DB::connection(config('module4.mis_connection'))->table('company_events')->whereRaw('UPPER(status) = ?', ['ACTIVE']);
    }

    private function actorId(): int|string
    {
        return Auth::user() ? app(CalendarIdentitySource::class)->forUser(Auth::user())['id'] : 0;
    }

    private function requireLinkedUser(): void
    {
        abort_unless($this->actorId() > 0, 403, 'A linked active MySQL account is required to manage company events.');
    }

    private function requireAllDay(object $event): void
    {
        if (! $event->is_all_day) {
            throw ValidationException::withMessages(['event' => 'Edit timed events in their owning module to preserve their time and location.']);
        }
    }

    private function attributes(array $attributes): array
    {
        return [
            'event_title' => $attributes['title'], 'event_type' => $attributes['type'], 'description' => $attributes['description'] ?? null,
            'start_datetime' => $attributes['starts_on'].' 00:00:00', 'end_datetime' => $attributes['ends_on'].' 23:59:59',
            'updated_at' => now(config('module4.timezone'))->format('Y-m-d H:i:s'),
        ];
    }

    private function record(object $event): array
    {
        return [
            'id' => (int) $event->company_event_id, 'title' => $event->event_title,
            'type' => $event->event_type, 'description' => $event->description,
            'starts_on' => substr($event->start_datetime, 0, 10), 'ends_on' => substr($event->end_datetime ?? $event->start_datetime, 0, 10),
            'created_by' => (int) $event->created_by,
        ];
    }
}
