<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class BannerController extends Controller
{
    /**
     * List banners (optionally filter by status)
     */
    public function index(Request $request)
    {
        $status = $this->normalizeStatus($request->query('status'));

        $query = Banner::query()->orderByDesc('id');
        if (!is_null($status)) {
            $query->where('is_active', $status);
        }

        $banners = $query->get()->map(function (Banner $banner) {
            return $this->transform($banner);
        });

        return response()->json([
            'data' => $banners
        ]);
    }

    /**
     * Create banner (upload image + title + status)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:150',
            'image' => 'required|image',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
        ]);

        $imagePath = $this->saveImage($request->file('image'), $request->title);
        $isActive = $this->normalizeStatus($request->input('status'));

        $banner = Banner::create([
            'title' => $request->title,
            'image_path' => $imagePath,
            'is_active' => is_null($isActive) ? true : $isActive,
        ]);

        return response()->json([
            'message' => 'Banner created successfully',
            'data' => $this->transform($banner),
        ], 201);
    }

    /**
     * Show single banner
     */
    public function show(Banner $banner)
    {
        return response()->json([
            'data' => $this->transform($banner),
        ]);
    }

    /**
     * Update banner (title, image, status)
     */
    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'image' => 'sometimes|image',
            'status' => 'nullable|in:active,inactive,1,0,true,false',
        ]);

        if ($request->filled('title')) {
            $banner->title = $request->title;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($banner->image_path);
            $banner->image_path = $this->saveImage($request->file('image'), $banner->title);
        }

        $status = $this->normalizeStatus($request->input('status'));
        if (!is_null($status)) {
            $banner->is_active = $status;
        }

        $banner->save();

        return response()->json([
            'message' => 'Banner updated successfully',
            'data' => $this->transform($banner),
        ]);
    }

    /**
     * Delete banner
     */
    public function destroy(Banner $banner)
    {
        $this->deleteImage($banner->image_path);
        $banner->delete();

        return response()->json([
            'message' => 'Banner deleted successfully',
        ]);
    }

    private function saveImage($file, string $title): string
    {
        $uploadDir = public_path('uploads/banners');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $fileName = Str::slug($title) . '-banner-' . time() . '.' . $extension;
        $file->move($uploadDir, $fileName);

        return 'uploads/banners/' . $fileName;
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

    private function transform(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'status' => $banner->is_active ? 'active' : 'inactive',
            'is_active' => (bool) $banner->is_active,
            'image_url' => url($banner->image_path),
            'created_at' => $banner->created_at,
            'updated_at' => $banner->updated_at,
        ];
    }
}
