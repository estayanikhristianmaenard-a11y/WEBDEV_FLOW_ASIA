<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamCalendarRequest;
use App\Services\TeamCalendarService;
use Illuminate\Http\JsonResponse;

class TeamCalendarController extends Controller
{
    public function __invoke(TeamCalendarRequest $request, TeamCalendarService $service): JsonResponse
    {
        $input = $request->validated();

        return response()->json($service->build(
            $request->user(), $input['month'], isset($input['department_id']) ? (int) $input['department_id'] : null,
        ));
    }
}
