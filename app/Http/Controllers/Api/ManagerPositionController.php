<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryResultHelperV2;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManagerPositions\ListManagerPositionsRequest;
use App\Http\Requests\ManagerPositions\SaveManagerPositionRequest;
use App\Services\ManagerPositionService;
use App\Services\PaginationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerPositionController extends Controller
{
    public function __construct(private readonly ManagerPositionService $service)
    {
    }

    public function index(ListManagerPositionsRequest $request): JsonResponse
    {
        $result = $this->service->list($request->user(), PaginationService::params($request, ['is_active']));

        return QueryResultHelperV2::onSuccessGet($result['rows'], meta: $result['meta']);
    }

    public function store(SaveManagerPositionRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessCreate(
            $this->service->create($request->user(), $request->validated()),
            'Position created.',
        );
    }

    public function update(SaveManagerPositionRequest $request, int $positionId): JsonResponse
    {
        return QueryResultHelperV2::onSuccessUpdate(
            $this->service->update($request->user(), $positionId, $request->validated()),
            'Position updated.',
        );
    }

    public function destroy(Request $request, int $positionId): JsonResponse
    {
        $this->service->deactivate($request->user(), $positionId);

        return QueryResultHelperV2::onSuccessDelete('Position deactivated.');
    }

    public function seedDefaults(Request $request): JsonResponse
    {
        $created = $this->service->seedDefaults($request->user());

        return QueryResultHelperV2::onSuccessCreate(
            ['created' => $created],
            $created > 0
                ? "Added {$created} default position(s)."
                : 'All default positions already exist.',
        );
    }
}
