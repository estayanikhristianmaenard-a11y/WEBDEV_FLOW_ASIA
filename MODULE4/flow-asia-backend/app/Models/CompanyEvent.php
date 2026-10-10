<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CompanyEvent extends Model
{
    protected $fillable = ['title', 'type', 'description', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    /** Inclusive stored dates overlap an inclusive requested date range. */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from);
    }

    public function toCalendarEvent(): array
    {
        return [
            'id' => 'company-event-'.$this->id,
            'title' => $this->title,
            'start' => $this->starts_on->toDateString(),
            'end' => $this->ends_on->addDay()->toDateString(),
            'allDay' => true,
            'classNames' => [config('calendar.company_event.class')],
            'extendedProps' => [
                'kind' => 'company_event',
                'eventId' => $this->id,
                'type' => $this->type,
                'description' => $this->description,
                'marker' => config('calendar.company_event.marker'),
            ],
        ];
    }
}
