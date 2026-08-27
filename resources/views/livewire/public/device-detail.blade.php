<div class="min-h-screen bg-slate-50 dark:bg-[#0a0a0a] flex flex-col items-center justify-center p-4">
    @if($sessionError)
        <div class="w-full max-w-2xl mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <p class="text-red-600 dark:text-red-400 text-center font-medium">{{ $sessionError }}</p>
        </div>
    @endif
    <!-- Card Container -->
    <div class="w-full max-w-2xl bg-white dark:bg-[#141414] rounded-[1rem] border border-slate-200 dark:border-white/5 shadow-2xl overflow-hidden">
        
        <!-- Header / Device ID Style from Image -->
        <div class="px-8 pt-10 pb-4">
            <p class="text-slate-900 dark:text-white text-2xl font-bold tracking-tight text-center">
                {{ $device->device_number ?? $device->deviceId }}
            </p>
            <div class="mt-4 border-b border-slate-100 dark:border-white/10"></div>
        </div>

        <!-- Details List -->
        <div class="px-8 py-6 space-y-4">
            @php
                $details = [
                    ['label' => 'Nama Faskes', 'value' => $device->customer->name ?? 'N/A'],
                    ['label' => 'Nomor Pesanan', 'value' => $device->order_number ?? 'N/A'],
                    ['label' => 'Nama Alat', 'value' => $device->deviceName->name ?? 'N/A'],
                    ['label' => 'Merk/Pembuat', 'value' => $device->brand->name ?? 'N/A'],
                    ['label' => 'Model/Tipe', 'value' => $device->type->name ?? 'N/A'],
                    ['label' => 'Nomor Seri', 'value' => $device->serial_number ?? 'N/A'],
                    ['label' => 'Nama Ruangan', 'value' => $device->room_name ?? 'N/A'],
                    ['label' => 'Tanggal Kalibrasi', 'value' => $device->calibration_date ? \Carbon\Carbon::parse($device->calibration_date)->format('Y-m-d') : 'N/A'],
                    ['label' => 'Keterangan Hasil', 'value' => $device->result ?? 'N/A', 'is_status' => true],
                    ['label' => 'Penanggung Jawab', 'value' => $device->pic->name ?? 'N/A'],
                ];
            @endphp

            @foreach($details as $detail)
                <div class="flex flex-col md:flex-row md:items-start text-[18px]">
                    <span class="text-black dark:text-white md:w-50 shrink-0 font-bold ">{{ $detail['label'] }}</span>
                    <span class="hidden md:inline text-black dark:text-white mr-4">:</span>
                    <span class="text-black dark:text-white font-medium break-words">
                        {{ $detail['value'] }}
                    </span>
                </div>
            @endforeach
        </div>

        <!-- Action Button Section -->
        @if($device->cert_number)
            <div class="mt-4 border-t border-slate-100 dark:border-white/10">
                @if($device->cert_password && !$certificateVerified)
                    <button wire:click="openCertificateWithPassword"
                            class="flex items-center justify-center w-full py-5 bg-[#003cc2] hover:bg-[#0034a8] text-white font-semibold transition-colors duration-200">
                        Lihat Sertifikat Kalibrasi
                    </button>
                @else
                    <a href="{{ route('certificate.download', ['cert_number' => $device->cert_number, 'no_download' => 1]) }}#toolbar=0"
                       target="_blank"
                       class="flex items-center justify-center w-full py-5 bg-[#003cc2] hover:bg-[#0034a8] text-white font-semibold transition-colors duration-200">
                        Lihat Sertifikat Kalibrasi
                    </a>
                @endif
            </div>
        @endif
    </div>

    <!-- Optional Footer Logo -->
    <div class="mt-8">
        <img src="{{ asset('assets/images/logos/Rena-Logo.webp') }}"
             alt="Rena Logo"
             class="h-25 object-contain">
    </div>

    <!-- Password Modal -->
    @if($showPasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click="closeModal">
            <div class="w-full max-w-md bg-white dark:bg-[#141414] rounded-[1rem] shadow-2xl p-6" wire:click.stop>
                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">
                    Masukkan Kata Sandi
                </h3>
                <p class="text-slate-600 dark:text-gray-400 mb-4">
                    Sertifikat ini dilindungi kata sandi. Silakan masukkan kata sandi untuk melihat sertifikat.
                </p>
                <form wire:submit.prevent="verifyCertificatePassword">
                    <div class="mb-4">
                        <label for="certPassword" class="block text-sm font-medium text-slate-700 dark:text-gray-300 mb-1">
                            Kata Sandi
                        </label>
                        <input type="password"
                               id="certPassword"
                               wire:model="enteredPassword"
                               class="w-full px-4 py-2 border border-slate-300 dark:border-white/10 rounded-lg bg-white dark:bg-[#1a1a1a] text-slate-900 dark:text-white focus:ring-2 focus:ring-[#003cc2] focus:border-transparent"
                               placeholder="Masukkan kata sandi"
                               autofocus>
                        @if($passwordError)
                            <p class="mt-1 text-sm text-red-600">{{ $passwordError }}</p>
                        @endif
                    </div>
                    <div class="flex gap-3">
                        <button type="button"
                                wire:click="closeModal"
                                class="flex-1 px-4 py-2 bg-slate-100 dark:bg-[#1a1a1a] text-slate-700 dark:text-gray-300 rounded-lg font-medium hover:bg-slate-200 dark:hover:bg-[#2a2a2a] transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                                class="flex-1 px-4 py-2 bg-[#003cc2] text-white rounded-lg font-medium hover:bg-[#0034a8] transition-colors">
                            Lihat Sertifikat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
