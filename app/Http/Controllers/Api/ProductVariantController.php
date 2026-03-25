<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductVariantController extends Controller
{
    public function index(Product $product)
    {
        $variants = ProductVariant::where('product_id', $product->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (ProductVariant $variant) {
                return $this->transform($variant);
            });

        return response()->json(['data' => $variants]);
    }

    public function store(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'sku' => 'nullable|string|max:100',
            'price' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'stock' => 'nullable|integer|min:0',
            'is_in_stock' => 'nullable|in:true,false,1,0',
            'image' => 'nullable|image',
            'options' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->saveImage($request->file('image'), $product->slug);
        }
        $status = $this->normalizeStatus($request->input('status')) ?? 'active';

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => $request->name,
            'sku' => $request->sku,
            'price' => $request->price ?? 0,
            'sale_price' => $request->sale_price,
            'stock' => $request->stock ?? 0,
            'is_in_stock' => $request->filled('is_in_stock') ? (bool) $request->is_in_stock : true,
            'image' => $imagePath,
            'options' => $request->options,
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Variant created successfully',
            'data' => $this->transform($variant),
        ], 201);
    }

    public function show(Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['message' => 'Variant not found'], 404);
        }

        return response()->json(['data' => $this->transform($variant)]);
    }

    public function update(Request $request, Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['message' => 'Variant not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'sku' => 'nullable|string|max:100',
            'price' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'stock' => 'nullable|integer|min:0',
            'is_in_stock' => 'nullable|in:true,false,1,0',
            'image' => 'sometimes|image',
            'options' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
        ]);

        if ($request->filled('name')) {
            $variant->name = $request->name;
        }
        if ($request->filled('sku')) {
            $variant->sku = $request->sku;
        }

        foreach (['price', 'sale_price', 'stock', 'options'] as $field) {
            if ($request->has($field)) {
                $variant->{$field} = $request->input($field);
            }
        }

        if ($request->has('is_in_stock')) {
            $variant->is_in_stock = (bool) $request->is_in_stock;
        }

        $status = $this->normalizeStatus($request->input('status'));
        if (!is_null($status)) {
            $variant->status = $status;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($variant->image);
            $variant->image = $this->saveImage($request->file('image'), $product->slug);
        }

        $variant->save();

        return response()->json([
            'message' => 'Variant updated successfully',
            'data' => $this->transform($variant),
        ]);
    }

    public function destroy(Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['message' => 'Variant not found'], 404);
        }

        $this->deleteImage($variant->image);
        $variant->delete();

        return response()->json(['message' => 'Variant deleted successfully']);
    }

    private function saveImage($file, string $slug): string
    {
        $uploadDir = public_path('uploads/variants');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $fileName = Str::slug($slug) . '-variant-' . time() . '.' . $extension;
        $file->move($uploadDir, $fileName);

        return 'uploads/variants/' . $fileName;
    }

    private function deleteImage(?string $path): void
    {
        if (!$path) {
            return;
        }
        $fullPath = public_path($path);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }

    private function normalizeStatus($value): ?string
    {
        if (is_null($value)) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'active' : 'inactive';
        }
        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 'active' : 'inactive';
        }
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['active', 'true', '1'], true)) {
            return 'active';
        }
        if (in_array($value, ['inactive', 'false', '0'], true)) {
            return 'inactive';
        }
        return null;
    }

    private function transform(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'price' => $variant->price,
            'sale_price' => $variant->sale_price,
            'stock' => $variant->stock,
            'is_in_stock' => (bool) $variant->is_in_stock,
            'image_url' => $variant->image ? url($variant->image) : null,
            'options' => $variant->options,
            'status' => $variant->status,
            'created_at' => $variant->created_at,
            'updated_at' => $variant->updated_at,
        ];
    }
}
