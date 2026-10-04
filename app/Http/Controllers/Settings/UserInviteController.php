<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserInviteRequest;
use App\Models\UserInvite;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserInviteController extends Controller
{
    public function index(Request $request): View
    {
        $invites = UserInvite::query()
            ->where('invited_by', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return view('settings.invites', [
            'invites' => $invites,
        ]);
    }

    public function store(StoreUserInviteRequest $request, AuditLogger $audit): RedirectResponse
    {
        $host = $request->user();
        $email = strtolower($request->string('email')->toString());

        $issued = UserInvite::issue($host, $email);

        $audit->log($host, 'invite_created', $request);

        return redirect()
            ->route('settings.invites')
            ->with('invite_url', $issued['url']);
    }

    public function destroy(Request $request, UserInvite $userInvite, AuditLogger $audit): RedirectResponse
    {
        if ($userInvite->invited_by !== $request->user()->id) {
            abort(404);
        }

        if ($userInvite->accepted_at !== null) {
            return back()->withErrors(['invite' => 'This invite was already accepted.']);
        }

        if ($userInvite->revoked_at !== null) {
            $userInvite->delete();

            $audit->log($request->user(), 'invite_deleted', $request);

            return back()->with('status', 'Invite removed.');
        }

        if (! $userInvite->isPending()) {
            return back()->withErrors(['invite' => 'This invite cannot be removed.']);
        }

        $userInvite->forceFill(['revoked_at' => now()])->save();

        $audit->log($request->user(), 'invite_revoked', $request);

        return back()->with('status', 'Invite revoked.');
    }

    public function link(Request $request, UserInvite $userInvite, AuditLogger $audit): JsonResponse
    {
        if ($userInvite->invited_by !== $request->user()->id) {
            abort(404);
        }

        if (! $userInvite->isPending()) {
            return response()->json([
                'message' => 'This invite is no longer active.',
            ], 422);
        }

        $plainToken = $userInvite->rotateToken();

        $audit->log($request->user(), 'invite_link_viewed', $request);

        return response()->json([
            'url' => $userInvite->urlForPlainToken($plainToken),
        ]);
    }
}
