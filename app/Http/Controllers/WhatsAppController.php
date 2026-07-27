<?php

namespace App\Http\Controllers;

class WhatsAppController extends Controller
{
    /**
     * Placeholder page for the WhatsApp channel sub-menu — integration is
     * not built yet, so this simply informs the user it's coming soon.
     */
    public function index()
    {
        return view('whatsapp.index');
    }
}
