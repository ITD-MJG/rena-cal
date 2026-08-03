<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            'Pelayanan rawat jalan',
            'Pelayanan UGD',
            'Pelayanan Rawat Inap',
            'Pelayanan bedah sentral (OK)',
            'Pelayanan perawatan intensif bayi',
            'Bagian keuangan',
            'Ruang Rekam Medis',
            'Ruang Radiologi',
            'Ruang Direksi',
            'Ruang ICU',
            'Ruang Komite Medis',
            'Ruang Bidang Keperawatan',
            'Ruang bidang pelayanan medis',
            'Unit Hemodialisa',
            'Instalasi Farmasi',
            'Instalasi dapur utama dan Gizi Klinik',
            'Instalasi pemulasaraan jenazah',
            'Instalasi laboratorium',
            'Instalasi pencucian linen/laundri',
            'Instalasi sterilisasi pusat',
            'Instalasi sanitasi',
            'Instalasi pemeliharaan sarana',
            'Instalasi radiodiagnostik',
            'Instalasi rehabilitasi medik',
            'Bank Darah/Unit Transfusi Darah',
            'Bidang pelayanan penunjang medik',
            'Ruangan Pendidikan dan pelatihan',
            'Ruangan SDM',
            'Ruang sekretaris direktur',
            'Ruangan rapat dan diskusi',
            'Ruangan SPI (satuan pengawas internal)',
            'Ruangan arsip/file',
            'Ruangan Tunggu',
        ];

        foreach ($services as $name) {
            Service::firstOrCreate(['name' => $name]);
        }
    }
}
