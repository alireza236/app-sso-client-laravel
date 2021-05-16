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


Route::get('/authsso', function (Request $request) {

        $request->session()->put("state", $state = Str::random(40));
        $query = http_build_query([
            "client_id" => "", // 
            "redirect_uri" => "", // ini adalah URI callback untuk men-generate token setelah proses autentikasi berhasil  
                                  // nama URI ini adalah nama URI aplikasi klien contoh : https://sikerja.bekasikota.go.id/callback       
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
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => '', // ini adalah URI callback untuk men-generate token setelah proses autentikasi berhasil  
                                                            // nama URI ini adalah nama URI aplikasi klien contoh : https://sikerja.bekasikota.go.id/callback
        'code' => $request->code,
    ]);


    $request->session()->put($response->json());

    return redirect('/userinfo');
});


Route::get('/userinfo', function (Request $request) {

       $access_token = $request->session()->get('access_token');

       $response = Http::withHeaders([
           "Accept" => "application/json",
           "Authorization" => "Bearer ". $access_token
       ])->get("https://sso.bekasikota.go.id/api/user");

       $email =  $response['email'];

       $user = User::whereEmail($email)->first();

       //return $response->json();

       if ($user) {
           Auth::login($user);
           return redirect('/home');
       } else {
          abort(403, 'Unauthorized.');
       }
});


Route::get('/', function (Request $request) {

      return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/home', function () {
        return "Hello ". auth()->user()->name;
    });

});
