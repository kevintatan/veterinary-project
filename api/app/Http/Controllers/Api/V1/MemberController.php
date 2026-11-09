<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\InviteMember;
use App\Actions\Members\UpdateMember;
use App\Auth\ClinicRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\InviteMemberRequest;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\MemberResource;
use App\Models\ClinicMembership;
use App\Models\Invitation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MemberController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $members = ClinicMembership::with('user.branches')->get()->sortBy('user.name')->values();

        return MemberResource::collection($members);
    }

    public function update(UpdateMemberRequest $request, ClinicMembership $member, UpdateMember $updateMember): MemberResource
    {
        $updated = $updateMember($request->user(), $member, $request->validated());

        return new MemberResource($updated->load('user.branches'));
    }

    public function invitations(): AnonymousResourceCollection
    {
        $pending = Invitation::with('inviter')
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return InvitationResource::collection($pending);
    }

    public function invite(InviteMemberRequest $request, InviteMember $inviteMember): InvitationResource
    {
        $data = $request->validated();

        $invitation = $inviteMember(
            $request->user(),
            $request->attributes->get('clinic'),
            $data['email'],
            ClinicRole::from($data['role']),
            $data['branch_ids'] ?? null,
        );

        return new InvitationResource($invitation->load('inviter'));
    }

    public function revokeInvitation(Invitation $invitation): Response
    {
        $invitation->forceFill(['revoked_at' => now()])->save();

        return response()->noContent();
    }
}
