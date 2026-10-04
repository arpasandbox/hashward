<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInviteRequest;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcceptInviteController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('login')
                ->with('status', 'Sign out before accepting an invite.');
        }

        $invite = $this->resolvePendingInvite($token);

        if ($invite === null) {
            return view('auth.invite-invalid');
        }

        return view('auth.accept-invite', [
            'token' => $token,
            'email' => $invite->email,
        ]);
    }

    public function store(AcceptInviteRequest $request, string $token, AuditLogger $audit): RedirectResponse|View
    {
        if ($request->user()) {
            return redirect()->route('login')
                ->with('status', 'Sign out before accepting an invite.');
        }

        $invite = $this->resolvePendingInvite($token);

        if ($invite === null) {
            $audit->log(null, 'invite_failed', $request);

            return view('auth.invite-invalid');
        }

        $host = $invite->inviter;

        if ($host === null || ! $host->isHost()) {
            $audit->log(null, 'invite_failed', $request);

            return view('auth.invite-invalid');
        }

        if (User::query()->where('email', $invite->email)->exists()) {
            $audit->log(null, 'invite_failed', $request);

            return view('auth.invite-invalid');
        }

        $invitee = User::createInvitee([
            'name' => $request->string('name')->toString(),
            'email' => $invite->email,
            'password' => $request->string('password')->toString(),
            'kdf_iterations' => 600000,
        ], $host);

        $invite->forceFill(['accepted_at' => now()])->save();

        $audit->log($invitee, 'invite_accepted', $request);

        return redirect()
            ->route('login')
            ->with('status', 'Account created. Sign in and complete two-factor and vault setup.');
    }

    private function resolvePendingInvite(string $token): ?UserInvite
    {
        if (strlen($token) < 32) {
            return null;
        }

        $invite = UserInvite::findByPlainToken($token);

        if ($invite === null || ! $invite->isPending()) {
            return null;
        }

        return $invite;
    }
}
