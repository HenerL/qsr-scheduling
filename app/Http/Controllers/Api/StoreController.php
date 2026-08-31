<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryResultHelperV2;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\OperatingHoursRequest;
use App\Http\Requests\Store\SaveStoreRequest;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(private readonly StoreService $storeService)
    {
    }

    public function create(SaveStoreRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessCreate(
            $this->storeService->createForOwner($request->user(), $request->validated()),
            'Store created. Operating hours seeded for the week.'
        );
    }

    public function show(Request $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessGet($this->storeService->getProfile($request->user()));
    }

    public function update(SaveStoreRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessUpdate(
            $this->storeService->updateProfile($request->user(), $request->validated())
        );
    }

    public function showHours(Request $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessGet($this->storeService->getOperatingHours($request->user()));
    }

    public function updateHours(OperatingHoursRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessUpdate(
            $this->storeService->replaceOperatingHours($request->user(), $request->input('days')),
            'Operating hours saved.'
        );
    }
}
