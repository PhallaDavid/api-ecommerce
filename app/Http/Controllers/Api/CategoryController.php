<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * List categories (optional status filter)
     */
    public function index(Request $request)
    {
        $status = $this->normalizeStatus($request->query('status'));

        $query = Category::query()->orderByDesc('id');
        if (!is_null($status)) {
            $query->where('is_active', $status);
        }

        $categories = $query->get()->map(function (Category $category) {
            return $this->transform($category);
        });

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Create category (name, image, description, status)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'image' => 'nullable|image',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->saveImage($request->file('image'), $request->name);
        }

        $isActive = $this->normalizeStatus($request->input('status'));

        $category = Category::create([
            'name' => $request->name,
            'image_path' => $imagePath,
            'description' => $request->description,
            'is_active' => is_null($isActive) ? true : $isActive,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $this->transform($category),
        ], 201);
    }

    /**
     * Show single category
     */
    public function show(Category $category)
    {
        return response()->json([
            'data' => $this->transform($category),
        ]);
    }

    /**
     * Update category
     */
    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'image' => 'sometimes|image',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
        ]);

        if ($request->filled('name')) {
            $category->name = $request->name;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($category->image_path);
            $category->image_path = $this->saveImage($request->file('image'), $category->name);
        }

        if ($request->filled('description')) {
            $category->description = $request->description;
        }

        $status = $this->normalizeStatus($request->input('status'));
        if (!is_null($status)) {
            $category->is_active = $status;
        }

        $category->save();

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $this->transform($category),
        ]);
    }

    /**
     * Delete category
     */
    public function destroy(Category $category)
    {
        $this->deleteImage($category->image_path);
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    private function saveImage($file, string $name): string
    {
        $uploadDir = public_path('uploads/categories');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $fileName = Str::slug($name) . '-category-' . time() . '.' . $extension;
        $file->move($uploadDir, $fileName);

        return 'uploads/categories/' . $fileName;
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

    private function normalizeStatus($value): ?bool
    {
        if (is_null($value)) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        $value = strtolower(trim((string) $value));
        if (in_array($value, ['active', 'true', '1'], true)) {
            return true;
        }
        if (in_array($value, ['inactive', 'false', '0'], true)) {
            return false;
        }

        return null;
    }

    private function transform(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'status' => $category->is_active ? 'active' : 'inactive',
            'is_active' => (bool) $category->is_active,
            'image_url' => $category->image_path ? url($category->image_path) : null,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }
}
