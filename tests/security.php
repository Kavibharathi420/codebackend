<?php
// Integration tests use a newly created, randomly named MySQL database only.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\{DB, Hash, Artisan};
use App\Support\Jwt;

$database = 'simatrix_test_'.bin2hex(random_bytes(8));
$connection = config('database.connections.mysql');
$connection['database'] = null;
config(['database.connections.test_server'=>$connection, 'cache.default'=>'array', 'app.debug'=>false, 'services.jwt.secret'=>bin2hex(random_bytes(32)), 'services.jwt.days'=>1]);
$server = DB::connection('test_server');
$created = false;
$checks = 0;
function check($condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function requestApi(string $method, string $path, array $body = [], ?string $token = null, array $files = []): array {
    global $kernel;
    $headers = ['HTTP_ACCEPT'=>'application/json', 'CONTENT_TYPE'=>'application/json'];
    if ($token) $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    $request = Illuminate\Http\Request::create('/api'.$path, $method, [], [], $files, $headers, json_encode($body));
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    return [$response->getStatusCode(), json_decode($response->getContent(), true)];
}
try {
    $server->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
    $created = true;
    config(['database.connections.mysql.database'=>$database]);
    DB::purge('mysql');
    Artisan::call('migrate', ['--force'=>true]);
    $admin = DB::table('admins')->insertGetId(['name'=>'Admin', 'email'=>'admin@example.test', 'role'=>'admin', 'password_hash'=>Hash::make('Secure-password-123')]);
    $staff = DB::table('admins')->insertGetId(['name'=>'Staff', 'email'=>'staff@example.test', 'role'=>'staff', 'password_hash'=>Hash::make('Secure-password-456')]);
    $unknown = DB::table('admins')->insertGetId(['name'=>'Unknown', 'email'=>'unknown@example.test', 'role'=>'other', 'password_hash'=>'unused']);
    $a = Jwt::issue($admin); $s = Jwt::issue($staff);
    check(requestApi('GET','/admin/settings')[0] === 401, 'Anonymous access denied');
    check(requestApi('GET','/admin/settings', [], $a.'tampered')[0] === 401, 'Tampered JWT denied');
    check(requestApi('GET','/admin/enquiries', [], Jwt::issue($unknown))[0] === 403, 'Unknown role denied');
    foreach ([['GET','/admin/settings'],['PUT','/admin/settings'],['POST','/admin/upload'],['POST','/admin/admins'],['PUT','/admin/admins/'.$admin],['DELETE','/admin/admins/'.$admin],['DELETE','/admin/enquiries/1'],['POST','/admin/courses'],['PUT','/admin/testimonials/1'],['GET','/admin/activity']] as [$method,$path]) {
        check(requestApi($method,$path,[], $s)[0] === 403, "Staff forbidden: $method $path");
    }
    check(requestApi('GET','/admin/enquiries', [], $s)[0] === 200, 'Staff can read enquiries');
    [$status,$body] = requestApi('POST','/admin/admins', ['name'=>'New staff','email'=>'new@example.test','password'=>'Strong-password-123','role'=>'staff','password_hash'=>'injected'], $a);
    check($status === 201, 'Admin can create staff');
    $id = $body['data']['id'];
    check(!isset($body['data']['password_hash']), 'Create response hides hash');
    check(Hash::check('Strong-password-123', DB::table('admins')->where('id',$id)->value('password_hash')), 'Password securely hashed');
    check(requestApi('POST','/admin/admins',['name'=>'Duplicate','email'=>'new@example.test','password'=>'Strong-password-123','role'=>'staff'],$a)[0] === 422, 'Duplicate email rejected');
    check(requestApi('PUT','/admin/admins/'.$id,['role'=>'superuser'],$a)[0] === 422, 'Invalid role rejected');
    check(requestApi('PUT','/admin/admins/'.$id,['password'=>'short'],$a)[0] === 422, 'Weak password rejected');
    check(requestApi('PUT','/admin/admins/'.$id,['name'=>'Updated','password'=>'Another-password-123'],$a)[0] === 200, 'Staff update works');
    check(requestApi('POST','/auth/login',['email'=>'new@example.test','password'=>'Another-password-123'])[0] === 200, 'Updated password authenticates');
    check(requestApi('DELETE','/admin/admins/'.$admin,[],$a)[0] === 422, 'Self deletion denied');
    check(requestApi('PUT','/admin/admins/'.$admin,['role'=>'staff'],$a)[0] === 422, 'Self demotion denied');
    check(requestApi('DELETE','/admin/admins/'.$id,[],$a)[0] === 200, 'Staff deletion works');
    check(requestApi('DELETE','/admin/admins/'.$id,[],$a)[0] === 404, 'Missing deletion returns 404');
    [$status,$body] = requestApi('POST','/reviews',['name'=>'Learner','content'=>'A useful learning experience.','rating'=>5,'is_active'=>1]);
    check($status === 201, 'Public review accepted'); $review = $body['data']['id'];
    check((int)DB::table('testimonials')->where('id',$review)->value('is_active') === 0, 'Public cannot bypass moderation');
    check(count(requestApi('GET','/reviews')[1]['data']) === 0, 'Pending review hidden');
    check(count(requestApi('GET','/site')[1]['data']['testimonials']) === 0, 'Pending review hidden from homepage');
    check(requestApi('PUT','/admin/testimonials/'.$review,['is_active'=>true],$a)[0] === 200, 'Admin approves review');
    check(count(requestApi('GET','/reviews')[1]['data']) === 1, 'Approved review public');
    check(requestApi('POST','/reviews',['name'=>'Learner','content'=>'A useful experience.','rating'=>9])[0] === 422, 'Invalid rating rejected');
    check(requestApi('POST','/admin/courses',['title'=>'Missing category'],$a)[0] === 422, 'Required foreign key validated');
    check(requestApi('POST','/admin/categories',['name'=>['unexpected']],$a)[0] === 422, 'Malformed name returns validation error');
    [$status,$body] = requestApi('POST','/admin/categories',['name'=>'Testing','id'=>999,'unexpected'=>'injected'],$a);
    check($status === 201 && $body['data']['id'] !== 999, 'Unintended fields ignored');
    $category = $body['data']['id'];
    check(requestApi('POST','/admin/categories',['name'=>'Testing'],$a)[0] === 422, 'Generated slug uniqueness validated');
    check(requestApi('POST','/admin/courses',['title'=>'Hidden','category_id'=>$category,'is_active'=>false,'syllabus'=>['Lesson']],$a)[0] === 201, 'Course create works');
    check(requestApi('GET','/courses/hidden')[0] === 404, 'Inactive course hidden by slug');
    check(requestApi('POST','/admin/blog',['title'=>'Article'],$a)[0] === 201, 'Blog create works');
    check(requestApi('GET','/admin/blog',[],$a)[0] === 200, 'Blog list uses valid sort column');
    check(requestApi('PUT','/admin/settings',['contact_email'=>'invalid'],$a)[0] === 422, 'Settings validated');
    check(requestApi('PUT','/admin/settings',['contact_phone'=>'123','injected_setting'=>'value'],$a)[0] === 200, 'Settings save works');
    check(!DB::table('settings')->where('key','injected_setting')->exists(), 'Settings allowlist enforced');
    check(requestApi('POST','/enquiries',['name'=>'Learner','phone'=>'123','consent'=>false])[0] === 422, 'Public enquiry requires contact consent');
    [$status,$body] = requestApi('POST','/enquiries',['name'=>'Learner','phone'=>'123','preferred_contact'=>'whatsapp','consent'=>true,'status'=>'converted','assigned_to'=>$admin]);
    check($status === 201, 'Public enquiry works'); $enquiry = $body['data']['id'];
    check((bool) preg_match('/^CB-\d{6}-\d{6}$/D', $body['data']['reference']), 'Enquiry returns a reference number');
    check(DB::table('enquiries')->where('id',$enquiry)->value('status') === 'new', 'Public cannot set enquiry status');
    check(requestApi('PUT','/admin/enquiries/'.$enquiry,['status'=>'contacted','name'=>'Injected'],$s)[0] === 200, 'Staff updates enquiry status');
    check(DB::table('enquiries')->where('id',$enquiry)->value('name') === 'Learner', 'Staff cannot overwrite enquiry identity');
    check(requestApi('POST','/admin/enquiries/'.$enquiry.'/notes',['body'=>'Followed up'],$s)[0] === 201, 'Staff can add notes');
    check(requestApi('POST','/admin/enquiries/99999/notes',['body'=>'Missing parent'],$s)[0] === 404, 'Missing note parent returns 404');
    $svg = tempnam(sys_get_temp_dir(), 'simatrix_svg_');
    file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    try {
        $file = new Illuminate\Http\UploadedFile($svg, 'unsafe.svg', 'image/svg+xml', null, true);
        check(requestApi('POST','/admin/upload',[],$a,['file'=>$file])[0] === 422, 'SVG upload rejected');
    } finally { unlink($svg); }
    $png = tempnam(sys_get_temp_dir(), 'simatrix_png_');
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9N8AAAAASUVORK5CYII='));
    try {
        $file = new Illuminate\Http\UploadedFile($png, 'photo.png', 'image/png', null, true);
        [$status,$body] = requestApi('POST','/admin/upload',[],$a,['file'=>$file]);
        check($status === 201, 'Valid raster upload accepted');
        $filename = $body['data']['filename'];
        check((bool) preg_match('/^[a-zA-Z0-9]{40}\.png$/D', $filename), 'Upload name generated by server');
        if (preg_match('/^[a-zA-Z0-9]{40}\.png$/D', $filename)) unlink(public_path('src/assets/'.$filename));
    } finally { if (file_exists($png)) unlink($png); }
    for ($attempt = 0; $attempt < 5; $attempt++) $login = requestApi('POST','/auth/login',['email'=>'missing@example.test','password'=>'incorrect']);
    check($login[0] === 429, 'Login rate limit enforced');
    DB::table('admins')->where('id',$staff)->update(['role'=>'other']);
    check(requestApi('GET','/admin/enquiries',[],$s)[0] === 403, 'Role changes apply to existing JWT');
    config(['services.jwt.secret'=>'change-me']);
    $rejected = false;
    try { Jwt::issue($admin); } catch (RuntimeException) { $rejected = true; }
    check($rejected, 'Placeholder signing secret rejected');
    echo "PASS: $checks security integration checks\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: '.$error->getMessage()."\n");
    $failed = true;
} finally {
    DB::disconnect('mysql');
    if ($created && preg_match('/^simatrix_test_[a-f0-9]{16}$/D', $database)) $server->statement("DROP DATABASE `$database`");
}
exit(isset($failed) ? 1 : 0);
