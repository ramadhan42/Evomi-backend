<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken; // Pastikan ini di-import

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Kredensial salah.']]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json(['user' => $user, 'token' => $token]);
    }

    /**
     * Lupa Password: Memperbarui password berdasarkan email.
     */
    public function forgotPassword(Request $request)
    {
        // 1. Validasi input dari request
        $request->validate([
            'email' => 'required|email|exists:users,email', // Pastikan email valid dan ada di database
            'password' => 'required|string|min:8', // Anda bisa menambahkan aturan 'confirmed' jika menggunakan form konfirmasi password
        ], [
            'email.exists' => 'Email tidak ditemukan di sistem kami.',
        ]);

        // 2. Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // 3. Update password user (jangan lupa di-hash)
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // 4. Kembalikan response sukses
        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui. Silakan login dengan password baru Anda.'
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Logout khusus via navigator.sendBeacon
     */
    public function logoutBeacon(Request $request)
    {
        // Ambil token string dari body request FormData
        $tokenString = $request->input('token');

        if ($tokenString) {
            // Karena plainTextToken Sanctum biasanya berbentuk "id|token_hash", 
            // kita cari token asli tersebut menggunakan static method findToken dari Sanctum
            $token = PersonalAccessToken::findToken($tokenString);

            if ($token) {
                // Hapus token dari database (menghancurkan sesi login)
                $token->delete();
                return response()->json(['status' => 'success', 'message' => 'Beacon logout successful']);
            }
        }

        return response()->json(['status' => 'error', 'message' => 'Token invalid or not found'], 400);
    }
}