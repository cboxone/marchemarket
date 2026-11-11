<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\NewMessageMail;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $conversations = $request->user()->vendor->conversations()
            ->with(['buyer', 'product', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->paginate(15);

        return view('vendor.messages.index', compact('conversations'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->vendor_id === $request->user()->vendor->id, 403);

        $conversation->load(['messages.sender', 'buyer', 'product']);
        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('vendor.messages.show', compact('conversation'));
    }

    public function reply(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->vendor_id === $request->user()->vendor->id, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $conversation->update(['last_message_at' => now()]);

        $buyer = $conversation->buyer;

        Mail::to($buyer->email)->send(new NewMessageMail(
            $message,
            $buyer->name,
            route('messages.show', $conversation)
        ));

        return back();
    }
}
