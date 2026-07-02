<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"> -->

    <!-- <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> -->
</head>

<body class="h-screen lg:overflow-y-hidden">
    <x-nav-baar></x-nav-baar>
        <div class="flex mt-31">

      <div class="w-[300px] bg-[#F08619] px-4 py-15 shadow-lg rounded-lg lg:block hidden">

            <x-sidebar :active="'arsip'"></x-sidebar>

            <!-- Wrapper kanan -->
            <div class="bg-[#E0DEDE] rounded-lg shadow-lg lg:mx-4 mx-2 w-[100%] h-screen p-2 overflow-y-auto">
                <div class="flex-1 p-6 h-[1450px]">
                    <!-- Judul -->
                    <div class="relative mb-6">
                        <h5 class="font-bold tracking-widest 
                                bg-[#3B6BA2] text-gray-50 
                                rounded-xl w-full py-3 text-xl 
                                text-center shadow-md">
                            DETAIL ADUAN
                        </h5>
                        <a href="{{ route('admin.arsip') }}"
                        class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-lg">
                            <i class="fa-solid fa-angle-left"></i>
                        </a>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-[#0970A5]
                                    border-l-4 border-[#0970A5] pl-3">
                                Informasi Aduan
                            </h2>
                            <div class="flex items-center gap-2">
                                @php
                                $labels = [
                                    'label4' => 'bg-green-100 text-green-700',
                                    'label3' => 'bg-blue-100 text-blue-700',
                                    'label2' => 'bg-yellow-100 text-yellow-700',
                                    'label1' => 'bg-orange-100 text-orange-700',
                                ];

                                $latestStatus = 'Baru';
                                $statusColor = 'bg-gray-100 text-gray-700';

                                foreach ($labels as $label => $color) {
                                    if (!empty($status->$label)) {
                                        $latestStatus = $status->$label;
                                        $statusColor = $color;
                                        break; // ambil label tertinggi yang ada
                                    }
                                }
                            @endphp

                            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $statusColor }}">
                                {{ $latestStatus }}
                            </span>
                            </div>
                                
                                
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
                            <div>
                                <p class="text-gray-500">Kode Aduan</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->kode_aduan ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Kategori</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->category ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Tanggal Peristiwa</p>
                                <p class="font-semibold text-gray-800">
                                    {{ $aduan->tanggal_peristiwa ? \Carbon\Carbon::parse($aduan->tanggal_peristiwa)->format('d F Y') : '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500">Lokasi Kejadian</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->lokasi ?? '-'}}</p>
                            </div>
                            <div class="md:col-span-2 mt-2">
                                <p class="text-gray-500">Status Penanganan</p>
                                @php
                                    $statusText = $status->status1 ?? '-';
                                    if (!empty($status->status4)) $statusText = $status->status4;
                                    elseif (!empty($status->status3)) $statusText = $status->status3;
                                    elseif (!empty($status->status2)) $statusText = $status->status2;
                                @endphp
                                <p class="font-semibold text-gray-800">{{ $statusText }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 class="text-base font-semibold text-[#0970A5] mb-4 border-l-4 border-[#0970A5] pl-3">
                            Data Pelapor
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
                            <div>
                                <p class="text-gray-500">Nama Pelapor</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->nama_pelapor ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Alamat</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->alamat_pelapor ?? '-'}}</</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->email_pelapor ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">No HP</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->phone_pelapor ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Bersedia Dihubungi</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->hubungi ?? '-'}}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 class="text-base font-semibold text-[#0970A5] mb-4 border-l-4 border-[#0970A5] pl-3">
                            Data Korban
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
                            <div>
                                <p class="text-gray-500">Nama Korban</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->nama_korban ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Jenis Kelamin</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->jenis_kelamin_korban ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Alamat</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->alamat_korban ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">No HP</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->phone_korban ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Status Korban</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->status_korban ?? '-'}}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 class="text-base font-semibold text-[#0970A5] mb-4 border-l-4 border-[#0970A5] pl-3">
                            Data Terlapor
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
                            <div>
                                <p class="text-gray-500">Nama Terlapor</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->nama_terlapor ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Jenis Kelamin</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->jenis_kelamin_terlapor ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Alamat</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->alamat_terlapor ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">No HP</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->phone_terlapor ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Status</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->status_terlapor ?? '-'}}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Karakteristik</p>
                                <p class="font-semibold text-gray-800">{{ $aduan->karakteristik_terlapor ?? '-'}}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 class="text-base font-semibold text-[#0970A5] mb-4 border-l-4 border-[#0970A5] pl-3">
                            Kronologi
                        </h2>

                        <div class="space-y-4 text-sm">
                            <div>
                                <p class="font-medium text-gray-700 mb-1">Kronologi</p>
                                <div class="bg-gray-50 border rounded-lg p-3 text-gray-700">
                                    {{ $aduan->chronology ?? '-'}}
                                </div>
                            </div>

                        </div>
                    </div>                   



                </div>

            </div>
                

            </div>

        </div>

    </div>


</body>
</html>
