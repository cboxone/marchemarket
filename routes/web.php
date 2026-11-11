<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\BankTransferController as AdminBankTransferController;
use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VendorController as AdminVendorController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\Vendor\MessageController as VendorMessageController;
use App\Http\Controllers\Vendor\OrderController as VendorOrderController;
use App\Http\Controllers\Vendor\PackController as VendorPackController;
use App\Http\Controllers\Vendor\PayoutController as VendorPayoutController;
use App\Http\Controllers\Vendor\DisputeController as VendorDisputeController;
use App\Http\Controllers\Vendor\QuoteRequestController as VendorQuoteRequestController;
use App\Http\Controllers\Vendor\ShopController as VendorShopController;
use App\Http\Controllers\Vendor\VerificationController as VendorVerificationController;
use App\Http\Controllers\Vendor\ProductController as VendorProductController;
use App\Http\Controllers\Vendor\ProductImportController;
use App\Http\Controllers\VendorProfileController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

// --- Public : vitrine & catalogue ---
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/produits', [ProductController::class, 'index'])->name('products.index');
Route::get('/produits/autocompletion', [ProductController::class, 'autocomplete'])->middleware('throttle:autocomplete')->name('products.autocomplete');
Route::get('/produits/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/packs', [PackController::class, 'index'])->name('packs.index');
Route::get('/packs/{pack:slug}', [PackController::class, 'show'])->name('packs.show');
Route::get('/fournisseurs/{vendor:slug}', [VendorProfileController::class, 'show'])->name('vendors.show');
Route::post('/langue/{locale}', [LocaleController::class, 'update'])->name('locale.update');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');

// --- Pages légales ---
Route::get('/conditions-generales', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/politique-de-confidentialite', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/mentions-legales', [LegalController::class, 'notice'])->name('legal.notice');

// --- Authentification ---
Route::middleware('guest')->group(function () {
    Route::get('/inscription', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store']);

    Route::get('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.update');
});
Route::middleware('auth')->post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profil/exporter', [ProfileController::class, 'export'])->middleware('throttle:10,60')->name('profile.export');
    Route::delete('/profil', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// --- Vérification d'adresse e-mail ---
Route::middleware('auth')->group(function () {
    Route::get('/verifier-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verifier-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/verifier-email', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// --- Espace acheteur (connecté) ---
Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('/panier', [CartController::class, 'index'])->name('cart.index');
    Route::get('/panier/compteur', [CartController::class, 'count'])->name('cart.count');
    Route::post('/panier', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/panier/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/panier/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/packs/{pack}/ajouter-au-panier', [PackController::class, 'addToCart'])->name('packs.add-to-cart');

    Route::get('/commande', [CheckoutController::class, 'create'])->middleware('verified')->name('checkout.create');
    Route::post('/commande', [CheckoutController::class, 'store'])->middleware(['verified', 'throttle:checkout'])->name('checkout.store');
    Route::post('/commande/code-promo', [CheckoutController::class, 'applyCoupon'])->middleware('throttle:coupon')->name('checkout.apply-coupon');
    Route::delete('/commande/code-promo', [CheckoutController::class, 'removeCoupon'])->name('checkout.remove-coupon');

    Route::get('/mes-commandes', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/mes-commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/mes-commandes/{order}/facture', [InvoiceController::class, 'show'])->name('orders.invoice');
    Route::get('/mes-commandes/{order}/litige', [DisputeController::class, 'create'])->name('disputes.create');
    Route::post('/mes-commandes/{order}/litige', [DisputeController::class, 'store'])->middleware('throttle:disputes')->name('disputes.store');
    Route::get('/mes-commandes/{order}/facture/telecharger', [InvoiceController::class, 'download'])->name('orders.invoice.download');
    Route::post('/mes-commandes/{order}/annuler', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/produits/{product}/devis', [QuoteRequestController::class, 'create'])->name('quotes.create');
    Route::post('/produits/{product}/devis', [QuoteRequestController::class, 'store'])->middleware(['verified', 'throttle:quotes'])->name('quotes.store');
    Route::get('/adresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/adresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('/adresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/adresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::post('/adresses/{address}/par-defaut', [AddressController::class, 'setDefault'])->name('addresses.set-default');

    Route::get('/mes-litiges', [DisputeController::class, 'index'])->name('disputes.index');
    Route::get('/mes-litiges/{dispute}', [DisputeController::class, 'show'])->name('disputes.show');
    Route::get('/mes-litiges/{dispute}/preuve/{index}', [DisputeController::class, 'evidence'])->name('disputes.evidence');
    Route::post('/mes-litiges/{dispute}/retirer', [DisputeController::class, 'withdraw'])->name('disputes.withdraw');

    Route::get('/mes-devis', [QuoteRequestController::class, 'index'])->name('quotes.index');
    Route::get('/mes-devis/{quote}', [QuoteRequestController::class, 'show'])->name('quotes.show');
    Route::post('/mes-devis/{quote}/accepter', [QuoteRequestController::class, 'accept'])->middleware(['verified', 'throttle:checkout'])->name('quotes.accept');
    Route::post('/mes-devis/{quote}/annuler', [QuoteRequestController::class, 'cancel'])->name('quotes.cancel');

    Route::get('/commande/{order}/paiement', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/commande/{order}/paiement/verifier', [PaymentController::class, 'verify'])->middleware('throttle:payment')->name('payment.verify');
    Route::post('/commande/{order}/paiement/virement/choisir', [PaymentController::class, 'chooseBankTransfer'])->name('payment.bank-transfer.choose');
    Route::post('/commande/{order}/paiement/virement/declarer', [PaymentController::class, 'declareBankTransfer'])->middleware('throttle:bank-transfer')->name('payment.bank-transfer.declare');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}/repondre', [MessageController::class, 'reply'])->middleware('throttle:messages')->name('messages.reply');
    Route::post('/produits/{product}/contacter', [MessageController::class, 'store'])->middleware('throttle:messages')->name('messages.store');
    Route::post('/produits/{product}/avis', [ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/favoris', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/produits/{product}/favoris', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

    Route::get('/notifications/compteur', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
});

// --- Webhook KkiaPay (hors auth, protégé par signature) ---
Route::post('/webhooks/kkiapay', [PaymentController::class, 'webhook'])->name('payment.webhook');

// --- Espace vendeur ---
Route::middleware(['auth', 'role:vendor'])->prefix('vendeur')->name('vendor.')->group(function () {
    Route::get('/', [VendorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/produits', [VendorProductController::class, 'index'])->name('products.index');
    Route::get('/produits/nouveau', [VendorProductController::class, 'create'])->name('products.create');
    Route::post('/produits', [VendorProductController::class, 'store'])->name('products.store');
    Route::get('/produits/importer', [ProductImportController::class, 'create'])->name('products.import');
    Route::get('/produits/importer/modele', [ProductImportController::class, 'template'])->name('products.import-template');
    Route::post('/produits/importer', [ProductImportController::class, 'store'])->name('products.import-store');
    Route::get('/produits/{product}/modifier', [VendorProductController::class, 'edit'])->name('products.edit');
    Route::put('/produits/{product}', [VendorProductController::class, 'update'])->name('products.update');
    Route::delete('/produits/{product}', [VendorProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/messages', [VendorMessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{conversation}', [VendorMessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}/repondre', [VendorMessageController::class, 'reply'])->middleware('throttle:messages')->name('messages.reply');

    Route::get('/packs', [VendorPackController::class, 'index'])->name('packs.index');
    Route::get('/packs/nouveau', [VendorPackController::class, 'create'])->name('packs.create');
    Route::post('/packs', [VendorPackController::class, 'store'])->name('packs.store');
    Route::delete('/packs/{pack}', [VendorPackController::class, 'destroy'])->name('packs.destroy');

    Route::get('/commandes', [VendorOrderController::class, 'index'])->name('orders.index');
    Route::get('/commandes/export', [VendorOrderController::class, 'export'])->name('orders.export');
    Route::get('/commandes/{order}', [VendorOrderController::class, 'show'])->name('orders.show');
    Route::patch('/commandes/article/{orderItem}/statut', [VendorOrderController::class, 'updateStatus'])->name('orders.update-status');

    Route::get('/litiges', [VendorDisputeController::class, 'index'])->name('disputes.index');
    Route::get('/litiges/{dispute}', [VendorDisputeController::class, 'show'])->name('disputes.show');
    Route::get('/litiges/{dispute}/preuve/{index}', [VendorDisputeController::class, 'evidence'])->name('disputes.evidence');
    Route::post('/litiges/{dispute}/repondre', [VendorDisputeController::class, 'respond'])->name('disputes.respond');

    Route::get('/devis', [VendorQuoteRequestController::class, 'index'])->name('quotes.index');
    Route::get('/devis/{quote}', [VendorQuoteRequestController::class, 'show'])->name('quotes.show');
    Route::post('/devis/{quote}/repondre', [VendorQuoteRequestController::class, 'respond'])->name('quotes.respond');
    Route::post('/devis/{quote}/refuser', [VendorQuoteRequestController::class, 'decline'])->name('quotes.decline');

    Route::get('/verification', [VendorVerificationController::class, 'edit'])->name('verification.edit');
    Route::post('/verification', [VendorVerificationController::class, 'store'])->middleware('throttle:kyc')->name('verification.store');

    Route::get('/boutique', [VendorShopController::class, 'edit'])->name('shop.edit');
    Route::put('/boutique', [VendorShopController::class, 'update'])->name('shop.update');

    Route::get('/paiements', [VendorPayoutController::class, 'index'])->name('payouts.index');
    Route::get('/paiements/export', [VendorPayoutController::class, 'export'])->name('payouts.export');
    Route::post('/paiements', [VendorPayoutController::class, 'store'])->name('payouts.store');
});

// --- Back-office admin ---
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
    Route::get('/verifications/{vendor}', [AdminVerificationController::class, 'show'])->name('verifications.show');
    Route::get('/verifications/{vendor}/document/{type}', [AdminVerificationController::class, 'document'])->name('verifications.document');
    Route::post('/verifications/{vendor}/approuver', [AdminVerificationController::class, 'approve'])->name('verifications.approve');
    Route::post('/verifications/{vendor}/rejeter', [AdminVerificationController::class, 'reject'])->name('verifications.reject');

    Route::get('/vendeurs', [AdminVendorController::class, 'index'])->name('vendors.index');
    Route::patch('/vendeurs/{vendor}/verifier', [AdminVendorController::class, 'toggleVerification'])->name('vendors.toggle-verification');

    Route::get('/utilisateurs', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/utilisateurs/{user}/suspendre', [AdminUserController::class, 'toggleBan'])->name('users.toggle-ban');

    Route::get('/virements', [AdminBankTransferController::class, 'index'])->name('bank-transfers.index');
    Route::get('/virements/{order}', [AdminBankTransferController::class, 'show'])->name('bank-transfers.show');
    Route::get('/virements/{order}/preuve', [AdminBankTransferController::class, 'proof'])->name('bank-transfers.proof');
    Route::post('/virements/{order}/confirmer', [AdminBankTransferController::class, 'confirm'])->name('bank-transfers.confirm');
    Route::post('/virements/{order}/rejeter', [AdminBankTransferController::class, 'reject'])->name('bank-transfers.reject');

    Route::get('/litiges', [AdminDisputeController::class, 'index'])->name('disputes.index');
    Route::get('/litiges/{dispute}', [AdminDisputeController::class, 'show'])->name('disputes.show');
    Route::get('/litiges/{dispute}/preuve/{index}', [AdminDisputeController::class, 'evidence'])->name('disputes.evidence');
    Route::post('/litiges/{dispute}/trancher', [AdminDisputeController::class, 'resolve'])->name('disputes.resolve');
    Route::post('/litiges/{dispute}/rembourse-manuellement', [AdminDisputeController::class, 'markRefunded'])->name('disputes.mark-refunded');

    Route::get('/produits', [AdminProductController::class, 'index'])->name('products.index');
    Route::patch('/produits/{product}/suspendre', [AdminProductController::class, 'suspend'])->name('products.suspend');
    Route::patch('/produits/{product}/retablir', [AdminProductController::class, 'restore'])->name('products.restore');
    Route::delete('/produits/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/codes-promo', [AdminCouponController::class, 'index'])->name('coupons.index');
    Route::post('/codes-promo', [AdminCouponController::class, 'store'])->name('coupons.store');
    Route::patch('/codes-promo/{coupon}/basculer', [AdminCouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('/codes-promo/{coupon}', [AdminCouponController::class, 'destroy'])->name('coupons.destroy');

    Route::get('/commandes', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/commandes/export', [AdminOrderController::class, 'export'])->name('orders.export');
    Route::patch('/commandes/{order}/statut', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');

    Route::get('/paiements', [AdminPayoutController::class, 'index'])->name('payouts.index');
    Route::get('/paiements/export', [AdminPayoutController::class, 'export'])->name('payouts.export');
    Route::patch('/paiements/{payout}/payer', [AdminPayoutController::class, 'markPaid'])->name('payouts.mark-paid');
    Route::patch('/paiements/{payout}/rejeter', [AdminPayoutController::class, 'reject'])->name('payouts.reject');

    Route::get('/avis', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::delete('/avis/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/journal', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::get('/parametres', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::post('/parametres', [AdminSettingController::class, 'update'])->name('settings.update');
});
