<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LupaPasswordController extends Controller
{
    // fungsi show halaman kirim link reset
    public function showHalamanLupaPassword()
    {
        return view('auth.lupa_password');
    }

    // fungsi kirim link reset
    public function kirimLinkReset(ForgotPasswordRequest $request)
    {
        // membuat token unik
        $token = Str::random(64);

        // menyimpan token ke dalam tabel
        DB::table('password_reset_tokens')->updateOrInsert([
            'email' => $request->email,
        ], [
            'token' => bcrypt($token),
            'created_at' => Carbon::now()
        ]);

        // kirim email
        Mail::send('emails.lupa-password', ['token' => $token, 'email' => $request->email], function ($message) use($request) {
            $message->to($request->email);
            $message->subject('Notifikasi Reset Password');
        });

        // respon ke user
        return back()->with('success', 'Kami telah mengirimkan link reset password ke email Anda.');
    }

    // fungsi show halaman reset password
    public function showHalamanResetPassword($token, $email)
    {
        return view('auth.reset_password', [
            'token' => $token,
            'email' => $email
        ]);
    }

    // fungsi reset password
    public function resetPassword(ResetPasswordRequest $request)
    {
        // pengecekan token valid dan email cocok
        $resetData = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        // validasi token ada dan cocok
        if (!$resetData || !Hash::check($request->token, $resetData->token)) {
            return back()->withErrors(['email' => 'Token reset password tidak valid atau sudah kadaluarsa.']);
        }

        // update password user di tabel users
        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password)
        ]);

        // menghapus token dari tabel agar tidak bisa digunakan kembali
        DB::table('password_reset_tokens')->where(['email' => $request->email])->delete();

        return redirect()->route('login.index')->with('success', 'Password Anda berhasil diperbarui.');
    }
}
