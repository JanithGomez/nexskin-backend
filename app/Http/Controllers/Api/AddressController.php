<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedAddress;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return SavedAddress::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:billing,shipping',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'address_line' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        // if setting as default, unset other defaults for same type
        if (!empty($data['is_default'])) {
            SavedAddress::where('user_id', $request->user()->id)
                ->where('type', $data['type'])
                ->update(['is_default' => false]);
        }

        $saved = SavedAddress::create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
            'is_default' => (bool)($data['is_default'] ?? false),
        ]);

        return response()->json($saved, 201);
    }

    public function update(string $id, Request $request)
    {
        $addr = SavedAddress::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->validate([
            'type' => 'required|in:billing,shipping',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'address_line' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($data['is_default'])) {
            SavedAddress::where('user_id', $request->user()->id)
                ->where('type', $data['type'])
                ->where('id', '!=', $addr->id)
                ->update(['is_default' => false]);
        }

        $addr->update([
            'type' => $data['type'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
            'is_default' => (bool)($data['is_default'] ?? false),
        ]);

        return response()->json($addr);
    }

    public function destroy(string $id, Request $request)
    {
        $addr = SavedAddress::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $addr->delete();

        return response()->json(['message' => 'Deleted']);
    }
}