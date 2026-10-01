<?php

use App\Http\Controllers\CustomerAdminLoginController;
use App\Livewire\Public\DeviceDetail;
use App\Models\CalibrationWorksheet;
use App\Models\Device;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/devices/details/{deviceId}', DeviceDetail::class)->name('devices.show');
// Redirect old device detail page to new device detail page
Route::get('/devices/{deviceId}', function ($deviceId) {
    return redirect()->route('devices.show', ['deviceId' => $deviceId]);
})->name('devices.publicDetail');

// Log a newly assigned customer admin in from a signed email link
Route::get('/customers/admin/login/{user}', CustomerAdminLoginController::class)
    ->middleware('signed')
    ->name('customers.admin.login');

// Certificate download route
Route::get('/certificate/download/{cert_number}', function (Request $request, $cert_number) {
    if (str_contains($cert_number, '..')) {
        abort(403, 'Invalid path');
    }

    $device = Device::where('cert_number', $cert_number)->first();

    if ($device && $device->cert_password) {
        $sessionKey = 'cert_verified_'.$device->id;
        $verifiedAt = session()->get('cert_verified_at_'.$device->id);

        if (! $verifiedAt || ! session()->get($sessionKey)) {
            return redirect()->route('devices.show', ['deviceId' => $device->deviceId])
                ->with('error', 'Silakan masukkan kata sandi untuk melihat sertifikat.');
        }

        $verifiedAt = Carbon::parse($verifiedAt);
        if ($verifiedAt->diffInMinutes(now()) > 120) {
            session()->forget($sessionKey);
            session()->forget('cert_verified_at_'.$device->id);

            return redirect()->route('devices.show', ['deviceId' => $device->deviceId])
                ->with('error', 'Sesi verifikasi telah kedaluwarsa. Silakan masukkan kata sandi lagi.');
        }
    }

    $disk = Storage::disk('public');

    if ($disk->exists($cert_number)) {
        $response = $disk->response($cert_number);

        if ($request->has('no_download')) {
            $response->headers->set('Content-Security-Policy', "sandbox allow-forms allow-scripts; default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; script-src 'none';");
        }

        return $response;
    }

    abort(404, 'Certificate not found');
})->name('certificate.download')->where('cert_number', '.*');

// QR Code Cards Print route
Route::get('/qr-print', function () {
    $ids = session()->get('qr_ids', []);
    $size = session()->get('qr_size', 'v1');
    $pageSize = session()->get('qr_page_size', 'A3');

    $assets = Device::whereIn('id', $ids)->get();
    $filename = 'Calibration-Labels-'.now()->format('Y-m-d').'.pdf';

    $pdf = Pdf::loadView('pdf.asset-calibration-labels', compact('assets', 'size'))
        ->setPaper($pageSize);

    return $pdf->stream($filename);
})->name('devices.qr-print');

// Test certificate route
Route::get('/test-certificate/{worksheet}', function ($worksheetId) {
    $worksheet = CalibrationWorksheet::with([
        'device.deviceName',
        'device.brand',
        'device.type',
        'device.customer',
        'service',
        'instruments',
        'physicalInspections',
        'electricalSafetyTests',
        'performanceMeasurements',
    ])->findOrFail($worksheetId);

    $pdf = Pdf::loadView('pdf.certificate', ['worksheet' => $worksheet])
        ->setPaper('a4', 'portrait');

    return $pdf->stream('certificate-test.pdf');
});
