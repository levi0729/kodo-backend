<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Upload a file and return its metadata.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $storedName = Str::uuid() . '.' . $extension;

        $path = $file->storeAs('uploads/' . date('Y/m'), $storedName, 'public');

        $fileData = [
            'file_name'     => $originalName,
            'file_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'file_url'      => Storage::disk('public')->url($path),
            'storage_path'  => $path,
            'uploaded_by'   => Auth::id(),
        ];

        // Generate thumbnail dimensions for images
        if (Str::startsWith($file->getMimeType(), 'image/')) {
            $dimensions = @getimagesize($file->getRealPath());
            if ($dimensions) {
                $fileData['width']  = $dimensions[0];
                $fileData['height'] = $dimensions[1];
            }
        }

        return response()->json([
            'file' => $fileData,
        ], 201);
    }

    /**
     * Attach an uploaded file to a message.
     */
    public function attachToMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message_id' => 'required|integer|exists:messages,id',
            'file_name'  => 'required|string|max:255',
            'file_type'  => 'nullable|string|max:100',
            'file_size'  => 'nullable|integer',
            'file_url'   => 'required|string|max:500',
            'width'      => 'nullable|integer',
            'height'     => 'nullable|integer',
        ]);

        $attachment = MessageAttachment::create([
            'message_id' => $data['message_id'],
            'file_name'  => $data['file_name'],
            'file_type'  => $data['file_type'] ?? null,
            'file_size'  => $data['file_size'] ?? null,
            'file_url'   => $data['file_url'],
            'width'      => $data['width'] ?? null,
            'height'     => $data['height'] ?? null,
            'uploaded_by' => Auth::id(),
        ]);

        return response()->json(['attachment' => $attachment], 201);
    }

    /**
     * Delete an uploaded file.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $attachment = MessageAttachment::findOrFail($id);

        if ($attachment->uploaded_by !== Auth::id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $attachment->delete();

        return response()->json(['message' => 'File deleted.']);
    }
}
