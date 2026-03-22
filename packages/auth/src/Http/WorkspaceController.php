<?php

declare(strict_types=1);

namespace Lattice\Auth\Http;

use Lattice\Auth\Http\Dto\CreateWorkspaceDto;
use Lattice\Auth\Http\Dto\InviteMemberDto;
use Lattice\Auth\Http\Dto\UpdateMemberRoleDto;
use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Auth\Models\User;
use Lattice\Auth\Models\Workspace;
use Lattice\Auth\Models\WorkspaceInvitation;
use Lattice\Auth\Principal;
use Lattice\Http\Response;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;

#[Controller('/api/workspaces')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
final class WorkspaceController
{
    /**
     * List all workspaces the authenticated user is a member of.
     */
    #[Get('/')]
    public function index(#[CurrentUser] Principal $principal): Response
    {
        $user = User::findOrFail($principal->getId());

        $workspaces = $user->workspaces()->get()->map(fn (Workspace $ws) => [
            'id' => $ws->id,
            'name' => $ws->name,
            'slug' => $ws->slug,
            'owner_id' => $ws->owner_id,
            'logo_url' => $ws->logo_url,
            'role' => $ws->pivot->role ?? null,
            'created_at' => $ws->created_at?->toIso8601String(),
        ]);

        return Response::json(['data' => $workspaces]);
    }

    /**
     * Create a new workspace. The authenticated user becomes the owner.
     */
    #[Post('/')]
    public function create(
        #[CurrentUser] Principal $principal,
        #[Body] CreateWorkspaceDto $dto,
    ): Response {
        $user = User::findOrFail($principal->getId());

        $workspace = Workspace::create([
            'name' => $dto->name,
            'slug' => $dto->slug ?? $this->generateSlug($dto->name),
            'owner_id' => $user->id,
            'settings' => $dto->settings,
            'logo_url' => $dto->logo_url,
        ]);

        // Owner is automatically the first member
        $workspace->addMember($user, 'owner');

        return Response::json([
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'owner_id' => $workspace->owner_id,
                'logo_url' => $workspace->logo_url,
                'settings' => $workspace->settings,
                'created_at' => $workspace->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get workspace detail by ID.
     */
    #[Get('/:id')]
    public function show(#[Param] int $id, #[CurrentUser] Principal $principal): Response
    {
        $workspace = Workspace::findOrFail($id);
        $user = User::findOrFail($principal->getId());

        if (!$workspace->hasMember($user)) {
            return Response::error('Not a member of this workspace', 403);
        }

        return Response::json([
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'owner_id' => $workspace->owner_id,
                'logo_url' => $workspace->logo_url,
                'settings' => $workspace->settings,
                'member_count' => $workspace->members()->count(),
                'your_role' => $workspace->getMemberRole($user),
                'created_at' => $workspace->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Send a workspace invitation.
     */
    #[Post('/:id/invitations')]
    public function invite(
        #[Param] int $id,
        #[CurrentUser] Principal $principal,
        #[Body] InviteMemberDto $dto,
    ): Response {
        $workspace = Workspace::findOrFail($id);
        $user = User::findOrFail($principal->getId());

        // Only owner or admin can invite
        $role = $workspace->getMemberRole($user);
        if (!in_array($role, ['owner', 'admin'], true)) {
            return Response::error('Insufficient permissions to invite members', 403);
        }

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $dto->email,
            'role' => $dto->role,
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        return Response::json([
            'data' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Accept a workspace invitation by token.
     */
    #[Post('/invitations/:token/accept')]
    public function acceptInvitation(
        #[Param] string $token,
        #[CurrentUser] Principal $principal,
    ): Response {
        $invitation = WorkspaceInvitation::where('token', $token)->first();

        if ($invitation === null) {
            return Response::error('Invitation not found', 404);
        }

        if ($invitation->isAccepted()) {
            return Response::error('Invitation already accepted', 409);
        }

        if ($invitation->isExpired()) {
            return Response::error('Invitation has expired', 410);
        }

        $user = User::findOrFail($principal->getId());
        $workspace = $invitation->workspace;

        if ($workspace->hasMember($user)) {
            return Response::error('Already a member of this workspace', 409);
        }

        // Add user as member and mark invitation accepted
        $workspace->addMember($user, $invitation->role, $invitation->invited_by);
        $invitation->markAccepted();

        return Response::json([
            'data' => [
                'workspace_id' => $workspace->id,
                'workspace_name' => $workspace->name,
                'role' => $invitation->role,
            ],
        ]);
    }

    /**
     * List members of a workspace.
     */
    #[Get('/:id/members')]
    public function members(#[Param] int $id, #[CurrentUser] Principal $principal): Response
    {
        $workspace = Workspace::findOrFail($id);
        $user = User::findOrFail($principal->getId());

        if (!$workspace->hasMember($user)) {
            return Response::error('Not a member of this workspace', 403);
        }

        $members = $workspace->members()->get()->map(fn (User $member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->pivot->role,
            'joined_at' => $member->pivot->joined_at,
        ]);

        return Response::json(['data' => $members]);
    }

    /**
     * Update a member's role within the workspace.
     */
    #[Put('/:id/members/:userId')]
    public function updateMemberRole(
        #[Param] int $id,
        #[Param] int $userId,
        #[CurrentUser] Principal $principal,
        #[Body] UpdateMemberRoleDto $dto,
    ): Response {
        $workspace = Workspace::findOrFail($id);
        $currentUser = User::findOrFail($principal->getId());

        // Only owner can change roles
        if (!$workspace->isOwner($currentUser)) {
            return Response::error('Only the workspace owner can change roles', 403);
        }

        $targetUser = User::findOrFail($userId);

        if (!$workspace->hasMember($targetUser)) {
            return Response::error('User is not a member of this workspace', 404);
        }

        // Cannot change the owner's own role
        if ($workspace->isOwner($targetUser)) {
            return Response::error('Cannot change the owner role', 400);
        }

        $workspace->updateMemberRole($targetUser, $dto->role);

        return Response::json([
            'data' => [
                'user_id' => $targetUser->id,
                'role' => $dto->role,
            ],
        ]);
    }

    /**
     * Remove a member from the workspace.
     */
    #[Delete('/:id/members/:userId')]
    public function removeMember(
        #[Param] int $id,
        #[Param] int $userId,
        #[CurrentUser] Principal $principal,
    ): Response {
        $workspace = Workspace::findOrFail($id);
        $currentUser = User::findOrFail($principal->getId());

        // Owner or admin can remove members, or user can remove themselves
        $role = $workspace->getMemberRole($currentUser);
        $isSelfRemoval = $currentUser->id === $userId;

        if (!$isSelfRemoval && !in_array($role, ['owner', 'admin'], true)) {
            return Response::error('Insufficient permissions to remove members', 403);
        }

        $targetUser = User::findOrFail($userId);

        if (!$workspace->hasMember($targetUser)) {
            return Response::error('User is not a member of this workspace', 404);
        }

        // Cannot remove the owner
        if ($workspace->isOwner($targetUser)) {
            return Response::error('Cannot remove the workspace owner', 400);
        }

        $workspace->removeMember($targetUser);

        return Response::noContent();
    }

    /**
     * Generate a URL-safe slug from a workspace name.
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
