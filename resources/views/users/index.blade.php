<x-layout title="Kelola Pengguna">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Kelola Pengguna</h1>
            <p class="text-sm text-gray-500">Daftar seluruh akun pengguna (Admin, Dosen, Mahasiswa)</p>
        </div>
        <a href="{{ route('users.create') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg shadow transition">
            Tambah Pengguna
        </a>
    </div>

    <!-- User Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-500 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4">NIM / NIP</th>
                    <th class="px-6 py-4">Nama</th>
                    <th class="px-6 py-4">Email</th>
                    <th class="px-6 py-4">Peran (Role)</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($users as $user)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 font-mono text-xs">{{ $user->nim_nip ?? '-' }}</td>
                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $user->name }}</td>
                    <td class="px-6 py-4">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full capitalize
                            {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-800' : ($user->role === 'dosen' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800') }}">
                            {{ $user->role }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="{{ route('users.edit', $user) }}" class="text-amber-600 hover:underline">Edit</a>
                        @if($user->id !== auth()->id())
                        <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Hapus pengguna ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</x-layout>
