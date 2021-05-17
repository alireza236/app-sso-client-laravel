<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\User;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function (Request $request) {

    return view('welcome');
});

Route::get('/authsso', function (Request $request) {

        $request->session()->put("state", $state = Str::random(40));
        $query = http_build_query([
            "client_id" => "", //adalah id yang mewakili dari 1 client yang akan digunakan untuk proses autentikasi
            "redirect_uri" => "", // ini adalah URI callback untuk men-generate token setelah proses autentikasi berhasil  nama URI ini adalah nama URI aplikasi klien contoh : https://sikerja.bekasikota.go.id/callback
            "response_type" => "code",
            "state" => $state
        ]);

        return redirect("https://sso.bekasikota.go.id/oauth/authorize?". $query);

})->name('authsso');


Route::get('/callback', function (Request $request) {

    $state = $request->session()->pull("state");

    throw_unless(
        strlen($state) > 0 && $state === $request->state,
        InvalidArgumentException::class
    );

    $response = Http::asForm()->post('https://sso.bekasikota.go.id/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => '',       // adalah id yang mewakili dari 1 client yang akan digunakan untuk proses autentikasi
        'client_secret' => '',   // adalah pasangan dari client id, bisa disebut juga sebagai password nya
        'redirect_uri' => '',    // ini adalah URI callback untuk men-generate token setelah proses autentikasi berhasil  nama URI ini adalah nama URI aplikasi klien contoh : https://sikerja.bekasikota.go.id/callback
        'code' => $request->code,
    ]);


    $request->session()->put($response->json());

    $access_token = $request->session()->get('access_token');

    $response = Http::withHeaders([
        "Accept" => "application/json",
        "Authorization" => "Bearer " . $access_token
    ])->get("https://sso.bekasikota.go.id/api/user");

    $nip =  $response['nip'];

    $user = User::whereNip($nip)->first();

    //dd($user);

    /*
      Note : sebelum menyimpan data user ke auth session lakukan proses kueri data dgn menggunakan NIP untuk memastikan ada kesesuaian data antara SSO server dgn aplikasi klien
             apabila data tidak sesuai maka lakukan redirect ke http://sso.bekasikota.go.id/authsso/failed, dan apabila  data berdasarkan NIP sesuai antara SSO server
              dgn aplikasi klien maka simpan  ke dalam session sebagai autentikasi..
    */

    if ($user) {
        Auth::login($user);
        return redirect('/home');
    } else {
        abort(403, 'Unauthorized.');
        return redirect('http://sso.bekasikota.go.id/authsso/failed');
    }
});


Route::middleware(['auth'])->group(function () {
    Route::get('/home', function () {
        return "Hello ". auth()->user()->name;
    });

});
