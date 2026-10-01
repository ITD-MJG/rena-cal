<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Worksheet;
use App\Worksheets\WorksheetParser;
use Carbon\Carbon;
use Throwable;

/**
 * Turns an uploaded workbook into a stored worksheet: parse it, resolve the
 * device, and keep the parsed payload plus the few denormalised columns the
 * resource table sorts on.
 */
class WorksheetService
{
    public function __construct(private WorksheetParser $parser) {}

    /**
     * @param  array{file_path: string, original_filename: string, device_id?: int|null, cert_number?: string|null, order_number?: string|null}  $data
     */
    public function createFromUpload(array $data): Worksheet
    {
        $payload = $this->parser->parse($data['file_path']);
        $payload = $payload === [] ? null : $payload;

        return Worksheet::create([
            'device_id' => $data['device_id'] ?? $this->matchDevice(data_get($payload, 'device.serial'))?->id,
            'source_workbook_path' => $data['file_path'],
            'original_filename' => $data['original_filename'],
            'payload' => $payload,
            'cert_number' => $data['cert_number'] ?? null,
            'order_number' => $data['order_number'] ?? null,
            'status' => Worksheet::STATUS_DRAFT,
            ...$this->denormalise($payload),
        ]);
    }

    public function reparse(Worksheet $worksheet): Worksheet
    {
        $payload = $this->parser->parse($worksheet->source_workbook_path);
        $payload = $payload === [] ? null : $payload;

        $worksheet->update([
            'payload' => $payload,
            'device_id' => $worksheet->device_id ?? $this->matchDevice(data_get($payload, 'device.serial'))?->id,
            ...$this->denormalise($payload),
        ]);

        return $worksheet->refresh();
    }

    public function matchDevice(?string $serial): ?Device
    {
        if ($serial === null || $serial === '') {
            return null;
        }

        return Device::where('serial_number', $serial)->first();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function denormalise(?array $payload): array
    {
        return [
            'device_serial' => data_get($payload, 'device.serial'),
            'customer_name' => data_get($payload, 'customer.name'),
            'calibration_date' => $this->date(data_get($payload, 'calibration.calibration_date')),
            'conclusion' => data_get($payload, 'conclusion'),
        ];
    }

    /**
     * The workbook writes dates as "02 JUL 2026".
     */
    private function date(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d M Y', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
