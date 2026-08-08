<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminInviteCodeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = InviteCode::with(['generator:id,name', 'usedByUser:id,name']);

        if ($search = $request->input('search')) {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            if ($status === 'used') {
                $query->where('is_used', true);
            } elseif ($status === 'valid') {
                $query->valid();
            } elseif ($status === 'expired') {
                $query->expired()->where('is_used', false);
            }
        }

        $codes = $query->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/InviteCodes/Index', [
            'codes' => $codes,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:50'],
            'expiry_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $count = (int) $request->input('count');
        $expiryDays = (int) $request->input('expiry_days');

        for ($i = 0; $i < $count; $i++) {
            InviteCode::create([
                'generated_by' => $request->user()->id,
                'code' => strtoupper(Str::random(8)),
                'is_used' => false,
                'expires_at' => now()->addDays($expiryDays),
            ]);
        }

        return redirect()->back()->with('status', "Generated {$count} new invite code(s).");
    }

    public function destroy(string $id): RedirectResponse
    {
        $code = InviteCode::findOrFail($id);
        $code->delete();

        return redirect()->back()->with('status', 'Invite code deleted successfully.');
    }
}
