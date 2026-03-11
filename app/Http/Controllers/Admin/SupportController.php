<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportReply;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    /**
     * Afficher la liste des tickets
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user', 'admin'])->withCount('replies')->recent();

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->byPriority($request->priority);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->search . '%')
                  ->orWhere('ticket_number', 'like', '%' . $request->search . '%');
            });
        }

        $tickets = $query->paginate(15);

        // Statistiques
        $stats = [
            'total' => SupportTicket::count(),
            'open' => SupportTicket::open()->count(),
            'in_progress' => SupportTicket::inProgress()->count(),
            'resolved' => SupportTicket::resolved()->count(),
            'closed' => SupportTicket::closed()->count(),
            'urgent' => SupportTicket::byPriority('urgent')->count(),
        ];

        return view('admin.support.index', compact('tickets', 'stats'));
    }

    /**
     * Afficher un ticket avec ses réponses
     */
    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'admin', 'replies.user']);

        return view('admin.support.show', compact('ticket'));
    }

    /**
     * Répondre à un ticket
     */
    public function reply(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:10',
        ]);

        SupportReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $validated['message'],
            'is_admin' => true,
        ]);

        // Mettre à jour le statut du ticket si nécessaire
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress', 'admin_id' => auth()->id()]);
        }

        return redirect()->back()->with('success', 'Réponse envoyée avec succès.');
    }

    /**
     * Mettre à jour le statut
     */
    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $data = ['status' => $validated['status']];

        // Si le ticket est résolu ou fermé, enregistrer la date
        if (in_array($validated['status'], ['resolved', 'closed'])) {
            $data['resolved_at'] = now();
        } else {
            $data['resolved_at'] = null;
        }

        // Assigner l'admin si le ticket passe en cours
        if ($validated['status'] === 'in_progress' && !$ticket->admin_id) {
            $data['admin_id'] = auth()->id();
        }

        $ticket->update($data);

        return redirect()->back()->with('success', 'Statut mis à jour.');
    }

    /**
     * Mettre à jour la priorité
     */
    public function updatePriority(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $ticket->update(['priority' => $validated['priority']]);

        return redirect()->back()->with('success', 'Priorité mise à jour.');
    }

    /**
     * Assigner un ticket à un admin
     */
    public function assign(Request $request, SupportTicket $ticket)
    {
        $ticket->update([
            'admin_id' => auth()->id(),
            'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
        ]);

        return redirect()->back()->with('success', 'Ticket assigné à vous.');
    }

    /**
     * Supprimer un ticket
     */
    public function destroy(SupportTicket $ticket)
    {
        $ticket->delete();

        return redirect()->route('admin.support.index')
            ->with('success', 'Ticket supprimé avec succès.');
    }
}
