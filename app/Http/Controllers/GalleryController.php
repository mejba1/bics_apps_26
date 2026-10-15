<?php

namespace App\Http\Controllers;

use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Support\Thumb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Admin > Gallery: albums (e.g. "Spain 2026") and their photos. Everything is saved over AJAX.
 */
class GalleryController extends Controller
{
    public function index(){
        $albums = GalleryAlbum::with('photos')->orderBy('sort')->orderBy('id')->get();
        return view('backend.pages.gallery-manager', [
            'albums' => $albums->map(fn ($a) => $this->albumJson($a, true))->values(),
        ]);
    }

    /* ---------------- Albums ---------------- */

    public function storeAlbum(Request $request){
        $data = $request->validate(['title' => 'required|string|max:150|unique:gallery_albums,title'],
            ['title.unique' => 'An album with this title already exists.']);
        $album = GalleryAlbum::create([
            'title' => trim($data['title']),
            'sort' => (int) GalleryAlbum::max('sort') + 1,
            'is_active' => true,
        ]);
        return response()->json(['success' => true, 'album' => $this->albumJson($album, true)]);
    }

    public function updateAlbum(Request $request, GalleryAlbum $album){
        $data = $request->validate(['title' => 'required|string|max:150|unique:gallery_albums,title,' . $album->id],
            ['title.unique' => 'An album with this title already exists.']);
        $album->update(['title' => trim($data['title'])]);
        return response()->json(['success' => true, 'album' => $this->albumJson($album)]);
    }

    public function toggleAlbum(GalleryAlbum $album){
        $album->update(['is_active' => !$album->is_active]);
        return response()->json(['success' => true, 'album' => $this->albumJson($album)]);
    }

    public function destroyAlbum(GalleryAlbum $album){
        $count = $album->photos()->count();
        foreach ($album->photos as $photo) $this->deleteFile($photo);
        $album->delete(); // photos removed with it
        return response()->json(['success' => true, 'removed_photos' => $count]);
    }

    public function reorderAlbums(Request $request){
        $this->reorder(GalleryAlbum::query(), $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids']);
        return response()->json(['success' => true]);
    }

    /* ---------------- Photos ---------------- */

    /** One photo per request (the page uploads several in a row and shows progress). */
    public function upload(Request $request, GalleryAlbum $album){
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp|max:25600'], [
            'file.max' => 'This photo is larger than 25 MB.',
            'file.mimes' => 'Only JPG, PNG or WEBP photos can be uploaded.',
        ]);
        $file = $request->file('file');
        $dir = 'upload/gallery/' . $album->id;
        File::ensureDirectoryExists(base_path($dir));
        $ext = strtolower($file->getClientOriginalExtension()) === 'png' ? 'png' : 'jpg';
        $base = Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'photo', 60, '');
        $path = $dir . '/' . $base . '-' . Str::random(6) . '.' . $ext;

        // Store a web-sized copy (max 2400px), not the 6000px camera original
        $size = Thumb::shrink($file->getRealPath(), base_path($path));
        if (!$size) {
            return response()->json(['message' => 'This file could not be read as an image.', 'errors' => ['file' => ['This file could not be read as an image.']]], 422);
        }

        $photo = $album->photos()->create([
            'image' => $path,
            'caption' => null,
            'width' => $size[0],
            'height' => $size[1],
            'sort' => (int) $album->photos()->max('sort') + 1,
        ]);
        foreach ([480, 800] as $w) Thumb::make($path, $w); // tiles ready straight away
        Thumb::placeholder($path);                           // blurred preview too

        return response()->json(['success' => true, 'photo' => $this->photoJson($photo)]);
    }

    public function updatePhoto(Request $request, GalleryPhoto $photo){
        $data = $request->validate([
            'caption' => 'nullable|string|max:255',
            'album_id' => 'nullable|integer|exists:gallery_albums,id',
        ]);
        $changes = ['caption' => isset($data['caption']) ? trim($data['caption']) : $photo->caption];
        if (!empty($data['album_id']) && (int) $data['album_id'] !== $photo->album_id) {
            $changes['album_id'] = (int) $data['album_id'];
            $changes['sort'] = (int) GalleryPhoto::where('album_id', $data['album_id'])->max('sort') + 1;
        }
        $photo->update($changes);
        return response()->json(['success' => true, 'photo' => $this->photoJson($photo)]);
    }

    public function destroyPhoto(GalleryPhoto $photo){
        $this->deleteFile($photo);
        $photo->delete();
        return response()->json(['success' => true]);
    }

    public function reorderPhotos(Request $request, GalleryAlbum $album){
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $this->reorder(GalleryPhoto::where('album_id', $album->id), $ids);
        return response()->json(['success' => true]);
    }

    /* ---------------- Helpers ---------------- */

    private function reorder($query, array $ids): void
    {
        DB::transaction(function () use ($query, $ids) {
            foreach (array_values($ids) as $i => $id) (clone $query)->where('id', $id)->update(['sort' => $i + 1]);
        });
    }

    /** Only files uploaded here are deleted; the original site photos in assets/ are never removed. */
    private function deleteFile(GalleryPhoto $photo): void
    {
        if ($photo->is_uploaded && is_file(base_path($photo->image))) {
            Thumb::forget($photo->image);
            File::delete(base_path($photo->image));
        }
    }

    private function albumJson(GalleryAlbum $a, bool $withPhotos = false): array
    {
        $json = ['id' => $a->id, 'title' => $a->title, 'is_active' => (bool) $a->is_active];
        if ($withPhotos) $json['photos'] = $a->photos->map(fn ($p) => $this->photoJson($p))->values();
        return $json;
    }

    private function photoJson(GalleryPhoto $p): array
    {
        return ['id' => $p->id, 'album_id' => $p->album_id, 'caption' => $p->caption, 'thumb' => Thumb::url($p->image, 480), 'full' => Thumb::url($p->image, 1600)];
    }
}
