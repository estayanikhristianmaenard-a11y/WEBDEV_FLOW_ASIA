<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyEventFeedRequest;
use App\Http\Requests\CompanyEventRequest;
use App\Models\CompanyEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CompanyEventController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CompanyEvent::query()->orderBy('starts_on')->orderBy('id')->paginate(20));
    }

    public function store(CompanyEventRequest $request): JsonResponse
    {
        $event = new CompanyEvent($request->validated());
        $event->created_by = $request->user()->id;
        $event->save();

        return response()->json(['data' => $event], 201);
    }

    public function update(CompanyEventRequest $request, CompanyEvent $event): JsonResponse
    {
        $event->update($request->validated());

        return response()->json(['data' => $event]);
    }

    public function destroy(CompanyEvent $event): Response
    {
        $event->delete();

        return response()->noContent();
    }

    public function feed(CompanyEventFeedRequest $request): JsonResponse
    {
        $bounds = $request->validated();
        $from = CarbonImmutable::parse($bounds['start'])->setTimezone(config('app.timezone'))->startOfDay();
        $exclusiveEnd = CarbonImmutable::parse($bounds['end'])->setTimezone(config('app.timezone'))->startOfDay();

        if ($exclusiveEnd->lte($from)) {
            return response()->json(['message' => 'The end must be a later calendar date.', 'errors' => ['end' => ['The end must be a later calendar date.']]], 422);
        }

        $events = CompanyEvent::query()
            ->overlapping($from->toDateString(), $exclusiveEnd->subDay()->toDateString())
            ->orderBy('starts_on')->orderBy('id')
            ->get()->map(fn (CompanyEvent $event) => $event->toCalendarEvent());

        return response()->json($events);
    }
}
