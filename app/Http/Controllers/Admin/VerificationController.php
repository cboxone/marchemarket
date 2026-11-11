<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\VendorKycStatusMail;
use App\Models\AuditLog;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** File d'attente des dossiers de vérification (KYC) des vendeurs. */
class VerificationController extends Controller
{
    use \App\Http\Controllers\Concerns\ServesPrivateFile;

    public function index()
    {
        $vendors = Vendor::where('kyc_status', Vendor::KYC_PENDING)
            ->with('user')
            ->oldest('kyc_submitted_at')
            ->paginate(20);

        return view('admin.verifications.index', compact('vendors'));
    }

    public function show(Vendor $vendor)
    {
        abort_if($vendor->kyc_status === Vendor::KYC_NONE, 404);

        return view('admin.verifications.show', compact('vendor'));
    }

    /** @param 'id'|'business' $type */
    public function document(Vendor $vendor, string $type)
    {
        abort_if($vendor->kyc_status === Vendor::KYC_NONE, 404);

        $path = match ($type) {
            'id' => $vendor->kyc_id_document_path,
            'business' => $vendor->kyc_business_document_path,
            default => null,
        };

        return $this->servePrivateFile($path);
    }

    public function approve(Vendor $vendor)
    {
        $approved = DB::transaction(function () use ($vendor) {
            $locked = Vendor::whereKey($vendor->id)->lockForUpdate()->first();

            if ($locked->kyc_status !== Vendor::KYC_PENDING) {
                return false;
            }

            $locked->forceFill([
                'kyc_status' => Vendor::KYC_APPROVED,
                'kyc_reviewed_by' => auth()->id(),
                'kyc_reviewed_at' => now(),
                'is_verified' => true,
            ])->save();

            return true;
        });

        if (! $approved) {
            return back()->withErrors(['kyc' => __("Ce dossier n'est plus en attente d'examen.")]);
        }

        $vendor->refresh();

        AuditLog::record('vendor.kyc_approved', "Dossier de vérification approuvé : {$vendor->store_name}", $vendor);

        Mail::to($vendor->user->email)->send(new VendorKycStatusMail($vendor->user, $vendor, 'approved'));

        return redirect()->route('admin.verifications.index')->with('status', __('Dossier approuvé, vendeur vérifié.'));
    }

    public function reject(Request $request, Vendor $vendor)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $rejected = DB::transaction(function () use ($vendor, $data) {
            $locked = Vendor::whereKey($vendor->id)->lockForUpdate()->first();

            if ($locked->kyc_status !== Vendor::KYC_PENDING) {
                return false;
            }

            $locked->forceFill([
                'kyc_status' => Vendor::KYC_REJECTED,
                'kyc_reviewed_by' => auth()->id(),
                'kyc_reviewed_at' => now(),
                'kyc_rejection_reason' => $data['reason'],
            ])->save();

            return true;
        });

        if (! $rejected) {
            return back()->withErrors(['kyc' => __("Ce dossier n'est plus en attente d'examen.")]);
        }

        $vendor->refresh();

        AuditLog::record('vendor.kyc_rejected', "Dossier de vérification refusé : {$vendor->store_name} — {$data['reason']}", $vendor);

        Mail::to($vendor->user->email)->send(new VendorKycStatusMail($vendor->user, $vendor, 'rejected'));

        return redirect()->route('admin.verifications.index')->with('status', __('Dossier refusé ; le vendeur a été prévenu.'));
    }
}
