<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $status = $this->normalizeStatus($request->query('status'));

        $query = Product::query()->orderByDesc('id');
        if (!is_null($status)) {
            $query->where('status', $status);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->filled('is_featured')) {
            $query->where('is_featured', (bool) $request->is_featured);
        }

        $products = $query->get()->map(function (Product $product) {
            return $this->transform($product);
        });

        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:180',
            'sku' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'cost_price' => 'nullable|numeric',
            'promotion_percent' => 'nullable|integer|min:0|max:100',
            'promotion_start_date' => 'nullable|date',
            'promotion_end_date' => 'nullable|date',
            'stock' => 'nullable|integer|min:0',
            'is_in_stock' => 'nullable|in:true,false,1,0',
            'image' => 'nullable|image',
            'gallery' => 'nullable',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_featured' => 'nullable|in:true,false,1,0',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
        ]);

        $slug = $this->uniqueSlug($request->input('slug') ?: $request->name);
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->saveImage($request->file('image'), $slug);
        }
        $gallery = $this->saveGallery($request, $slug);
        $status = $this->normalizeStatus($request->input('status')) ?? 'active';

        $product = Product::create([
            'name' => $request->name,
            'slug' => $slug,
            'sku' => $request->sku,
            'description' => $request->description,
            'short_description' => $request->short_description,
            'price' => $request->price ?? 0,
            'sale_price' => $request->sale_price,
            'cost_price' => $request->cost_price,
            'promotion_percent' => $request->promotion_percent,
            'promotion_start_date' => $request->promotion_start_date,
            'promotion_end_date' => $request->promotion_end_date,
            'stock' => $request->stock ?? 0,
            'is_in_stock' => $request->filled('is_in_stock') ? (bool) $request->is_in_stock : true,
            'image' => $imagePath,
            'gallery' => $gallery,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'is_featured' => $request->filled('is_featured') ? (bool) $request->is_featured : false,
            'status' => $status,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $this->transform($product),
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json(['data' => $this->transform($product)]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'slug' => 'nullable|string|max:180',
            'sku' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'cost_price' => 'nullable|numeric',
            'promotion_percent' => 'nullable|integer|min:0|max:100',
            'promotion_start_date' => 'nullable|date',
            'promotion_end_date' => 'nullable|date',
            'stock' => 'nullable|integer|min:0',
            'is_in_stock' => 'nullable|in:true,false,1,0',
            'image' => 'sometimes|image',
            'gallery' => 'nullable',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_featured' => 'nullable|in:true,false,1,0',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
        ]);

        if ($request->filled('name')) {
            $product->name = $request->name;
        }

        if ($request->filled('slug')) {
            $product->slug = $this->uniqueSlug($request->slug, $product->id);
        } elseif ($request->filled('name')) {
            $product->slug = $this->uniqueSlug($request->name, $product->id);
        }

        if ($request->filled('sku')) {
            $product->sku = $request->sku;
        }

        foreach ([
            'description',
            'short_description',
            'price',
            'sale_price',
            'cost_price',
            'promotion_percent',
            'promotion_start_date',
            'promotion_end_date',
            'stock',
            'category_id',
            'brand_id',
            'meta_title',
            'meta_description',
        ] as $field) {
            if ($request->has($field)) {
                $product->{$field} = $request->input($field);
            }
        }

        if ($request->has('is_in_stock')) {
            $product->is_in_stock = (bool) $request->is_in_stock;
        }

        if ($request->has('is_featured')) {
            $product->is_featured = (bool) $request->is_featured;
        }

        $status = $this->normalizeStatus($request->input('status'));
        if (!is_null($status)) {
            $product->status = $status;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $product->image = $this->saveImage($request->file('image'), $product->slug);
        }

        if ($request->has('gallery')) {
            $this->deleteGallery($product->gallery);
            $product->gallery = $this->saveGallery($request, $product->slug);
        }

        $product->save();

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $this->transform($product),
        ]);
    }

    public function destroy(Product $product)
    {
        $this->deleteImage($product->image);
        $this->deleteGallery($product->gallery);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $i = 1;

        while (
            Product::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function saveImage($file, string $slug): string
    {
        $uploadDir = public_path('uploads/products');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $fileName = $slug . '-product-' . time() . '.' . $extension;
        $file->move($uploadDir, $fileName);

        return 'uploads/products/' . $fileName;
    }

    private function saveGallery(Request $request, string $slug): ?array
    {
        $gallery = [];

        if ($request->hasFile('gallery')) {
            $files = $request->file('gallery');
            if (is_array($files)) {
                foreach ($files as $file) {
                    $gallery[] = $this->saveImage($file, $slug . '-gallery');
                }
            }
        } elseif (is_array($request->input('gallery'))) {
            $gallery = $request->input('gallery');
        }

        return empty($gallery) ? null : $gallery;
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

    private function deleteGallery($gallery): void
    {
        if (empty($gallery) || !is_array($gallery)) {
            return;
        }
        foreach ($gallery as $path) {
            $this->deleteImage($path);
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

    private function transform(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'cost_price' => $product->cost_price,
            'promotion_percent' => $product->promotion_percent,
            'promotion_start_date' => $product->promotion_start_date,
            'promotion_end_date' => $product->promotion_end_date,
            'stock' => $product->stock,
            'is_in_stock' => (bool) $product->is_in_stock,
            'image_url' => $product->image ? url($product->image) : null,
            'gallery' => $product->gallery,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'is_featured' => (bool) $product->is_featured,
            'status' => $product->status,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }
}
