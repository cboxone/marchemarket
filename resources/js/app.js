import './bootstrap';

// Bascule de la quantité et recalcul du prix par palier sur la fiche produit.
document.addEventListener('DOMContentLoaded', () => {
    const qtyInput = document.querySelector('[data-role="quantity-input"]');
    const tierRows = document.querySelectorAll('[data-role="tier-row"]');
    const unitPriceEl = document.querySelector('[data-role="unit-price"]');
    const totalPriceEl = document.querySelector('[data-role="total-price"]');

    if (!qtyInput || !tierRows.length) return;

    const applyTier = () => {
        const qty = parseInt(qtyInput.value || '0', 10);
        let matched = null;

        tierRows.forEach((row) => {
            row.classList.remove('bg-amber-50', 'border-amber-400');
            const min = parseInt(row.dataset.min, 10);
            const max = row.dataset.max ? parseInt(row.dataset.max, 10) : Infinity;
            if (qty >= min && qty <= max) {
                matched = row;
            }
        });

        if (matched) {
            matched.classList.add('bg-amber-50', 'border-amber-400');
            const price = parseFloat(matched.dataset.price);
            if (unitPriceEl) unitPriceEl.textContent = price.toLocaleString('fr-FR', { minimumFractionDigits: 0 }) + ' FCFA';
            if (totalPriceEl) totalPriceEl.textContent = (price * qty).toLocaleString('fr-FR', { minimumFractionDigits: 0 }) + ' FCFA';
        }
    };

    qtyInput.addEventListener('input', applyTier);
    applyTier();

    // Gestion AJAX pour l'ajout au panier
    const addToCartForms = document.querySelectorAll('form[data-cart-add="true"]');
    
    addToCartForms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = '...';

            const formData = new FormData(form);
            
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();

                if (data.success) {
                    // Mettre à jour le compteur de panier
                    const cartBadge = document.getElementById('cart-count-badge');
                    if (cartBadge) {
                        cartBadge.textContent = data.cart_count;
                        cartBadge.classList.toggle('hidden', data.cart_count === 0);
                    }

                    // Afficher le toast
                    if (typeof showToast === 'function') {
                        showToast(data.message, 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.message || 'Erreur lors de l\'ajout au panier', 'error');
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
                if (typeof showToast === 'function') {
                    showToast('Erreur de connexion', 'error');
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    });

    // Système de notifications toast
    window.showToast = function(message, type) {
        type = type || 'success';
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        var bgColor = type === 'success' ? 'bg-trade-green' : 
                       type === 'error' ? 'bg-red-600' : 
                       type === 'warning' ? 'bg-amber-500' : 'bg-navy-800';
        
        toast.className = bgColor + ' text-white px-4 py-3 rounded-md shadow-lg transform transition-all duration-300 translate-x-full opacity-0';
        
        var iconSvg = '';
        if (type === 'success') {
            iconSvg = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />';
        } else if (type === 'error') {
            iconSvg = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />';
        } else {
            iconSvg = '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />';
        }
        
        toast.innerHTML = '<div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">' + iconSvg + '</svg><span class="text-sm font-medium">' + message + '</span></div>';

        container.appendChild(toast);

        requestAnimationFrame(function() {
            toast.classList.remove('translate-x-full', 'opacity-0');
        });

        setTimeout(function() {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 3000);
    };

    // Afficher les notifications flash de Laravel
    const flashSuccess = document.getElementById('flash-success');
    if (flashSuccess && flashSuccess.textContent) {
        window.showToast(flashSuccess.textContent, 'success');
    }

    const flashError = document.getElementById('flash-error');
    if (flashError && flashError.textContent) {
        window.showToast(flashError.textContent, 'error');
    }

    const flashWarning = document.getElementById('flash-warning');
    if (flashWarning && flashWarning.textContent) {
        window.showToast(flashWarning.textContent, 'warning');
    }

    // Rafraîchissement des badges
    const appData = document.getElementById('app-data');
    if (appData) {
        const unreadCountUrl = appData.dataset.unreadCountUrl;
        if (unreadCountUrl) {
            function refreshUnreadBadge() {
                fetch(unreadCountUrl)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        document.querySelectorAll('#unread-messages-badge').forEach(function(badge) {
                            badge.textContent = data.count;
                            badge.classList.toggle('hidden', data.count === 0);
                        });
                    })
                    .catch(function() {});
            }
            setInterval(refreshUnreadBadge, 30000);
        }

        const cartCountUrl = appData.dataset.cartCountUrl;
        if (cartCountUrl) {
            function refreshCartCount() {
                fetch(cartCountUrl)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        const badge = document.getElementById('cart-count-badge');
                        if (badge) {
                            badge.textContent = data.count;
                            badge.classList.toggle('hidden', data.count === 0);
                        }
                    })
                    .catch(function() {});
            }
            setInterval(refreshCartCount, 30000);
        }
    }

    // Gestion des formulaires de suppression avec confirmation
    const deleteForms = document.querySelectorAll('.delete-form');
    deleteForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const message = form.dataset.confirm;
            if (message && !confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
