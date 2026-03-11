<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $announcements = Announcement::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.announcements.index', compact('announcements'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::orderBy('name')->get();
        return view('admin.announcements.create', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
            'channel' => 'required|in:sms,whatsapp,email,push',
            'target_type' => 'required|in:all,specific',
            'user_id' => 'nullable|exists:users,id|required_if:target_type,specific',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        try {
            // Déterminer le statut
            if ($request->filled('scheduled_at')) {
                $validated['status'] = 'scheduled';
            } else {
                $validated['status'] = 'draft';
            }

            $announcement = Announcement::create($validated);

            return redirect()->route('admin.announcements.index')
                ->with('success', 'Annonce créée avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la création: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Announcement $announcement)
    {
        $announcement->load('user');
        return view('admin.announcements.show', compact('announcement'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Announcement $announcement)
    {
        $users = User::orderBy('name')->get();
        return view('admin.announcements.edit', compact('announcement', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Announcement $announcement)
    {
        // Ne pas permettre la modification d'une annonce déjà envoyée
        if ($announcement->status === 'sent') {
            return redirect()->back()
                ->with('error', 'Impossible de modifier une annonce déjà envoyée');
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
            'channel' => 'required|in:sms,whatsapp,email,push',
            'target_type' => 'required|in:all,specific',
            'user_id' => 'nullable|exists:users,id|required_if:target_type,specific',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        try {
            // Déterminer le statut
            if ($request->filled('scheduled_at')) {
                $validated['status'] = 'scheduled';
            } else {
                $validated['status'] = 'draft';
            }

            $announcement->update($validated);

            return redirect()->route('admin.announcements.index')
                ->with('success', 'Annonce mise à jour avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Announcement $announcement)
    {
        try {
            $announcement->delete();

            return redirect()->route('admin.announcements.index')
                ->with('success', 'Annonce supprimée avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }
    }

    /**
     * Send the announcement immediately.
     */
    public function send(Announcement $announcement)
    {
        // Ne pas permettre l'envoi d'une annonce déjà envoyée
        if ($announcement->status === 'sent') {
            return redirect()->back()
                ->with('error', 'Cette annonce a déjà été envoyée');
        }

        try {
            // TODO: Implémenter la logique d'envoi selon le canal
            // Pour l'instant, on marque simplement comme envoyé

            // Calculer le nombre de destinataires
            $recipientCount = 0;
            if ($announcement->target_type === 'all') {
                $recipientCount = User::count();
            } else {
                $recipientCount = 1;
            }

            $announcement->update([
                'status' => 'sent',
                'sent_at' => now(),
                'sent_count' => $recipientCount,
                'failed_count' => 0,
            ]);

            return redirect()->route('admin.announcements.index')
                ->with('success', 'Annonce envoyée avec succès à ' . $recipientCount . ' utilisateur(s)');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de l\'envoi: ' . $e->getMessage());
        }
    }
}
