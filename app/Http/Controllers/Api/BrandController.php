<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $status = $this->normalizeStatus($request->query('status'));

        $query = Brand::query()->orderByDesc('id');
        if (!is_null($status)) {
            $query->where('status', $status);
        }

        $brands = $query->get()->map(function (Brand $brand) {
            return $this->transform($brand);
        });

        return response()->json(['data' => $brands]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:150',
            'image' => 'nullable|image',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
        ]);

        $slug = $this->uniqueSlug($request->input('slug') ?: $request->name);
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->saveImage($request->file('image'), $slug);
        }
        $status = $this->normalizeStatus($request->input('status')) ?? 'active';

        $brand = Brand::create([
            'name' => $request->name,
            'slug' => $slug,
            'image' => $imagePath,
            'description' => $request->description,
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Brand created successfully',
            'data' => $this->transform($brand),
        ], 201);
    }

    public function show(Brand $brand)
    {
        return response()->json(['data' => $this->transform($brand)]);
    }

    public function update(Request $request, Brand $brand)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'slug' => 'nullable|string|max:150',
            'image' => 'sometimes|image',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
        ]);

        if ($request->filled('name')) {
            $brand->name = $request->name;
        }

        if ($request->filled('slug')) {
            $brand->slug = $this->uniqueSlug($request->slug, $brand->id);
        } elseif ($request->filled('name')) {
            $brand->slug = $this->uniqueSlug($request->name, $brand->id);
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($brand->image);
            $brand->image = $this->saveImage($request->file('image'), $brand->slug);
        }

        if ($request->filled('description')) {
            $brand->description = $request->description;
        }

        $status = $this->normalizeStatus($request->input('status'));
        if (!is_null($status)) {
            $brand->status = $status;
        }

        $brand->save();

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => $this->transform($brand),
        ]);
    }

    public function destroy(Brand $brand)
    {
        $this->deleteImage($brand->image);
        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $i = 1;

        while (
            Brand::where('slug', $slug)
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
        $uploadDir = public_path('uploads/brands');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $fileName = $slug . '-brand-' . time() . '.' . $extension;
        $file->move($uploadDir, $fileName);

        return 'uploads/brands/' . $fileName;
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

    private function transform(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'description' => $brand->description,
            'status' => $brand->status,
            'image_url' => $brand->image ? url($brand->image) : null,
            'created_at' => $brand->created_at,
            'updated_at' => $brand->updated_at,
        ];
    }
}
