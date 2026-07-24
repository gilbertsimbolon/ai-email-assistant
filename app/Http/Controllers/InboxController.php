<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    /**
     * Menampilkan halaman Inbox utama (List Percakapan).
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending_review');

        // Ambil percakapan beserta relasi analisis, draf, dan pesan terakhir
        $conversations = Conversation::with(['analysis', 'drafts', 'messages'])
            ->when($status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->latest('last_message_at')
            ->paginate(15);

        return view('inbox.index', compact('conversations', 'status'));
    }

    /**
     * Menampilkan detail percakapan yang dipilih dari daftar.
     */
    public function show(Conversation $conversation)
    {
        $conversation->load(['analysis', 'drafts', 'messages']);

        return view('inbox.show', compact('conversation'));
    }
}