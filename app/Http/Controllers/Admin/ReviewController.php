<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::with('product', 'user')->latest()->paginate(20);

        return view('admin.reviews.index', compact('reviews'));
    }

    public function destroy(Review $review)
    {
        $description = 'Avis supprimé sur "' . $review->product->name . '" (auteur : ' . $review->user->name . ')';
        $review->delete();

        AuditLog::record('review.deleted', $description);

        return back()->with('status', __('Avis supprimé.'));
    }
}
