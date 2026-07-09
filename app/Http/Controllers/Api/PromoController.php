<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromoController extends Controller
{
    // READ: Ambil semua promo
    public function index()
    {
        $promos = Promo::orderBy('id', 'asc')->get();
        return response()->json(['success' => true, 'data' => $promos], 200);
    }

    // READ: Ambil detail 1 promo
    public function show($id)
    {
        $promo = Promo::find($id);

        if (!$promo) {
            return response()->json(['success' => false, 'message' => 'Promo tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $promo], 200);
    }

    // CREATE: Tambah promo baru
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'harga_promo'          => 'required|numeric|min:0',
            'persentase_promo'     => 'required|numeric|min:0|max:100',
            'tanggal_berlaku_promo'=> 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $promo = Promo::create($request->only([
            'harga_promo',
            'persentase_promo',
            'tanggal_berlaku_promo',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Promo berhasil ditambahkan',
            'data'    => $promo,
        ], 201);
    }

    // UPDATE: Edit promo
    public function update(Request $request, $id)
    {
        $promo = Promo::find($id);

        if (!$promo) {
            return response()->json(['success' => false, 'message' => 'Promo tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'harga_promo'          => 'sometimes|required|numeric|min:0',
            'persentase_promo'     => 'sometimes|required|numeric|min:0|max:100',
            'tanggal_berlaku_promo'=> 'sometimes|required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $promo->update($request->only([
            'harga_promo',
            'persentase_promo',
            'tanggal_berlaku_promo',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Promo berhasil diupdate',
            'data'    => $promo,
        ], 200);
    }

    // DELETE: Hapus promo
    public function destroy($id)
    {
        $promo = Promo::find($id);

        if (!$promo) {
            return response()->json(['success' => false, 'message' => 'Promo tidak ditemukan'], 404);
        }

        $promo->delete();

        return response()->json(['success' => true, 'message' => 'Promo berhasil dihapus'], 200);
    }
}
