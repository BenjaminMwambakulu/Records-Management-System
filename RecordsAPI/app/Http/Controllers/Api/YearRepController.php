<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Http\Responses\APIResponse;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YearRepController extends Controller
{
    use APIResponse;

    public function __construct(
        protected MemberService $memberService,
    ) {}

    protected function getScope(Request $request): array
    {
        $user = $request->user();

        if (! $user->academic_track || ! $user->study_year) {
            abort(403, 'Your account is not configured with an academic track and study year.');
        }

        return [
            'academic_track' => $user->academic_track,
            'study_year' => $user->study_year,
        ];
    }

    protected function applyScope(Request $request, $query): void
    {
        $scope = $this->getScope($request);
        $query->where('academic_track', $scope['academic_track'])
            ->where('study_year', $scope['study_year']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::query();
        $this->applyScope($request, $query);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('student_id', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $members = $query->with('roles')->latest()->paginate($request->input('per_page', 15));

        return $this->success(
            MemberResource::collection($members),
            'Students retrieved successfully'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $query = User::query();
        $this->applyScope($request, $query);

        $member = $query->find($id);

        if (! $member) {
            return $this->error('Student not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new MemberResource($member),
            'Student retrieved successfully'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $scope = $this->getScope($request);

        $validated = $request->validate([
            'student_id' => ['required', 'string', 'max:255', 'unique:users,student_id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'enrolled_year' => ['nullable', 'integer', 'min:1950', 'max:2200'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:255'],
        ]);

        $validated['academic_track'] = $scope['academic_track'];
        $validated['study_year'] = $scope['study_year'];

        $member = $this->memberService->create($validated);

        return $this->success(
            new MemberResource($member),
            'Student created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $scope = $this->getScope($request);

        $query = User::query();
        $this->applyScope($request, $query);
        $member = $query->find($id);

        if (! $member) {
            return $this->error('Student not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'student_id' => ['sometimes', 'string', 'max:255', 'unique:users,student_id,'.$member->id],
            'email' => ['sometimes', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$member->id],
            'enrolled_year' => ['sometimes', 'nullable', 'integer', 'min:1950', 'max:2200'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:255'],
        ]);

        $this->memberService->update($member, $validated);

        return $this->success(
            new MemberResource($member->refresh()),
            'Student updated successfully'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $query = User::query();
        $this->applyScope($request, $query);
        $member = $query->find($id);

        if (! $member) {
            return $this->error('Student not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->memberService->delete($member);

        return $this->success(null, 'Student deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $scope = $this->getScope($request);

        $total = User::where('academic_track', $scope['academic_track'])
            ->where('study_year', $scope['study_year'])
            ->count();

        return $this->success([
            'academic_track' => $scope['academic_track'],
            'study_year' => $scope['study_year'],
            'total_students' => $total,
        ], 'Stats retrieved successfully');
    }
}
