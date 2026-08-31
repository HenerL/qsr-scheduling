<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryResultHelperV2;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftTemplates\ListShiftTemplatesRequest;
use App\Http\Requests\ShiftTemplates\SaveShiftTemplateRequest;
use App\Services\PaginationService;
use App\Services\ShiftTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftTemplateController extends Controller
{
    public function __construct(private readonly ShiftTemplateService $service)
    {
    }

    public function index(ListShiftTemplatesRequest $request): JsonResponse
    {
        $result = $this->service->list(
            $request->user(),
            PaginationService::params($request, ['is_active', 'applies_to']),
        );

        return QueryResultHelperV2::onSuccessGet($result['rows'], meta: $result['meta']);
    }

    public function store(SaveShiftTemplateRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessCreate(
            $this->service->create($request->user(), $request->validated()),
            'Shift template created.',
        );
    }

    public function update(SaveShiftTemplateRequest $request, int $templateId): JsonResponse
    {
        return QueryResultHelperV2::onSuccessUpdate(
            $this->service->update($request->user(), $templateId, $request->validated()),
            'Shift template updated.',
        );
    }

    public function destroy(Request $request, int $templateId): JsonResponse
    {
        $this->service->deactivate($request->user(), $templateId);

        return QueryResultHelperV2::onSuccessDelete('Shift template deactivated.');
    }

    public function seedDefaults(Request $request): JsonResponse
    {
        $created = $this->service->seedDefaults($request->user());

        return QueryResultHelperV2::onSuccessCreate(
            ['created' => $created],
            $created > 0
                ? "Added {$created} default shift template(s)."
                : 'All default shift templates already exist.',
        );
    }
}
