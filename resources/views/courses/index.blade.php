<x-layout title="Daftar Mata Kuliah">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Daftar Mata Kuliah</h1>
        <a href="{{ route('courses.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">Tambah Mata Kuliah</a>
    </div>

    {{--
        [MASALAH - Logika di View] Kode di bawah ini adalah logika penyaringan data (array_filter)
        yang sebelumnya ditulis langsung di View. Ini tidak benar, karena View hanya boleh
        menampilkan data, bukan mengolahnya.
    --}}
    {{--
        [FIX] Logika ini sudah dipindahkan ke CourseController@index().
        View sekarang hanya menerima variabel $activeCourses yang sudah siap ditampilkan.
    --}}
    {{-- Kode lama yang keliru (dikomentari sebagai bukti):
    @php
        $activeCourses = array_filter($courses, function($c) {
            return $c['status'] === 'active';
        });
    @endphp
    --}}

    <table class="w-full bg-white rounded shadow overflow-hidden">
        <thead class="bg-gray-200 text-left">
            <tr>
                <th class="p-3">Kode</th>
                <th class="p-3">Nama Mata Kuliah</th>
                <th class="p-3">SKS</th>
                <th class="p-3">Dosen</th>
                <th class="p-3">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activeCourses as $course)
            <tr class="border-b">
                <td class="p-3">{{ $course['code'] }}</td>
                <td class="p-3">
                    {{--
                        [MASALAH - URL Hardcode] URL ditulis langsung: /courses/{{ id }}.
                        Jika nama route berubah, semua link harus diubah satu per satu secara manual.
                    --}}
                    {{-- Kode lama yang keliru (dikomentari sebagai bukti):
                    <a href="/courses/{{ $course['id'] }}" class="text-blue-600 font-semibold hover:underline">
                        {{ $course['name'] }}
                    </a>
                    --}}
                    {{--
                        [FIX] Gunakan helper route() dengan nama route yang sudah didefinisikan.
                        Jika URL berubah di web.php, semua link otomatis ikut berubah.
                    --}}
                     <a href="{{ route('courses.show', $course['id']) }}" class="text-blue-600 font-semibold hover:underline">
                        {{ $course['name'] }} 
                    </a> 
                </td>
                <td class="p-3">{{ $course['sks'] }}</td>
                <td class="p-3">{{ $course['lecturer'] }}</td>
                <td class="p-3 space-x-2">
                    {{--
                        [FIX - URL Hardcode] Link Detail sebelumnya juga hardcode: /courses/{{ id }}.
                        Sudah diperbaiki menggunakan named route route('courses.show', ...).
                    --}}
                    <a href="{{ route('courses.show', $course['id']) }}" class="text-gray-600 hover:underline">Detail</a>
                    {{--
                        [MASALAH - HTTP Method] Sebelumnya tombol Hapus adalah link biasa (<a href>)
                        dengan URL /courses/{id}/delete menggunakan method GET.
                        Ini berbahaya karena hanya dengan membuka link, data bisa terhapus.
                    --}}
                    {{-- Kode lama yang keliru (dikomentari sebagai bukti):
                    <a href="/courses/{{ $course['id'] }}/delete" class="text-red-600 hover:underline" onclick="return confirm('Hapus?')">Hapus</a>
                    --}}
                    {{--
                        [FIX - HTTP Method] Hapus sekarang menggunakan <form> dengan method POST
                        dan @method('DELETE') agar Laravel tahu ini request DELETE.
                        @csrf ditambahkan untuk mencegah serangan CSRF (form palsu dari situs lain).
                    --}}
                    <form action="{{ route('courses.destroy', $course['id']) }}" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="text-red-600 hover:underline"
                                onclick="return confirm('Hapus?')">
                            Hapus
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</x-layout>