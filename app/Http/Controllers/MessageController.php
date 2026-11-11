<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Models\Conversation;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $conversations = $request->user()->conversationsAsBuyer()
            ->with(['vendor', 'product', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->paginate(15);

        return view('messages.index', compact('conversations'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->buyer_id === $request->user()->id, 403);

        $conversation->load(['messages.sender', 'vendor', 'product']);
        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('messages.show', compact('conversation'));
    }

    /**
     * Démarre (ou réutilise) une conversation avec le fournisseur d'un
     * produit, depuis le bouton « Contacter le fournisseur » de la fiche produit.
     */
    public function store(Request $request, Product $product)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $conversation = Conversation::firstOrCreate([
            'buyer_id' => $request->user()->id,
            'vendor_id' => $product->vendor_id,
            'product_id' => $product->id,
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $conversation->update(['last_message_at' => now()]);

        $this->notifyVendor($conversation, $message);

        return redirect()->route('messages.show', $conversation)->with('status', __('Message envoyé au fournisseur.'));
    }

    public function reply(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->buyer_id === $request->user()->id, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $conversation->update(['last_message_at' => now()]);

        $this->notifyVendor($conversation, $message);

        return back();
    }

    private function notifyVendor(Conversation $conversation, $message): void
    {
        $vendorUser = $conversation->vendor->user;

        Mail::to($vendorUser->email)->send(new NewMessageMail(
            $message,
            $vendorUser->name,
            route('vendor.messages.show', $conversation)
        ));
    }
}
