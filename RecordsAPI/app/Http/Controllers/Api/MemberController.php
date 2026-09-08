<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignMemberRolesRequest;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Http\Responses\APIResponse;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use APIResponse;

    public function __construct(
        protected MemberService $memberService,
    ) {
        $this->middleware('role:admin|superadmin,logto')->only(
            'store', 'update', 'destroy', 'assignRoles', 'removeRole',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $members = $this->memberService->list($request->only(['search', 'role', 'per_page']));

        return $this->success(
            MemberResource::collection($members),
            'Members retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $member = $this->memberService->find($id);

        if (! $member) {
            return $this->error('Member not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new MemberResource($member),
            'Member retrieved successfully'
        );
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $member = $this->memberService->create(
            $request->safe()->except('roles'),
            $request->safe()->input('roles', []),
        );

        return $this->success(
            new MemberResource($member),
            'Member created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateMemberRequest $request, int $id): JsonResponse
    {
        $member = $this->memberService->find($id);

        if (! $member) {
            return $this->error('Member not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $data = $request->safe()->except('roles');

        if ($request->has('roles')) {
            $this->memberService->assignRoles($member, $request->safe()->input('roles'));
        }

        $this->memberService->update($member, $data);

        return $this->success(
            new MemberResource($member->refresh()),
            'Member updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $member = $this->memberService->find($id);

        if (! $member) {
            return $this->error('Member not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->memberService->delete($member);

        return $this->success(null, 'Member deleted successfully');
    }

    public function assignRoles(AssignMemberRolesRequest $request, int $id): JsonResponse
    {
        $member = $this->memberService->find($id);

        if (! $member) {
            return $this->error('Member not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->memberService->assignRoles($member, $request->validated('roles'));

        return $this->success(
            new MemberResource($member->refresh()),
            'Roles assigned successfully'
        );
    }

    public function removeRole(Request $request, int $id, string $role): JsonResponse
    {
        $member = $this->memberService->find($id);

        if (! $member) {
            return $this->error('Member not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->memberService->removeRole($member, $role);

        return $this->success(
            new MemberResource($member->refresh()),
            'Role removed successfully'
        );
    }
}
