<?php

namespace FlowAsia\Module4\Http\Controllers;

use Carbon\CarbonImmutable;
use FlowAsia\Module4\Contracts\CompanyEventSource;
use FlowAsia\Module4\Http\Requests\CompanyEventFeedRequest;
use FlowAsia\Module4\Http\Requests\CompanyEventRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompanyEventController extends Controller
{
    public function __construct(private CompanyEventSource $events) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return response()->json($this->events->paginate((int) $request->input('page', 1)))->header('Cache-Control', 'private, no-store');
    }

    public function store(CompanyEventRequest $request): JsonResponse
    {
        $event = $this->events->create($request->validated(), $request->user()->getAuthIdentifier());

        return response()->json(['data' => $event], 201);
    }

    public function update(CompanyEventRequest $request, int $event): JsonResponse
    {
        $event = $this->events->update($event, $request->validated());
        abort_if($event === null, 404);

        return response()->json(['data' => $event]);
    }

    public function destroy(int $event): Response
    {
        abort_unless($this->events->delete($event), 404);

        return response()->noContent();
    }

    public function feed(CompanyEventFeedRequest $request): JsonResponse
    {
        $bounds = $request->validated();
        $from = CarbonImmutable::parse($bounds['start'])->setTimezone(config('module4.timezone'))->startOfDay();
        $exclusiveEnd = CarbonImmutable::parse($bounds['end'])->setTimezone(config('module4.timezone'))->startOfDay();

        if ($exclusiveEnd->lte($from)) {
            return response()->json(['message' => 'The end must be a later calendar date.', 'errors' => ['end' => ['The end must be a later calendar date.']]], 422);
        }

        $events = $this->events->between($from->toDateString(), $exclusiveEnd->subDay()->toDateString());

        return response()->json($events)->header('Cache-Control', 'private, no-store');
    }
}
