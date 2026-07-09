<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KurirController extends Controller
{
    // READ: Ambil semua kurir
    public function index()
    {
        $kurirs = Kurir::orderBy('id', 'asc')->get();
        return response()->json(['success' => true, 'data' => $kurirs], 200);
    }

    // READ: Ambil detail 1 kurir
    public function show($id)
    {
        $kurir = Kurir::find($id);

        if (!$kurir) {
            return response()->json(['success' => false, 'message' => 'Kurir tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $kurir], 200);
    }

    // CREATE: Tambah kurir baru
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'jenis' => 'required|string|max:100',
            'harga' => 'required|numeric|min:0',
            'destinasi' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $kurir = Kurir::create($request->only(['nama', 'jenis', 'harga', 'destinasi']));

        return response()->json([
            'success' => true,
            'message' => 'Kurir berhasil ditambahkan',
            'data' => $kurir,
        ], 201);
    }

    // UPDATE: Edit kurir
    public function update(Request $request, $id)
    {
        $kurir = Kurir::find($id);

        if (!$kurir) {
            return response()->json(['success' => false, 'message' => 'Kurir tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|required|string|max:255',
            'jenis' => 'sometimes|required|string|max:100',
            'harga' => 'sometimes|required|numeric|min:0',
            'destinasi' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $kurir->update($request->only(['nama', 'jenis', 'harga', 'destinasi']));

        return response()->json([
            'success' => true,
            'message' => 'Kurir berhasil diupdate',
            'data' => $kurir,
        ], 200);
    }

    // DELETE: Hapus kurir
    public function destroy($id)
    {
        $kurir = Kurir::find($id);

        if (!$kurir) {
            return response()->json(['success' => false, 'message' => 'Kurir tidak ditemukan'], 404);
        }

        $kurir->delete();

        return response()->json(['success' => true, 'message' => 'Kurir berhasil dihapus'], 200);
    }
}
