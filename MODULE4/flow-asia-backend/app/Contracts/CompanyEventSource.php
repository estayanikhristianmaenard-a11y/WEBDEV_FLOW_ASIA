<?php

namespace FlowAsia\Module4\Contracts;

use Illuminate\Support\Collection;

interface CompanyEventSource
{
    /** FullCalendar event records, with an exclusive end, for inclusive bounds. */
    public function between(string $from, string $to): Collection;

    /** Paginated management records with the existing M4 event field names. */
    public function paginate(int $page): array;

    public function create(array $attributes, int|string $createdBy): array;

    /** Return null for a missing event. */
    public function update(int $eventId, array $attributes): ?array;

    /** Return false for a missing event. */
    public function delete(int $eventId): bool;
}
