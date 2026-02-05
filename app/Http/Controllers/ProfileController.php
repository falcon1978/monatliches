<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        if ($request->filled('profile_photo_cropped')) {
            $data = $request->input('profile_photo_cropped');
            if (preg_match('/^data:image\\/\\w+;base64,/', $data)) {
                $data = substr($data, strpos($data, ',') + 1);
            }
            $binary = base64_decode($data, true);
            if ($binary !== false) {
                if ($user->profile_photo_path) {
                    Storage::disk('public')->delete($user->profile_photo_path);
                }

                $filename = 'profile-photos/user-'.$user->id.'-'.now()->timestamp.'.webp';
                Storage::disk('public')->put($filename, $binary);
                $user->profile_photo_path = $filename;
            }
        } elseif ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $image = @imagecreatefromstring($file->get());
            if ($image) {
                $size = 256;
                $width = imagesx($image);
                $height = imagesy($image);
                $minSide = min($width, $height);
                $srcX = (int) floor(($width - $minSide) / 2);
                $srcY = (int) floor(($height - $minSide) / 2);

                $canvas = imagecreatetruecolor($size, $size);
                imagecopyresampled($canvas, $image, 0, 0, $srcX, $srcY, $size, $size, $minSide, $minSide);

                if ($user->profile_photo_path) {
                    Storage::disk('public')->delete($user->profile_photo_path);
                }

                $filename = 'profile-photos/user-'.$user->id.'-'.now()->timestamp.'.webp';
                ob_start();
                imagewebp($canvas, null, 82);
                $binary = ob_get_clean();
                if ($binary !== false) {
                    Storage::disk('public')->put($filename, $binary);
                    $user->profile_photo_path = $filename;
                }

                imagedestroy($canvas);
                imagedestroy($image);
            }
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
