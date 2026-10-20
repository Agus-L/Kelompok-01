<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        // Pengecekan manual keberadaan email membocorkan informasi pengguna terdaftar (User Enumeration).
        // if (! User::where('email', $credentials['email'])->exists()) {
        //     throw ValidationException::withMessages([
        //         'email' => 'Email tidak terdaftar.',
        //     ]);
        // }
        // alasan perubahan:
        // Memberikan pesan spesifik "Email tidak terdaftar" memungkinkan penyerang menebak dan mengidentifikasi email pengguna/admin/dosen yang terdaftar di sistem (User Enumeration).
        // Pemeriksaan kredensial seharusnya langsung ditangani sekaligus oleh Auth::attempt() tanpa membedakan respon antara email tidak ditemukan atau password salah.

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);

            // session()->regenerate() sebelumnya hilang setelah autentikasi berhasil.
            $request->session()->regenerate();
            // ALASAN DIGANTI:
            // $request->session()->regenerate() wajib dipanggil saat user berhasil login untuk mencegah serangan Session Fixation (pembajakan sesi),
            // yaitu dengan memperbarui ID sesi lama menjadi ID sesi baru yang aman.

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey);

        // throw ValidationException::withMessages([
        //     'email' => 'Password yang Anda masukkan salah.',
        // ]);
        // alasan perubahan
        // Pesan "Password yang Anda masukkan salah" membocorkan kepada penyerang bahwa email tersebut ada dan valid di database.
        // Seharusnya menggunakan pesan yang ambigu/generik untuk kegagalan autentikasi.

        throw ValidationException::withMessages([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
