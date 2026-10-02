<?php

use App\Filament\Dashboard\Resources\Worksheets\Pages\ViewWorksheet;
use App\Models\Customer;
use App\Models\Device;
use App\Models\User;
use App\Models\Worksheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['Super Admin', 'Admin', 'Hospital Admin', 'Technician'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

function worksheetFixture(): Worksheet
{
    $customer = Customer::create([
        'name' => 'Klinik Pratama Ocean Dental Radio Dalam',
        'slug' => 'klinik-pratama-ocean-dental-radio-dalam',
        'address' => 'Jl. Radio Dalam Raya No.26, Jakarta Selatan 12140',
    ]);

    $device = Device::factory()->create([
        'serial_number' => 'A250705-510',
        'customer_id' => $customer->id,
    ]);

    return Worksheet::factory()->create([
        'device_id' => $device->id,
        'cert_number' => 'RKS/26/3125',
        'order_number' => '07260074',
        'payload' => [
            'device' => ['name' => 'AUTOCLAVE', 'brand' => 'LOKAL', 'type' => 'YA28X6T/8', 'serial' => 'A250705-510'],
            'customer' => ['name' => 'Klinik Pratama Ocean Dental Radio Dalam'],
            'service' => ['name' => 'Instalasi sterilisasi pusat'],
            'calibration' => [
                'room' => 'STERILISASI',
                'technician' => 'MARSHEL ASYRAF DALTAFIKA',
                'received_date' => '02 JUL 2026',
                'calibration_date' => '02 JUL 2026',
            ],
            'environment' => [
                'temperature' => ['value' => 24.55, 'uncert' => 0.63],
                'humidity' => ['value' => 55.5, 'uncert' => 2.9],
                'main_voltage' => 222,
            ],
            'instruments' => [
                ['name' => 'Electrical Safety Analyzer', 'brand' => 'RIGEL', 'type' => '288 PLUS', 'serial' => '17Q-1323', 'traceability' => 'LK-032-IDN'],
            ],
            'physical' => [['parameter' => 'Badan dan permukaan', 'result' => 'Baik']],
            'electrical' => [['parameter' => 'Resistansi pembumian', 'measured' => 0.08529005530253725, 'unit' => 'Ω', 'limit' => 0.3, 'limit_unit' => 'Ω']],
            'performance' => [
                'temperature' => [['setting' => 134, 'standard' => 135.67030976400375, 'correction' => 1.670309764003747, 'limit' => '± 2 °C']],
                'temperature_uncertainty' => [['value' => 0.06855226743147864, 'unit' => '°C']],
                'time' => [['setting' => 3, 'standard' => 3, 'correction' => 0, 'limit' => '≥ 3 menit']],
                'time_uncertainty' => [['value' => 0, 'unit' => '°C']],
            ],
            'notes' => [['text' => 'Kalibrasi menggunakan Instruksi Kerja (RKS/MT/IK.01-010)']],
            'conclusion' => 'Tidak Laik Pakai',
        ],
    ]);
}

it('renders the view page', function () {
    $worksheet = worksheetFixture();
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    Livewire::actingAs($user)
        ->test(ViewWorksheet::class, ['record' => $worksheet->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('A250705-510');
});

it('renders a three page certificate from the stored payload', function () {
    $worksheet = worksheetFixture()->load('device.customer');

    $dompdf = Pdf::loadView('pdf.certificate', ['worksheet' => $worksheet])
        ->setPaper('a4', 'portrait')
        ->getDomPDF();
    $dompdf->render();

    expect($dompdf->output())->toStartWith('%PDF')
        ->and($dompdf->getCanvas()->get_page_count())->toBe(3);
});

it('carries the workbook values into the certificate', function () {
    $worksheet = worksheetFixture()->load('device.customer');

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)
        ->toContain('RKS/26/3125')
        ->toContain('07260074')
        ->toContain('A250705-510')
        ->toContain('YA28X6T/8')
        ->toContain('24.55')
        // Rendered three decimals, trailing zeros trimmed: never the raw float.
        ->toContain('135.67')
        ->not->toContain('135.67030976400375')
        ->toContain('TIDAK LAIK PAKAI')
        ->toContain('Jl. Radio Dalam Raya');
});

it('lists the certificate and order numbers with the identitas alat alignment', function () {
    // The cover pairs each label with a colon-prefixed value, the same shape
    // the IDENTITAS ALAT block uses, so the two blocks line up.
    $worksheet = worksheetFixture()->load('device.customer');

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)
        ->toContain('Nomor Sertifikat</td><td style="border:none;">: RKS/26/3125')
        ->toContain('Nomor Pesanan</td><td style="border:none;">: 07260074')
        ->and(strpos($html, 'Nomor Sertifikat'))->toBeLessThan(strpos($html, 'Nomor Pesanan'));

    // The certificate number appears exactly once: in the cover's
    // Nomor Sertifikat row, not repeated under the title.
    expect(substr_count($html, 'RKS/26/3125'))->toBe(1);
});

it('renders without error when the payload is null', function () {
    $worksheet = Worksheet::factory()->create(['payload' => null]);

    expect(view('pdf.certificate', ['worksheet' => $worksheet])->render())->toBeString();
});

it('does not assert a verdict when the conclusion could not be read', function () {
    // A null conclusion means the workbook cell was unreadable. Printing
    // "TIDAK LAIK PAKAI" would declare the equipment unfit on no evidence.
    $worksheet = Worksheet::factory()->create([
        'payload' => ['device' => ['serial' => 'A250705-510'], 'conclusion' => null],
    ]);

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)->not->toContain('TIDAK LAIK PAKAI')
        ->and($html)->not->toContain('LAIK PAKAI');
});

it('omits the letterhead from every page', function () {
    // The header partial carried the RENA logo and the company block. It is no
    // longer rendered, so neither the company name nor the contact lines appear.
    $worksheet = worksheetFixture();

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)
        ->not->toContain('PT RENA KALIBRINDO SELARAS')
        ->not->toContain('Jl. Pangeran Antasari')
        ->not->toContain('admin@rena.co.id');
});

it('renders the page count in the fixed footer', function () {
    $worksheet = worksheetFixture();

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)
        ->toContain('pdf-footer')
        ->toContain('Halaman')
        // The current page number comes from the CSS page counter, so the
        // footer is repeated on every page rather than hardcoded per page.
        ->toContain('counter(page)')
        ->not->toContain('Dilarang memperbanyak');
});

it('reserves a blank top margin for the pre-printed letterhead', function () {
    // The certificate paper is pre-printed with the RENA letterhead, so the
    // top of every page must stay empty: 35mm of headroom plus the 10mm base
    // margin. A regression to the old 10mm top would print content under it.
    $worksheet = worksheetFixture();

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)->toContain('padding: 45mm 20mm 10mm 20mm');
});

it('keeps the fixed footer clear of the paper edge', function () {
    // Without bottom padding the footer prints flush against the paper's
    // bottom edge, where most printers cannot reach it.
    $worksheet = worksheetFixture();

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)->toContain('padding-bottom: 10mm');
});

it('places the QR block between the signer and the e-signature note', function () {
    $worksheet = worksheetFixture();

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    $signer = strpos($html, 'Penanggung Jawab');
    $qr = strpos($html, 'border:1px dashed #999');
    $note = strpos($html, 'Dokumen ini ditandatangani secara elektronik');

    expect($signer)->not->toBeFalse()
        ->and($qr)->not->toBeFalse()
        ->and($note)->not->toBeFalse()
        ->and($signer)->toBeLessThan($qr)
        ->and($qr)->toBeLessThan($note);
});

it('leaves 1.5cm below the letterhead rule before the body', function () {
    // The header partial is not rendered into this certificate (the paper is
    // pre-printed), but its rule-to-body gap is the layout contract for the
    // letterhead. 8px ran the first table into the rule.
    $html = view('pdf.partials.header')->render();

    expect($html)
        ->toContain('margin-bottom: 1.5cm')
        ->not->toContain('height: 1.5cm');
});

it('separates each numbered part group from the table above it', function () {
    // Section titles ran straight into the previous table. The top margin
    // gives every numbered group its own breathing room.
    $worksheet = worksheetFixture();

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)->toContain('margin: 12px 0 6px 0');
});
