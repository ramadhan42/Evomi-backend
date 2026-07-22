<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\ContactReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactMessageController extends Controller
{
    /**
     * Menampilkan semua data pesan beserta riwayat balasannya untuk Admin.
     */
    /**
     * Menampilkan data pesan.
     * Filter berdasarkan email untuk User, atau tampilkan semua untuk Admin.
     */
    public function index(Request $request)
    {
        try {
            $email = $request->query('email');

            // Tanpa email = daftar semua pesan → hanya admin (Bearer token)
            if (!$email) {
                $user = auth('sanctum')->user();
                if (!$user || !$user->is_admin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akses ditolak. Login sebagai admin atau sertakan parameter email.',
                    ], 403);
                }

                $messages = ContactMessage::with('replies')
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                $messages = ContactMessage::with('replies')
                    ->where('email', $email)
                    ->orderBy('created_at', 'asc')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengambil data pesan.',
                'data' => $messages
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Menampilkan pesan berdasarkan filter email tertentu atau semua.
     */
    public function show(Request $request)
    {
        $email = $request->query('email');

        try {
            if ($email) {
                $messages = ContactMessage::with('replies')
                    ->where('email', $email)
                    ->orderBy('created_at', 'asc')
                    ->get();
            } else {
                $messages = ContactMessage::with('replies')
                    ->orderBy('created_at', 'asc')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $messages
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan pesan baru dari form contact website (Public).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        try {
            $contact = ContactMessage::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Pesan berhasil dikirim! Tim kami akan segera menghubungi Anda.',
                'data' => $contact
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengirim pesan. Silakan coba lagi.'
            ], 500);
        }
    }

    /**
     * Fungsi POST untuk membalas pesan pelanggan berkali-kali (Admin Only).
     * Endpoint: POST /api/admin/contact/{id}/reply
     */
    public function reply(Request $request, $id)
    {
        $validated = $request->validate([
            'reply_message' => 'required|string',
        ]);

        try {
            $message = ContactMessage::findOrFail($id);

            // Membuat record baru ke tabel contact_replies tanpa membatasi jumlah baris
            $reply = $message->replies()->create([
                'reply_message' => $validated['reply_message'],
                'replied_by' => Auth::id() ?? 1 // Fallback ke ID 1 jika belum ter-auth penuh
            ]);

            // Load ulang relasi agar response mengembalikan thread chat terbaru lengkap
            $message->load('replies');

            return response()->json([
                'success' => true,
                'message' => 'Balasan berhasil dikirim.',
                'data' => $message
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengirim balasan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengambil jumlah balasan admin yang belum dibaca oleh user.
     */
    public function getUnreadCount(Request $request)
    {
        $email = $request->query('email');
        if (!$email) {
            return response()->json(['count' => 0]);
        }

        // GUNAKAN false (bukan 0) UNTUK POSTGRESQL
        $count = ContactReply::where(function ($q) {
            $q->where('is_read_by_user', false)
                ->orWhereNull('is_read_by_user');
        })
            ->whereHas('contactMessage', function ($query) use ($email) {
                $query->where('email', $email);
            })->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Menandai semua balasan admin sebagai "Telah Dibaca" oleh user.
     */
    /**
     * Menandai semua balasan admin sebagai "Telah Dibaca" oleh user.
     */
    public function markUserRead(Request $request)
    {
        $email = $request->input('email');
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email tidak valid']);
        }

        try {
            $messageIds = ContactMessage::where('email', $email)->pluck('id');

            ContactReply::whereIn('contact_message_id', $messageIds)
                ->where(function ($q) {
                    // PASTIKAN MENGGUNAKAN false DI SINI UNTUK POSTGRESQL
                    $q->where('is_read_by_user', false)
                        ->orWhereNull('is_read_by_user');
                })
                // PASTIKAN MENGGUNAKAN true DI SINI UNTUK POSTGRESQL
                ->update(['is_read_by_user' => true]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}