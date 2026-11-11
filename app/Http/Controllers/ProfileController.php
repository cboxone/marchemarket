<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'canDeleteAccount' => $this->canDeleteAccount($user),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update($data);

        return back()->with('status', __('Profil mis à jour.'));
    }

    /**
     * Export des données personnelles de l'utilisateur en un fichier JSON
     * téléchargeable : profil, adresses, commandes, devis, litiges, avis, et
     * — pour un vendeur — sa boutique et ses reversements. Les messages
     * échangés ne sont pas inclus : un fil de discussion contient aussi les
     * messages de l'autre partie, qui ne sont pas les données personnelles
     * de la personne qui exporte.
     */
    public function export(Request $request)
    {
        $user = $request->user();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'company_name' => $user->company_name,
                'role' => $user->role,
                'member_since' => $user->created_at->toDateString(),
            ],
            'addresses' => $user->addresses->map(fn ($a) => [
                'label' => $a->label,
                'recipient_name' => $a->recipient_name,
                'address' => $a->address,
                'city' => $a->city,
                'country' => $a->country,
                'phone' => $a->phone,
                'is_default' => $a->is_default,
            ])->all(),
            'orders' => $user->orders()->with('items')->get()->map(fn (Order $order) => [
                'reference' => $order->reference,
                'date' => $order->created_at->toIso8601String(),
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => (float) $order->total_amount,
                'shipping_address' => $order->shipping_address,
                'shipping_city' => $order->shipping_city,
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                ])->all(),
            ])->all(),
            'quote_requests' => $user->quoteRequests->map(fn (QuoteRequest $q) => [
                'reference' => $q->reference,
                'date' => $q->created_at->toIso8601String(),
                'product_name' => $q->product_name,
                'quantity' => $q->quantity,
                'status' => $q->status,
            ])->all(),
            'disputes' => Dispute::where('buyer_id', $user->id)->get()->map(fn (Dispute $d) => [
                'reference' => $d->reference,
                'date' => $d->created_at->toIso8601String(),
                'reason' => $d->reason,
                'description' => $d->description,
                'status' => $d->status,
            ])->all(),
            'reviews' => $user->reviews()->with('product')->get()->map(fn ($r) => [
                'product_name' => $r->product?->name,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'date' => $r->created_at->toIso8601String(),
            ])->all(),
        ];

        if ($user->isVendor() && $user->vendor) {
            $vendor = $user->vendor;
            $data['vendor_shop'] = [
                'store_name' => $vendor->store_name,
                'description' => $vendor->description,
                'city' => $vendor->city,
                'country' => $vendor->country,
                'tax_id' => $vendor->tax_id,
                'is_verified' => $vendor->is_verified,
                'kyc_status' => $vendor->kyc_status,
            ];
            $data['payouts'] = $vendor->payouts()->get()->map(fn ($p) => [
                'date' => $p->created_at->toIso8601String(),
                'amount' => (float) $p->amount,
                'status' => $p->status,
            ])->all();
        }

        $filename = 'mes-donnees-marchemarket-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /**
     * Suppression du compte, en fait une anonymisation : les commandes,
     * factures et reversements sont des pièces comptables (voir la
     * politique de confidentialité, section « Conservation des données ») et
     * ne peuvent pas être effacés sans casser cette obligation légale — seul
     * ce qui identifie la personne (nom, e-mail, téléphone, adresses,
     * panier, favoris) est retiré ou supprimé. Les avis et messages déjà
     * postés restent visibles mais s'affichent désormais sous « Compte
     * supprimé », puisqu'ils citent l'utilisateur par sa relation `user()`
     * plutôt que par une copie figée de son nom.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->isVendor()) {
            return back()->withErrors(['account' => __('Un compte vendeur ne peut pas être supprimé directement : contactez le support pour la fermeture de votre boutique.')]);
        }

        if (! $this->canDeleteAccount($user)) {
            return back()->withErrors(['account' => __('Impossible de supprimer votre compte : vous avez une commande, un devis ou un litige encore en cours.')]);
        }

        $request->validate(['current_password' => ['required', 'current_password']]);

        $user->addresses()->delete();
        $user->wishlists()->delete();
        $user->cart()->delete();

        $user->forceFill([
            'name' => __('Compte supprimé'),
            'email' => 'compte-supprime-' . $user->id . '-' . Str::random(8) . '@marchemarket.invalid',
            'password' => Str::random(40),
            'phone' => null,
            'company_name' => null,
            'email_verified_at' => null,
            'remember_token' => null,
        ])->save();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Votre compte a été supprimé.'));
    }

    /**
     * Un compte ne se supprime pas au milieu d'une transaction en cours :
     * une commande non livrée ni annulée, un devis encore ouvert, ou un
     * litige non résolu doivent d'abord aboutir, pour ne jamais laisser un
     * fournisseur ou un admin sans interlocuteur en pleine transaction.
     */
    private function canDeleteAccount(User $user): bool
    {
        $hasActiveOrder = $user->orders()
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PAID, Order::STATUS_SHIPPED])
            ->exists();

        $hasActiveQuote = $user->quoteRequests()
            ->whereIn('status', [QuoteRequest::STATUS_PENDING, QuoteRequest::STATUS_QUOTED])
            ->exists();

        $hasActiveDispute = Dispute::where('buyer_id', $user->id)
            ->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_ESCALATED])
            ->exists();

        return ! $hasActiveOrder && ! $hasActiveQuote && ! $hasActiveDispute;
    }
}
