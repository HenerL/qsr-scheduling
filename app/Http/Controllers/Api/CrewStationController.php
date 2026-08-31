<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryResultHelperV2;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrewStations\ListCrewStationsRequest;
use App\Http\Requests\CrewStations\SaveCrewStationRequest;
use App\Services\CrewStationService;
use App\Services\PaginationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewStationController extends Controller
{
    public function __construct(private readonly CrewStationService $service)
    {
    }

    public function index(ListCrewStationsRequest $request): JsonResponse
    {
        $result = $this->service->list($request->user(), PaginationService::params($request, ['is_active']));

        return QueryResultHelperV2::onSuccessGet($result['rows'], meta: $result['meta']);
    }

    public function store(SaveCrewStationRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessCreate(
            $this->service->create($request->user(), $request->validated()),
            'Station created.',
        );
    }

    public function update(SaveCrewStationRequest $request, int $stationId): JsonResponse
    {
        return QueryResultHelperV2::onSuccessUpdate(
            $this->service->update($request->user(), $stationId, $request->validated()),
            'Station updated.',
        );
    }

    public function destroy(Request $request, int $stationId): JsonResponse
    {
        $this->service->deactivate($request->user(), $stationId);

        return QueryResultHelperV2::onSuccessDelete('Station deactivated.');
    }

    public function seedDefaults(Request $request): JsonResponse
    {
        $created = $this->service->seedDefaults($request->user());

        return QueryResultHelperV2::onSuccessCreate(
            ['created' => $created],
            $created > 0
                ? "Added {$created} default station(s)."
                : 'All default stations already exist.',
        );
    }
}
