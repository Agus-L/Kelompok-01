<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // public function index(Request $request)
    // {
    //     $query = User::query();
    // Method index pada UserController.php tidak memanggil Gate::authorize('viewAny', User::class);, sehingga mahasiswa atau pengguna non-admin dapat mengakses endpoint /users dan melihat seluruh data sensitif pengguna lain (terjadi kebocoran data pengguna), yang seharusnya dicegah dengan menambahkan otorisasi policy di awal method index.
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('email', 'like', '%'.$request->search.'%')
                  ->orWhere('nim_nip', 'like', '%'.$request->search.'%');
            });
        }

        $users = $query->paginate(15)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        Gate::authorize('viewAny', User::class);

        return view('users.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'nim_nip' => 'nullable|string|unique:users,nim_nip',
            'role' => 'required|in:admin,dosen,mahasiswa',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'nim_nip' => $validated['nim_nip'] ?? null,
        ]);

        $user->role = $validated['role'];
        $user->save();

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dibuat.');
    }

    public function edit(User $user)
    {
        Gate::authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'nim_nip' => ['nullable', 'string', Rule::unique('users', 'nim_nip')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->nim_nip = $validated['nim_nip'] ?? null;

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // if ($request->has('role')) {
        //     $request->validate(['role' => 'required|in:admin,dosen,mahasiswa']);
        //     $user->role = $request->role;
        // }
        // Blok pembaruan role pada method update di UserController.php langsung mengubah role tanpa memverifikasi kemampuan updateRole, sehingga pengguna biasa (seperti mahasiswa) dapat menyisipkan input role=admin saat mengedit profil mereka sendiri untuk menaikkan hak akses (privilege escalation), yang seharusnya dibatasi dengan menambahkan pengecekan Gate::allows('updateRole', $user).
        // Hanya admin yang boleh mengubah role pengguna
        if ($request->has('role') && Gate::allows('updateRole', $user)) {
            $request->validate(['role' => 'required|in:admin,dosen,mahasiswa']);
            $user->role = $request->role;
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus.');
    }
}
