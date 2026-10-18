<?php

namespace FlowAsia\Module4\Http\Controllers;

use FlowAsia\Module4\Http\Requests\TeamCalendarRequest;
use FlowAsia\Module4\Services\TeamCalendarService;
use Illuminate\Http\JsonResponse;

class TeamCalendarController extends Controller
{
    public function __invoke(TeamCalendarRequest $request, TeamCalendarService $service): JsonResponse
    {
        $input = $request->validated();

        return response()->json($service->build(
            $request->user(), $input['month'], isset($input['department_id']) ? (int) $input['department_id'] : null,
        ))->header('Cache-Control', 'private, no-store');
    }
}
