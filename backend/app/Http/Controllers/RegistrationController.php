<?php

namespace App\Http\Controllers;

use App\Services\CoreDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(private readonly CoreDataService $coreDataService)
    {
    }

    public function publicStatus(): JsonResponse
    {
        return response()->json($this->coreDataService->loadRegistrationPublicStatus());
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'loginCode' => ['required', 'string'],
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'gender' => ['required', 'in:male,female'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->coreDataService->createRegistrationRequest(
                $data['name'],
                $data['loginCode'],
                (int) $data['age'],
                $data['gender'],
                $data['answers'] ?? [],
            ),
            201,
        );
    }

    public function index(): JsonResponse
    {
        return response()->json($this->coreDataService->loadRegistrationDashboardData());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'isOpen' => ['required', 'boolean'],
        ]);

        $this->coreDataService->updateRegistrationSettings($data['isOpen']);

        return response()->json(status: 204);
    }

    public function updateFields(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fields' => ['present', 'array'],
            'fields.*.id' => ['nullable', 'string'],
            'fields.*.label' => ['required', 'string'],
            'fields.*.type' => ['required', 'in:text,select'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['nullable', 'string'],
        ]);

        return response()->json([
            'fields' => $this->coreDataService->updateRegistrationFormFields($data['fields']),
        ]);
    }

    public function accept(Request $request, string $requestId): JsonResponse
    {
        $data = $request->validate([
            'branchId' => ['nullable', 'string'],
        ]);

        return response()->json($this->coreDataService->acceptRegistrationRequest($requestId, $data['branchId'] ?? null));
    }

    public function reject(Request $request, string $requestId): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        return response()->json($this->coreDataService->rejectRegistrationRequest($requestId, (string) ($data['reason'] ?? '')));
    }

    public function markAccepted(string $requestId): JsonResponse
    {
        return response()->json($this->coreDataService->markRegistrationRequestAccepted($requestId));
    }
}
